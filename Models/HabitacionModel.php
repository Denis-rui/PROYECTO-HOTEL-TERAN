<?php

namespace Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Reserva as ReservaEntity;
use Models\Entities\Habitacion;

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

    public function obtenerPorNumero(string $numeroHabitacion)
    {
        return self::where('numero_habitacion', $numeroHabitacion)->first();
    }

    public function actualizar(int $id, array $datos)
    {
        return self::where('id', $id)->update($datos);
    }

    public function darDeBaja(int $id)
    {
        return self::where('id', $id)->update(['activo' => 0]);
    }

    public function bloquearParaReserva(array $idsHabitaciones): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsHabitaciones))));
        sort($ids, SORT_NUMERIC);

        if (empty($ids)) {
            return;
        }

        DB::table('habitacion')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    public function obtenerPorId(int $id): ?array
    {
        $habitacion = Habitacion::with('tipoHabitacion')
            ->whereHas('tipoHabitacion')
            ->where('id', $id)
            ->first();

        if (!$habitacion) {
            return null;
        }

        return [
            'id' => $habitacion->id,
            'numero_habitacion' => $habitacion->numero_habitacion,
            'piso' => $habitacion->piso,
            'id_tipo_habitacion' => $habitacion->id_tipo_habitacion,
            'tipo_nombre' => $habitacion->tipoHabitacion->tipo,
            'precio' => $habitacion->tipoHabitacion->precio_base,
            'estado' => $habitacion->estado,
            'capacidad' => $habitacion->capacidad,
            'descripcion_habitacion' => $habitacion->descripcion_habitacion,
            'activo' => $habitacion->activo,
        ];
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
        $estadosOcupacion = ReservaEntity::ESTADOS_OCUPACION_ACTUAL;

        $subReserva = DB::table('reserva_habitacion as rh2')
            ->join('reserva as r2', 'r2.id', '=', 'rh2.id_reserva')
            ->where('rh2.activo', 1)
            ->whereRaw("LOWER(TRIM(COALESCE(rh2.estado, 'activa'))) = 'activa'")
            ->whereIn('r2.estado', $estadosOcupacion)
            ->whereNull('r2.checkout_real')
            ->select([
                'rh2.id_habitacion',
                DB::raw('MIN(r2.id) as id_reserva_activa'),
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
                DB::raw("CASE WHEN sr.id_reserva_activa IS NOT NULL THEN 'Ocupada' ELSE h.estado END as estado"),
                'h.estado as estado_bd',
                'h.capacidad',
                'h.activo',
                'h.limpieza_inicio',
                'r.id as reserva_actual_id',
                DB::raw("CONCAT(COALESCE(c.nombres, ''), ' ', COALESCE(c.apellido_paterno, ''), ' ', COALESCE(c.apellido_materno, '')) as cliente_actual")
            ]);

        if ($numero) $query->where('h.numero_habitacion', 'like', '%' . $numero . '%');
        if ($tipo) $query->where('h.id_tipo_habitacion', $tipo);
        if ($piso) $query->where('h.piso', (int) $piso);

        if ($estadoNorm) {
            $query->where(function ($q) use ($estadoNorm) {
                if ($estadoNorm === 'Ocupada') {
                    $q->whereNotNull('sr.id_reserva_activa')
                        ->orWhere('h.estado', 'Ocupada');
                } else {
                    $q->whereNull('sr.id_reserva_activa')->where('h.estado', $estadoNorm);
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
