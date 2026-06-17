<?php
namespace Libraries\Core;

class Validator
{
    private array $errores = [];
    private array $datos = [];

    public function __construct(array $datos = [])
    {
        $this->datos = $datos;
    }

    public function requerido(string $campo, string $etiqueta = ''): self
    {
        $etiqueta = $etiqueta ?: $campo;
        if (empty(trim($this->datos[$campo] ?? ''))) {
            $this->errores[$campo] = ucfirst($etiqueta) . ' es obligatorio';
        }
        return $this;
    }

    public function email(string $campo, string $etiqueta = ''): self
    {
        if (!empty($this->datos[$campo])) {
            if (!filter_var($this->datos[$campo], FILTER_VALIDATE_EMAIL)) {
                $etiqueta = $etiqueta ?: $campo;
                $this->errores[$campo] = 'El ' . strtolower($etiqueta) . ' no es válido';
            }
        }
        return $this;
    }

    public function minimo(string $campo, int $longitud, string $etiqueta = ''): self
    {
        if (!empty($this->datos[$campo])) {
            if (strlen($this->datos[$campo]) < $longitud) {
                $etiqueta = $etiqueta ?: $campo;
                $this->errores[$campo] = 'El ' . strtolower($etiqueta) . ' debe tener mínimo ' . $longitud . ' caracteres';
            }
        }
        return $this;
    }

    public function numerico(string $campo, string $etiqueta = ''): self
    {
        if (!empty($this->datos[$campo])) {
            if (!is_numeric($this->datos[$campo])) {
                $etiqueta = $etiqueta ?: $campo;
                $this->errores[$campo] = 'El ' . strtolower($etiqueta) . ' debe ser un número';
            }
        }
        return $this;
    }

    public function dniValido(string $campo, string $etiqueta = ''): self
    {
        if (!empty($this->datos[$campo])) {
            $dni = preg_replace('/\D+/', '', $this->datos[$campo]);
            if (strlen($dni) !== 8 || !ctype_digit($dni)) {
                $etiqueta = $etiqueta ?: 'DNI';
                $this->errores[$campo] = 'El ' . strtolower($etiqueta) . ' debe tener 8 dígitos';
            }
        }
        return $this;
    }


    public function rucValido(string $campo, string $etiqueta = ''): self
    {
        if (!empty($this->datos[$campo])) {
            $ruc = preg_replace('/\D+/', '', $this->datos[$campo]);
            if (strlen($ruc) !== 11 || !ctype_digit($ruc)) {
                $etiqueta = $etiqueta ?: 'RUC';
                $this->errores[$campo] = 'El ' . strtolower($etiqueta) . ' debe tener 11 dígitos';
            }
        }
        return $this;
    }

    public function en(string $campo, array $valores, string $etiqueta = ''): self
    {
        if (!empty($this->datos[$campo])) {
            if (!in_array($this->datos[$campo], $valores, true)) {
                $etiqueta = $etiqueta ?: $campo;
                $this->errores[$campo] = 'El valor de ' . strtolower($etiqueta) . ' no es válido';
            }
        }
        return $this;
    }

    public function falla(): bool
    {
        return !empty($this->errores);
    }

    public function pasa(): bool
    {
        return empty($this->errores);
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function primerError(): ?string
    {
        return array_values($this->errores)[0] ?? null;
    }

    public static function limpiar(string $dato): string
    {
        return trim(htmlspecialchars($dato ?? '', ENT_QUOTES, 'UTF-8'));
    }

    public static function obtenerDatos(array $campos = []): array
    {
        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = self::limpiar($_POST[$campo] ?? '');
        }
        return $datos;
    }
}