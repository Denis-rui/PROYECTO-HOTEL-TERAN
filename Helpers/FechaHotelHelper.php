<?php

namespace Helpers;

class FechaHotelHelper
{
    public static function ahora(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))
            ->format('Y-m-d H:i:s');
    }

    public static function hoy(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))
            ->format('Y-m-d');
    }
}
