<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\ClienteService;

class ClienteController extends Controller
{
    private ClienteService $clienteService;

    public function __construct()
    {
        parent::__construct();
        $this->clienteService = new ClienteService();
    }

    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $nombre = $_GET['nombre'] ?? '';
        $data['page_title'] = 'Gestion de Clientes';
        $data['clientes'] = $this->clienteService->listarClientes($nombre);
        $data['page_js'] = ['Modal-Clientes.js', 'Clientes.js'];

        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        $this->responderJson($this->clienteService->listarClientes());
    }

    public function buscar($params = '')
    {
        $texto = $_GET['q'] ?? '';
        $respuesta = $this->clienteService->buscarParaReserva($texto);
        $this->responderJson($respuesta['data']);
    }

    public function consultarApiPeru($params = '')
    {
        $tipo = $_GET['tipo'] ?? 'dni';
        $documento = $_GET['documento'] ?? '';

        $respuesta = $this->clienteService->consultarApiExterna($tipo, $documento);
        $codigoHttp = $respuesta['code'] ?? 200;
        unset($respuesta['code']); // Quitamos el código interno del array final

        $this->responderJson($respuesta, $codigoHttp);
    }

    public function registrar($params = '')
    {
        $datos = $this->obtenerPayloadJson();

        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido'], 400);
            return;
        }

        $respuesta = $this->clienteService->registrarCliente($datos);
        $codigoHttp = $respuesta['code'] ?? 200;
        unset($respuesta['code']);

        $this->responderJson($respuesta, $codigoHttp);
    }

    public function actualizar($params = '')
    {
        $datos = $this->obtenerPayloadJson();

        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido'], 400);
            return;
        }

        $respuesta = $this->clienteService->actualizarCliente($datos);
        $codigoHttp = $respuesta['code'] ?? 200;
        unset($respuesta['code']);

        $this->responderJson($respuesta, $codigoHttp);
    }

    public function eliminar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido'], 400);
            return;
        }

        $respuesta = $this->clienteService->cambiarEstado((int)($datos['id'] ?? 0), 0);
        $codigoHttp = $respuesta['code'] ?? 200;
        unset($respuesta['code']);

        $this->responderJson($respuesta, $codigoHttp);
    }

    public function habilitar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido'], 400);
            return;
        }

        $respuesta = $this->clienteService->cambiarEstado((int)($datos['id'] ?? 0), 1);
        $codigoHttp = $respuesta['code'] ?? 200;
        unset($respuesta['code']);

        $this->responderJson($respuesta, $codigoHttp);
    }
}
