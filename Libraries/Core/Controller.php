<?php

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
        // Deduce el nombre del Model a partir del Controller:
        // "LoginController" → "LoginModel"
        $modelName = str_replace('Controller', 'Model', get_class($this));
        $modelPath = "Models/{$modelName}.php";

        if (file_exists($modelPath)) {
            require_once $modelPath;
            $this->model = new $modelName();
        }
    }
}
