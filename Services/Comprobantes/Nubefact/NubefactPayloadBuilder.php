<?php

namespace Services\Comprobantes\Nubefact;

class NubefactPayloadBuilder
{
    private const IGV = 18.0;

    public function construirPayload(array $datos): array
    {
        $documento = $this->codigoDocumentoSunat(
            (string) ($datos['cliente_tipo_documento'] ?? '-'),
            (string) ($datos['cliente_numero_documento'] ?? '')
        );

        return [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => (int) ($datos['tipo_de_comprobante'] ?? 2),
            'serie' => (string) ($datos['serie'] ?? $this->seriePorTipo((string) ($datos['tipo_documento'] ?? 'BOLETA'))),
            'numero' => (int) ($datos['numero'] ?? 1),
            'sunat_transaction' => 1,
            'cliente_tipo_de_documento' => $documento['codigo'],
            'cliente_numero_de_documento' => $documento['numero'],
            'cliente_denominacion' => (string) ($datos['cliente_denominacion'] ?? ''),
            'cliente_direccion' => (string) ($datos['cliente_direccion'] ?? ''),
            'cliente_email' => (string) ($datos['cliente_email'] ?? ''),
            'fecha_de_emision' => (string) ($datos['fecha_de_emision'] ?? date('d-m-Y')),
            'moneda' => 1,
            'porcentaje_de_igv' => self::IGV,
            'total_exonerada' => (float) ($datos['total_exonerada'] ?? 0),
            'total_igv' => (float) ($datos['total_igv'] ?? 0),
            'total' => (float) ($datos['total'] ?? 0),
            'observaciones' => (string) ($datos['observaciones'] ?? ''),
            'enviar_automaticamente_a_la_sunat' => true,
            'formato_de_pdf' => 'A4',
            'bienes_region_selva' => false,
            'servicios_region_selva' => true,
            'items' => $datos['items'] ?? [],
        ];
    }

    private function seriePorTipo(string $tipoDocumento): string
    {
        if (strtoupper($tipoDocumento) === 'FACTURA') {
            return defined('NUBEFACT_SERIE_FACTURA') ? (string) constant('NUBEFACT_SERIE_FACTURA') : 'FFF1';
        }
        return defined('NUBEFACT_SERIE_BOLETA') ? (string) constant('NUBEFACT_SERIE_BOLETA') : 'BBB1';
    }

    private function codigoDocumentoSunat(string $codigo, string $numero): array
    {
        $codigo = strtoupper(trim($codigo));
        $numero = preg_replace('/\D+/', '', trim($numero));

        if ($codigo === '6' || strlen($numero) === 11) return ['codigo' => '6', 'numero' => $numero];
        if ($codigo === '1' || strlen($numero) === 8) return ['codigo' => '1', 'numero' => $numero];
        if ($codigo === '7') return ['codigo' => '7', 'numero' => $numero];
        if ($codigo === '0') return ['codigo' => '0', 'numero' => $numero];

        return ['codigo' => '-', 'numero' => $numero];
    }
}
