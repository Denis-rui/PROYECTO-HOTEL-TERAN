<?php
namespace App\Controllers;

use App\Core\Controller;

class ErrorController extends Controller
{
    public function index($params = '')
    {
        $this->views->render($this, 'error404');
    }
}

$objError = new ErrorController();
$objError->index();
