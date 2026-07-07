<?php

namespace Services\Reservas;

use Models\ReservaModel;

class ConsultarReservaService
{
    private ReservaModel $reservaModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
    }


    public function listarParaDataTable(array $parametros): array
    {
        // El servicio traduce la respuesta del modelo al contrato que DataTables espera.
        // El controlador se mantiene delgado: recibe petición, llama servicio y responde JSON.
        $resultado = $this->reservaModel->obtenerReservasDataTable($parametros);
        $reservas = array_map(
            fn(array $reserva): array => $this->agregarAccionesDisponibles($reserva),
            $resultado['items'] ?? []
        );

        return [
            'draw' => (int) ($parametros['draw'] ?? 0),
            'recordsTotal' => (int) ($resultado['total'] ?? 0),
            'recordsFiltered' => (int) ($resultado['filtrados'] ?? 0),
            'data' => $reservas,
        ];
    }

    private function agregarAccionesDisponibles(array $reserva): array
    {
        $estado = strtolower((string) ($reserva['estado'] ?? ''));
        $acciones = ['editar', 'pago', 'ver_detalles'];

        if (in_array($estado, ['en_estadia', 'checkout_pendiente', 'checkout_realizado'], true)) {
            $acciones[] = 'emitir_documento';
        }

        if ($estado === 'pendiente') {
            $acciones[] = 'eliminar_pendiente';
        }

        if ($estado === 'confirmada') {
            $acciones[] = $this->esAntesCheckinNormal() ? 'pre_checkin' : 'checkin';
        }

        if ($estado === 'pre_checkin' && !$this->esAntesCheckinNormal()) {
            $acciones[] = 'checkin';
        }

        if (in_array($estado, ['en_estadia', 'checkout_pendiente'], true)) {
            $acciones[] = 'checkout';
        }

        if ($estado === 'en_estadia') {
            $acciones[] = 'marcar_ausente';
        }

        if ($estado === 'ausente') {
            $acciones[] = 'marcar_regreso';
        }

        if (!in_array($estado, ['pendiente', 'cancelada', 'checkout_realizado'], true)) {
            $acciones[] = 'cancelar';
        }

        $reserva['acciones_disponibles'] = $acciones;
        return $reserva;
    }

    private function esAntesCheckinNormal(): bool
    {
        return (int) (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))->format('H') < 14;
    }

    public function obtenerPorId(int $idReserva): ?array
    {
        return $this->reservaModel->obtenerReservaPorId($idReserva);
    }

}
