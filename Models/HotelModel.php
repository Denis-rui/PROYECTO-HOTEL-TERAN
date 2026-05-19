<?php
namespace Models;

use Libraries\Core\Model;
use PDO;

class HotelModel extends Model
{
    protected $table = 'hotel';

    public function __construct()
    {
        parent::__construct();
    }

    public function read()
    {
        try {
            $sql = "SELECT * FROM hotel LIMIT 1";
            $statement = $this->conectar()->prepare($sql);
            $statement->execute();
            return $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Error al leer hotel: " . $e->getMessage());
        }
    }

    public function actualizarHotel($datos)
    {
        try {
            $sql = "UPDATE hotel SET 
                nombre        = :nombre,
                ruc           = :ruc,
                telefono      = :telefono,
                email         = :email,
                direccion     = :direccion,
                ciudad_region = :ciudad_region,
                descripcion   = :descripcion,
                moneda        = :moneda,
                check_in      = :check_in,
                check_out     = :check_out,
                web           = :web,
                porcentaje_adelanto = :porcentaje_adelanto,
                porcentaje_penalidad_cancelacion = :porcentaje_penalidad
                LIMIT 1";

            $statement = $this->conectar()->prepare($sql);
            return $statement->execute([
                ':nombre'        => $datos['nombre'] ?? '',
                ':ruc'           => $datos['ruc'] ?? '',
                ':telefono'      => $datos['telefono'] ?? '',
                ':email'         => $datos['email'] ?? '',
                ':direccion'     => $datos['direccion'] ?? '',
                ':ciudad_region' => $datos['ciudad-region'] ?? '',
                ':descripcion'   => $datos['descripcion-slogan'] ?? '',
                ':moneda'        => $datos['monedas'] ?? '',
                ':check_in'      => $datos['check-in'] ?? '',
                ':check_out'     => $datos['check-out'] ?? '',
                ':web'           => $datos['web-redes'] ?? '',
                ':porcentaje_adelanto' => $datos['porcentaje_adelanto'] ?? 50,
                ':porcentaje_penalidad' => $datos['porcentaje_penalidad'] ?? 25,
            ]);
        } catch (\PDOException $e) {
            throw new \Exception("Error al actualizar hotel: " . $e->getMessage());
        }
    }
}
