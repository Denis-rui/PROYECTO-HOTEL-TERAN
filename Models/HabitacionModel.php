<?php
namespace Models;

use Libraries\Core\Model;
use PDO;

// Importamos la clase base de modelos y manejador de BD de Laravel
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Capsule\Manager as DB;

class HabitacionModel extends Eloquent
{
    protected $table      = 'habitacion';
    protected $primaryKey = 'id';
    public $timestamps    = false;
    protected $fillable   = [
        'numero_habitacion', 'piso', 'id_tipo_habitacion',
        'estado', 'descripcion_habitacion', 'capacidad', 'activo'
    ];

    private const ESTADOS = ['Disponible', 'Ocupada', 'Mantenimiento', 'Reservada'];

    public function registrar($datos)
    {
        try {
            $estado = $this->normalizarEstado($datos['estado'] ?? 'Disponible');

            $habitacion = self::create([
                'numero_habitacion'      => $datos['numero_habitacion'] ?? '',
                'piso'                   => (int) ($datos['piso'] ?? 1),
                'id_tipo_habitacion'     => $datos['id_tipo_habitacion'] ?? null,
                'estado'                 => $estado,
                'descripcion_habitacion' => $datos['descripcion'] ?? '',
                'capacidad'              => (int) ($datos['capacidad'] ?? 1),
                'activo'                 => (int) ($datos['activo'] ?? 1),
            ]);

            return (bool) $habitacion;
        } catch (\Throwable $e) {
            throw new \Exception("Error al registrar habitación: " . $e->getMessage());
        }
    }

    public function buscar($numero, $tipo, $estado, $piso)
    {
        try {
            $query = DB::table('habitacion as h')
                ->join('tipo_habitacion as t', 't.id', '=', 'h.id_tipo_habitacion')
                ->leftJoin('reserva_habitacion as rh', function ($join) {
                    $join->on('rh.id_habitacion', '=', 'h.id')
                         ->where('rh.check_in', '<=', DB::raw('NOW()'))
                         ->where(function ($q) {
                             $q->whereNull('rh.check_out')
                               ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
                         })
                         ->where('rh.activo', '=', 1);
                })
                ->leftJoin('reserva as r', function ($join) {
                    $join->on('r.id', '=', 'rh.id_reserva')
                         ->whereIn('r.estado', ['en_estadia', 'checkout_pendiente']);
                })
                ->leftJoin('cliente as c', 'c.id', '=', 'r.id_cliente')
                ->where('h.activo', 1)
                ->select([
                    'h.id',
                    'h.numero_habitacion',
                    'h.piso',
                    'h.id_tipo_habitacion',
                    't.tipo as tipo_nombre',
                    DB::raw("COALESCE(NULLIF(h.descripcion_habitacion, ''), '') as descripcion"),
                    DB::raw('t.precio_base as precio'),
                    'h.estado',
                    'h.capacidad',
                    'h.activo',
                    'r.id as reserva_actual_id',
                    'c.nombre_completo as cliente_actual'
                ]);

            if ($numero !== null && $numero !== '') {
                $query->where('h.numero_habitacion', 'like', '%' . $numero . '%');
            }

            if ($tipo) {
                $query->where('h.id_tipo_habitacion', $tipo);
            }

            if ($estado) {
                $query->where('h.estado', $this->normalizarEstado($estado));
            }

            if ($piso) {
                $query->where('h.piso', (int) $piso);
            }

            $query->orderBy('h.piso', 'asc')
                  ->orderBy('h.numero_habitacion', 'asc');

            return $query->get()->map(function ($item) {
                return (array) $item;
            })->toArray();
        } catch (\Throwable $e) {
            throw new \Exception("Error al buscar habitaciones: " . $e->getMessage());
        }
    }

