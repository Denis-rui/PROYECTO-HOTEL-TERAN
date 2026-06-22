<?php

namespace Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Reserva as ReservaEntity;

class HabitacionModel extends Eloquent
{
    protected $table = 'habitacion';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'numero_habitacion',
        'piso',
        'id_tipo_habitacion',
        'estado',
        'descripcion_habitacion',
        'capacidad',
        'activo',
        'limpieza_inicio'
    ];

    // ── MÉTODOS DE ESCRITURA BÁSICA ──

    public function crear(array $datos)
    {
        return self::create($datos);
    }

    public function actualizar(int $id, array $datos)
    {
        return self::where('id', $id)->update($datos);
    }

    public function darDeBaja(int $id)
    {
        return self::where('id', $id)->update(['activo' => 0]);
    }

    public function obtenerPorId(int $id): ?array
    {
        $item = DB::table('habitacion as h')
            ->join('tipo_habitacion as t', 't.id', '=', 'h.id_tipo_habitacion')
            ->where('h.id', $id)
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
                'h.activo',
            ])
            ->first();

        return $item ? (array) $item : null;
    }

    /**
     * Registra cambios operativos de una habitación cuando la auditoría está
     * disponible. La reserva no debe fallar por no contar con esta tabla
     * auxiliar en instalaciones que todavía no la hayan creado.
     */
    public function registrarHistorial(int $idHabitacion, ?int $idReserva, ?string $estadoAnterior, ?string $estadoNuevo, ?string $limpiezaAnterior = null, ?string $limpiezaNueva = null, string $accion = '', string $comentario = '', ?int $idUsuario = null): bool
    {
        try {
            return DB::table('historial_habitacion')->insert([
                'id_habitacion' => $idHabitacion,
                'id_reserva' => $idReserva,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'estado_limpieza_anterior' => $limpiezaAnterior,
                'estado_limpieza_nuevo' => $limpiezaNueva,
                'accion' => $accion,
                'comentario' => $comentario,
                'id_usuario' => $idUsuario,
                'fecha_registro' => DB::raw('NOW()'),
            ]);
        } catch (\Throwable $e) {
            error_log('HabitacionModel::registrarHistorial -> ' . $e->getMessage());
            return false;
        }
    }


    public function obtenerReservaActiva(int $idHabitacion)
    {
        return DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->where('rh.id_habitacion', $idHabitacion)
            ->where('rh.activo', 1)
            ->whereIn('r.estado', ReservaEntity::ESTADOS_BLOQUEANTES)
            ->where(function ($q) {
                $q->whereNull('rh.check_out')
                    ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
            })
            ->first();
    }

    public function obtenerBloqueante(int $idHabitacion)
    {
        return DB::table('reserva_habitacion as rh')
            ->leftJoin('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->where('rh.id_habitacion', $idHabitacion)
            ->whereIn('r.estado', ReservaEntity::ESTADOS_BLOQUEANTES)
            ->where(function ($q) {
                $q->whereNull('rh.check_out')
                    ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
            })
            ->select(['r.id as id_reserva', 'r.estado as estado_reserva', 'rh.check_in', 'rh.check_out'])
            ->orderBy('rh.check_out', 'asc')
            ->first();
    }

    // La búsqueda compleja se queda aquí porque es 100% lógica de Base de Datos
    public function buscar($numero, $tipo, $estadoNorm, $piso)
    {
        $estadosBloqueantes = ReservaEntity::ESTADOS_BLOQUEANTES;
        $estadosOcupacion = ReservaEntity::ESTADOS_OCUPACION_ACTUAL;
        $estadosPreCheckin = ReservaEntity::ESTADOS_PRE_CHECKIN;
        $estadosOcupacionSql = "'" . implode("','", $estadosOcupacion) . "'";
        $estadosPreCheckinSql = "'" . implode("','", $estadosPreCheckin) . "'";

        $subReserva = DB::table('reserva_habitacion as rh2')
            ->join('reserva as r2', 'r2.id', '=', 'rh2.id_reserva')
            ->where('rh2.activo', 1)
            ->whereIn('r2.estado', $estadosBloqueantes)
            ->where(function ($q) {
                $q->whereNull('rh2.check_out')->orWhere('rh2.check_out', '>', DB::raw('NOW()'));
            })
            ->select([
                'rh2.id_habitacion',
                DB::raw('MIN(r2.id) as id_reserva_activa'),
                DB::raw("MAX(CASE WHEN r2.estado IN ({$estadosOcupacionSql}) THEN 'Ocupada' WHEN r2.estado IN ({$estadosPreCheckinSql}) THEN 'Reservada' ELSE NULL END) as estado_por_reserva")
            ])
            ->groupBy('rh2.id_habitacion');

        $query = DB::table('habitacion as h')
            ->join('tipo_habitacion as t', 't.id', '=', 'h.id_tipo_habitacion')
            ->leftJoinSub($subReserva, 'sr', 'sr.id_habitacion', '=', 'h.id')
            ->leftJoin('reserva as r', 'r.id', '=', 'sr.id_reserva_activa')
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
                DB::raw("CASE WHEN sr.estado_por_reserva IS NOT NULL THEN sr.estado_por_reserva ELSE h.estado END as estado"),
                'h.estado as estado_bd',
                'h.capacidad',
                'h.activo',
                'h.limpieza_inicio',
                'r.id as reserva_actual_id',
                'c.nombre_completo as cliente_actual'
            ]);

        if ($numero) $query->where('h.numero_habitacion', 'like', '%' . $numero . '%');
        if ($tipo) $query->where('h.id_tipo_habitacion', $tipo);
        if ($piso) $query->where('h.piso', (int) $piso);

        if ($estadoNorm) {
            $query->where(function ($q) use ($estadoNorm, $estadosOcupacion, $estadosPreCheckin) {
                if ($estadoNorm === 'Ocupada') {
                    $q->whereIn('r.estado', $estadosOcupacion);
                } elseif ($estadoNorm === 'Reservada') {
                    $q->whereIn('r.estado', $estadosPreCheckin)
                        ->whereNotIn('h.id', function ($sub) {
                            $sub->select('rh3.id_habitacion')->from('reserva_habitacion as rh3')
                                ->join('reserva as r3', 'r3.id', '=', 'rh3.id_reserva')
                                ->whereIn('r3.estado', ReservaEntity::ESTADOS_OCUPACION_ACTUAL)->where('rh3.activo', 1);
                        });
                } else {
                    if ($estadoNorm === 'Mantenimiento') {
                        $q->whereNull('sr.id_reserva_activa')->whereIn('h.estado', ['Mantenimiento', 'En Limpieza']);
                    } else {
                        $q->whereNull('sr.id_reserva_activa')->where('h.estado', $estadoNorm);
                    }
                }
            });
        }

        return $query->orderBy('h.piso', 'asc')->orderBy('h.numero_habitacion', 'asc')->get()->map(fn($item) => (array) $item)->toArray();
    }

    public function obtenerFiltros()
    {
        return [
            'tipos' => DB::table('tipo_habitacion')->where('activo', 1)->orderBy('tipo', 'asc')->select('id', 'tipo', 'precio_base', 'capacidad_maxima')->get()->map(fn($i) => (array) $i)->toArray(),
            'pisos' => self::where('activo', 1)->distinct()->orderBy('piso', 'asc')->pluck('piso')->toArray(),
            'estados' => self::where('activo', 1)->distinct()->orderBy('estado', 'asc')->pluck('estado')->toArray(),
        ];
    }
}
