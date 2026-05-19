<?php
namespace App\Core;

class Controller
{
    protected $views;
    protected $model;

    public function __construct()
    {
        $this->views = new Views();
        $this->loadModel();
    }

    public function loadModel()
    {
        // Deduce el nombre del Model a partir del Controller short name:
        $shortName = (new \ReflectionClass($this))->getShortName();
        $modelName = str_replace('Controller', 'Model', $shortName);
        $modelClass = "App\\Models\\{$modelName}";

        if (class_exists($modelClass)) {
            $this->model = new $modelClass();
        }
    }
}
