<?php

namespace Services\Reservas;

use Models\ReporteOcupacionModel;
use Models\ReservaModel;

class ConsultarReservaService
{
    private ReservaModel $reservaModel;
    private ReporteOcupacionModel $reporteOcupacionModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->reporteOcupacionModel = new ReporteOcupacionModel();
    }

    public function listar(array $filtros, int $limite): array
    {
        return $this->reservaModel->obtenerReservas($filtros, $limite);
    }

    public function listarParaDataTable(array $parametros): array
    {
        // Este método separa la consulta vieja de la consulta especial que exige DataTables:
        // paginación por start/length, búsqueda global y conteo total/filtrado.
        return $this->reservaModel->obtenerReservasDataTable($parametros);
    }

    public function obtenerPorId(int $idReserva): ?array
    {
        return $this->reservaModel->obtenerReservaPorId($idReserva);
    }

    public function calcularTotal(int $idHabitacion, string $checkIn, string $checkOut)
    {
        return $this->reporteOcupacionModel->calcularTotalReserva($idHabitacion, $checkIn, $checkOut);
    }
}
