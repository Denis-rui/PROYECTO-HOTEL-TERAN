<?php

namespace Libraries\Core;

class Router
{
    private array $routes = [];

    public function get(string $route, array $action): void
    {
        $this->routes['GET'][$route] = $action;
    }

    public function post(string $route, array $action): void
    {
        $this->routes['POST'][$route] = $action;
    }

    public function resolve(): array
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $url = $_GET['url'] ?? 'Login/index';

        $partes = explode('/', trim($url, '/'));

        $ruta = $partes[0] . '/' . ($partes[1] ?? 'index');

        $params = array_slice($partes, 2);

        if (!isset($this->routes[$requestMethod][$ruta])) {
            http_response_code(404);
            die('Ruta no encontrada');
        }

        return [
            'controller' => $this->routes[$requestMethod][$ruta][0],
            'method'     => $this->routes[$requestMethod][$ruta][1],
            'params'     => implode(',', $params)
        ];
    }
}
