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
            if (($reserva['porcentaje_pago'] ?? 0) > 0) {
                $acciones[] = 'cancelar';
            } else {
                $acciones[] = 'eliminar_pendiente';
            }
        }

        if ($estado === 'confirmada') {
            $fechaCheckin = $reserva['check_in_programado'] ?? null;
            $acciones[] = $this->esHorarioPreCheckin($fechaCheckin) ? 'pre_checkin' : 'checkin';
        }

        if ($estado === 'pre_checkin') {
            $fechaCheckin = $reserva['check_in_programado'] ?? null;
            if (!$this->esHorarioPreCheckin($fechaCheckin)) {
                $acciones[] = 'checkin';
            }
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

    /**
     * Determina si el momento actual corresponde al horario de pre-check-in.
     * Pre-check-in se muestra el mismo día de ingreso antes de las 13:40.
     * Si la reserva es de un día futuro, también se muestra pre-check-in
     * (el backend validará que no se ejecute antes de las 12:00).
     */
    private function esHorarioPreCheckin(?string $fechaCheckinProgramado): bool
    {
        $zona = new \DateTimeZone('America/Lima');
        $ahora = new \DateTimeImmutable('now', $zona);

        if (!$fechaCheckinProgramado) {
            // Sin fecha programada, usar la lógica del día actual
            $limite = $ahora->setTime(13, 40, 0);
            return $ahora < $limite;
        }

        try {
            $checkinDate = new \DateTimeImmutable($fechaCheckinProgramado, $zona);
        } catch (\Exception $e) {
            $limite = $ahora->setTime(13, 40, 0);
            return $ahora < $limite;
        }

        $hoyStr = $ahora->format('Y-m-d');
        $checkinStr = $checkinDate->format('Y-m-d');

        if ($hoyStr < $checkinStr) {
            // Día anterior al ingreso: mostrar pre-checkin (backend validará la hora mínima)
            return true;
        }

        if ($hoyStr === $checkinStr) {
            // Mismo día de ingreso: pre-checkin hasta las 13:40, después check-in
            $limite = $ahora->setTime(13, 40, 0);
            return $ahora < $limite;
        }

        // Día posterior al ingreso: ya corresponde check-in
        return false;
    }

    public function obtenerPorId(int $idReserva): ?array
    {
        return $this->reservaModel->obtenerReservaPorId($idReserva);
    }

}
