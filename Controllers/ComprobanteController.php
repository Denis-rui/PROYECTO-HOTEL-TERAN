<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\Comprobantes\ComprobanteService;

class ComprobanteController extends Controller
{
    public function obtenerPorPago($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $idPago = (int) ($params ?? 0);

        $service = new ComprobanteService();

        echo json_encode(
            $service->obtenerPorPago($idPago),
            JSON_UNESCAPED_UNICODE
        );
    }

    public function emitidosPorReserva($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $idReserva = (int) ($params ?? 0);

        $service = new ComprobanteService();

        echo json_encode(
            $service->obtenerEmitidosPorReserva($idReserva),
            JSON_UNESCAPED_UNICODE
        );
    }
}
