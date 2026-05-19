<?php
namespace App\Core;

class Model extends Conexion
{
    protected $table;
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function all()
    {
        $sql  = "SELECT * FROM {$this->table}";
        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $sql  = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conectar()->prepare($sql);
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function delete($id)
    {
        $sql  = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conectar()->prepare($sql);
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function insert($data)
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql          = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt         = $this->conectar()->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        return $stmt->execute();
    }

    public function update($id, $data)
    {
        $setClause = '';
        foreach ($data as $key => $value) {
            $setClause .= "{$key} = :{$key}, ";
        }
        $setClause = rtrim($setClause, ', ');
        $sql       = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :id";
        $stmt      = $this->conectar()->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function where($conditions)
    {
        $whereClause = '';
        foreach ($conditions as $key => $value) {
            $whereClause .= "{$key} = :{$key} AND ";
        }
        $whereClause = rtrim($whereClause, ' AND ');
        $sql         = "SELECT * FROM {$this->table} WHERE {$whereClause}";
        $stmt        = $this->conectar()->prepare($sql);
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