    public function actualizarEstado($id, $estado, $motivo = '')
    {
        try {
            $nuevoEstado = $this->normalizarEstado($estado);
            $habitacion = self::find($id);

            if (!$habitacion) {
                return ['exito' => false, 'mensaje' => 'Habitación no encontrada.'];
            }

            // Si se intenta marcar como Disponible, bloquear si existe cualquier
            // `reserva_habitacion` para la habitación con `check_out` NULL o en el futuro.
            if ($nuevoEstado === 'Disponible') {
                $bloqueoExiste = DB::table('reserva_habitacion as rh')
                    ->where('rh.id_habitacion', (int) $id)
                    ->where(function ($q) {
                        $q->whereNull('rh.check_out')
                          ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
                    })
                    ->exists();

                if ($bloqueoExiste) {
                    $bloqueante = DB::table('reserva_habitacion as rh')
                        ->leftJoin('reserva as r', 'r.id', '=', 'rh.id_reserva')
                        ->where('rh.id_habitacion', (int) $id)
                        ->where(function ($q) {
                            $q->whereNull('rh.check_out')
                              ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
                        })
                        ->select(['r.id as id_reserva', 'r.estado as estado_reserva', 'rh.check_in', 'rh.check_out'])
                        ->orderBy('rh.check_out', 'asc')
                        ->first();

                    $detalle = $bloqueante ? (array) $bloqueante : null;
                    if ($detalle && !empty($detalle['check_out'])) {
                        $checkOutTs = strtotime($detalle['check_out']);
                        $detalle['minutos_faltantes'] = $checkOutTs > time() ? (int) floor(($checkOutTs - time()) / 60) : 0;
                    }

                    return [
                        'exito' => false,
                        'mensaje' => 'No se puede marcar como Disponible: existe una reserva con checkout pendiente o sin hora registrada.',
                        'reserva_bloqueante' => $detalle
                    ];
                }
            }

            $updateData = ['estado' => $nuevoEstado];
            if (strtolower($nuevoEstado) === 'mantenimiento') {
                $updateData['descripcion_habitacion'] = $motivo;
            }

            // Si intentan marcar como Disponible, usar una actualización condicional
            // en la DB para evitar race conditions y garantizar que no exista
            // una reserva/estadia activa que bloquee el cambio.
            if ($nuevoEstado === 'Disponible') {
                $affected = DB::table('habitacion')
                    ->where('id', (int) $id)
                    ->whereNotExists(function ($query) use ($id) {
                        $query->select(DB::raw(1))
                            ->from('reserva_habitacion as rh')
                            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                            ->where('rh.id_habitacion', (int) $id)
                            ->where('rh.activo', 1)
                            ->whereIn('r.estado', ['checkin_realizado', 'en_estadia', 'checkout_pendiente'])
                            ->where(function ($q) {
                                $q->whereNull('rh.check_out')
                                  ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
                            });
                    })
                    ->update($updateData);

                if ($affected > 0) {
                    return ['exito' => true, 'mensaje' => 'Estado actualizado correctamente.'];
                }

                // Si no se actualizó, obtener la reserva bloqueante para informar
                $bloqueante = DB::table('reserva_habitacion as rh')
                    ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                    ->where('rh.id_habitacion', (int) $id)
                    ->where('rh.activo', 1)
                    ->whereIn('r.estado', ['checkin_realizado', 'en_estadia', 'checkout_pendiente'])
                    ->select(['r.id as id_reserva', 'r.estado as estado_reserva', 'rh.check_in', 'rh.check_out'])
                    ->orderBy('rh.check_out', 'asc')
                    ->first();

                $detalle = $bloqueante ? (array) $bloqueante : null;
                if ($detalle && !empty($detalle['check_out'])) {
                    $checkOutTs = strtotime($detalle['check_out']);
                    $minutosFaltantes = $checkOutTs > time() ? (int) floor(($checkOutTs - time()) / 60) : 0;
                    $detalle['minutos_faltantes'] = $minutosFaltantes;
                }

                return [
                    'exito' => false,
                    'mensaje' => 'No se puede marcar como Disponible: existe una estadía o checkout pendiente.',
                    'reserva_bloqueante' => $detalle
                ];
            }

            $ok = $habitacion->update($updateData);

            return [
                'exito'   => $ok,
                'mensaje' => $ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.'
            ];
        } catch (\Throwable $e) {
            return ['exito' => false, 'mensaje' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }

    public function obtenerFiltros()
    {
        try {
            $filtros = [];

            $filtros['tipos'] = DB::table('tipo_habitacion')
                ->where('activo', 1)
                ->orderBy('tipo', 'asc')
                ->select('id', 'tipo', 'precio_base', 'capacidad_maxima')
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })
                ->toArray();

            $filtros['pisos'] = self::where('activo', 1)
                ->distinct()
                ->orderBy('piso', 'asc')
                ->pluck('piso')
                ->toArray();

            $filtros['estados'] = self::where('activo', 1)
                ->distinct()
                ->orderBy('estado', 'asc')
                ->pluck('estado')
                ->toArray();

            return $filtros;
        } catch (\Throwable $e) {
            throw new \Exception("Error al obtener filtros: " . $e->getMessage());
        }
    }

