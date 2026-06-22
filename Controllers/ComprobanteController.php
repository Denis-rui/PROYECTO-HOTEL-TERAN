<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\Comprobantes\ComprobanteService;

class ComprobanteController extends Controller
{
    public function obtenerPorPago($params = '')
    {
        $idPago = (int) ($params ?? 0);

        $service = new ComprobanteService();

        $this->responderJson($service->obtenerPorPago($idPago));
    }

    public function emitidosPorReserva($params = '')
    {
        $idReserva = (int) ($params ?? 0);

        $service = new ComprobanteService();

        $this->responderJson($service->obtenerEmitidosPorReserva($idReserva));
    }
}
