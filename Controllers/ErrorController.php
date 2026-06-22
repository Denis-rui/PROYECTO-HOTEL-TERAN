<?php
namespace Controllers;

use Libraries\Core\Controller;

class ErrorController extends Controller
{
    public function index($params = '')
    {
        http_response_code(404);
        $this->views->render($this, 'error404');
    }
}