    public function obtenerPorId($id)
    {
        $item = DB::table('habitacion as h')
            ->join('tipo_habitacion as t', 't.id', '=', 'h.id_tipo_habitacion')
            ->where('h.id', (int) $id)
            ->select([
                'h.id',
                'h.numero_habitacion',
                'h.piso',
                'h.id_tipo_habitacion',
                't.tipo as tipo_nombre',
                DB::raw('t.precio_base as precio'),
                'h.estado',
                'h.capacidad',
                'h.descripcion_habitacion',
                'h.activo'
            ])
            ->first();

        return $item ? (array) $item : null;
    }

    public function registrarHistorial($idHabitacion, $idReserva, $estadoAnterior, $estadoNuevo, $limpiezaAnterior = null, $limpiezaNueva = null, $accion = '', $comentario = '', $idUsuario = null)
    {
        try {
            return DB::table('historial_habitacion')->insert([
                'id_habitacion'             => (int) $idHabitacion,
                'id_reserva'                => $idReserva ? (int) $idReserva : null,
                'estado_anterior'           => $estadoAnterior,
                'estado_nuevo'              => $estadoNuevo,
                'estado_limpieza_anterior'  => $limpiezaAnterior,
                'estado_limpieza_nuevo'     => $limpiezaNueva,
                'accion'                    => $accion,
                'comentario'                => $comentario,
                'id_usuario'                => $idUsuario ? (int) $idUsuario : null,
                'fecha_registro'            => DB::raw('NOW()')
            ]);
        } catch (\Throwable $e) {
            error_log("Error al registrar historial de habitación: " . $e->getMessage());
            return false;
        }
    }

    public function disponiblesPorRango($checkIn, $checkOut, $tipo = null, $piso = null)
    {
        return (new ReporteOcupacionModel())->obtenerDisponiblesPorRango($checkIn, $checkOut, $tipo, $piso);
    }

    public function validarDisp_habitacion($idHabitacion, $checkIn, $checkOut)
    {
        return (new ReporteOcupacionModel())->validarDisponibilidadHabitacion($idHabitacion, $checkIn, $checkOut);
    }

    public function obtenerReser_EstadiaHab($idHabitacion)
    {
        return (new ReporteOcupacionModel())->obtenerReser_EstadiaHab($idHabitacion);
    }

    public function calcularTotalReserva($idHabitacion, $checkIn, $checkOut)
    {
        return (new ReporteOcupacionModel())->calcularTotalReserva($idHabitacion, $checkIn, $checkOut);
    }

    public function validarDisponibilidadHabitacion($idHabitacion, $checkIn, $checkOut, $idReservaExcluir = null)
    {
        return (new ReporteOcupacionModel())->validarDisponibilidadHabitacion($idHabitacion, $checkIn, $checkOut, $idReservaExcluir);
    }

    private function normalizarEstado($estado)
    {
        $estado = strtolower(trim((string) $estado));
        $mapa = [
            'disponible' => 'Disponible',
            'ocupada' => 'Ocupada',
            'ocupado' => 'Ocupada',
            'mantenimiento' => 'Mantenimiento',
            'mantenimie' => 'Mantenimiento',
            'reservada' => 'Reservada',
            'reservado' => 'Reservada',
        ];

        return $mapa[$estado] ?? 'Disponible';
    }

    private function obtenerReservaBloqueante(int $idHabitacion)
    {
        return (new ReporteOcupacionModel())->obtenerReservaBloqueante($idHabitacion);
    }
}