<?php
namespace Helpers;

class ReservaHelper
{
    public static function normalizarFecha($fecha)
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        return substr($fecha, 0, 10);
    }

    public static function obtenerDiasEstadia($checkIn, $checkOut)
    {
        $inicioTexto = self::normalizarFecha($checkIn);
        $finTexto = self::normalizarFecha($checkOut);

        $inicio = $inicioTexto ? \DateTime::createFromFormat('Y-m-d', $inicioTexto) : null;
        $fin = $finTexto ? \DateTime::createFromFormat('Y-m-d', $finTexto) : null;

        if (!$inicio || !$fin || $fin <= $inicio) {
            return 0;
        }

        return (int) $inicio->diff($fin)->days;
    }

    public static function combinarFechaHora($fecha, $hora = null)
    {
        $fecha = trim((string) $fecha);
        $hora = trim((string) $hora);

        if ($fecha === '') {
            return null;
        }

        if ($hora === '') {
            return $fecha . ' 12:00:00';
        }

        return $fecha . ' ' . $hora . ':00';
    }

    public static function calcularCargoCheckoutTarde($minutosDemora, $totalReserva)
    {
        if ($minutosDemora <= 30) {
            return 0;
        }

        if ($minutosDemora <= 120) {
            return 50;
        }

        return round(max(50, $totalReserva / 2), 2);
    }
}
