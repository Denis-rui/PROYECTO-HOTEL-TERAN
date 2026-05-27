<?php
namespace Controllers;

use Libraries\Core\Controller;
use Models\ComprobanteModel;

class ComprobanteController extends Controller
{
    public function obtenerPorPago($params = '')
    {
        header('Content-Type: application/json');
        $idPago = (int) ($params ?? 0);
        $model = new ComprobanteModel();
        echo json_encode($model->obtenerPorPago($idPago));
    }

    public function emitidosPorReserva($params = '')
    {
        header('Content-Type: application/json');
        $idReserva = (int) ($params ?? 0);
        $model = new ComprobanteModel();
        echo json_encode($model->obtenerEmitidosPorReserva($idReserva));
    }
}
