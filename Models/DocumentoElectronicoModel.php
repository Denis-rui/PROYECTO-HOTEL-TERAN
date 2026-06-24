<?php

namespace Models;

use Models\Entities\DocumentoElectronico; // agregamos para usar eloquent

class DocumentoElectronicoModel
{
    
    public function siguienteNumero(string $tipoDocumento, string $serie): int
    {
        $maximo = (int) DocumentoElectronico::where('tipo_documento', $tipoDocumento)
            ->where('serie', $serie)
            ->max('numero');

        return $maximo > 0 ? $maximo + 1 : 1;
    }

    public function obtenerPorCodigoUnico(string $codigoUnico): ?array
    {
        $documento = DocumentoElectronico::where('codigo_unico', $codigoUnico)
            ->first();

        return $documento ? $documento->toArray() : null;
    }

    public function obtenerPorReserva(int $idReserva): array
    {
        try {
            return DocumentoElectronico::where('id_reserva', $idReserva)
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            error_log('DocumentoElectronicoModel::obtenerPorReserva -> ' . $e->getMessage());
            return [];
        }
    }

    public function insertar(array $datos): bool
    {
        return DocumentoElectronico::create($datos) !== null;
    }
}
