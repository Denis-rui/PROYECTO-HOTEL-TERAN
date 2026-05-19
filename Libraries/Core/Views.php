<?php
namespace Libraries\Core;

class Views
{
    public function render($controller, $view, $data = [])
    {
        $short = (new \ReflectionClass($controller))->getShortName();
        $controllerName = str_replace('Controller', '', $short);
        $viewPath       = "Views/{$controllerName}/{$view}.php";

        if (file_exists($viewPath)) {
            if (!empty($data)) {
                extract($data);
            }

            // Si es un parcial o es el Login (que tiene su propia estructura completa)
            if ((isset($is_partial) && $is_partial) || $controllerName == 'Login') {
                require_once $viewPath;
            } else {
                // Renderizado estándar con Layout
                require_once "Views/Template/header.php";
                require_once $viewPath;
                require_once "Views/Template/footer.php";
            }
        } else {
            echo "<p style='color:red'>Vista no encontrada: {$viewPath}</p>";
        }
    }
}
