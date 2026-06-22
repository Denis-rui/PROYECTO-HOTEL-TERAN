<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;

class DocumentoElectronicoModel
{
    private const TABLE = 'documento_electronico_reserva';

    public function siguienteNumero(string $tipoDocumento, string $serie): int
    {
        $maximo = (int) DB::table(self::TABLE)
            ->where('tipo_documento', $tipoDocumento)
            ->where('serie', $serie)
            ->max('numero');

        return $maximo > 0 ? $maximo + 1 : 1;
    }

    public function obtenerPorCodigoUnico(string $codigoUnico): ?array
    {
        $documento = DB::table(self::TABLE)
            ->where('codigo_unico', $codigoUnico)
            ->first();

        return $documento ? (array) $documento : null;
    }

    public function obtenerPorReserva(int $idReserva): array
    {
        try {
            return DB::table(self::TABLE)
                ->where('id_reserva', $idReserva)
                ->orderBy('id', 'asc')
                ->get()
                ->map(static fn($documento) => (array) $documento)
                ->toArray();
        } catch (\Throwable $e) {
            error_log('DocumentoElectronicoModel::obtenerPorReserva -> ' . $e->getMessage());
            return [];
        }
    }

    public function insertar(array $datos): bool
    {
        return DB::table(self::TABLE)->insert($datos);
    }
}
