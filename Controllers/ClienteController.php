<?php

namespace Controllers;

use Libraries\Core\ApiController;
use Helpers\CodigoHTTP;
use Services\ClienteService;

class ClienteController extends ApiController
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
        [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($respuesta);
        $this->responderJson($payload, $codigoHttp);
    }

    public function registrar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson();

        if ($datos === null) {
            $this->responderJson($this->respuestaJsonInvalido(), 400);
            return;
        }

        $respuesta = $this->clienteService->registrarCliente($datos);
        $this->responderServicio($respuesta);
    }

    public function actualizar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson();

        if ($datos === null) {
            $this->responderJson($this->respuestaJsonInvalido(), 400);
            return;
        }

        $respuesta = $this->clienteService->actualizarCliente($datos);
        $this->responderServicio($respuesta);
    }

    public function eliminar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson($this->respuestaJsonInvalido(), 400);
            return;
        }

        $respuesta = $this->clienteService->cambiarEstado((int)($datos['id'] ?? 0), 0);
        $this->responderServicio($respuesta);
    }

    public function habilitar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson($this->respuestaJsonInvalido(), 400);
            return;
        }

        $respuesta = $this->clienteService->cambiarEstado((int)($datos['id'] ?? 0), 1);
        $this->responderServicio($respuesta);
    }

    private function responderServicio(array $respuesta): void
    {
        [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($respuesta);
        $this->responderJson($payload, $codigoHttp);
    }

    private function respuestaJsonInvalido(): array
    {
        return [
            'exito' => false,
            'codigo' => 'VALIDACION_ERROR',
            'mensaje' => 'JSON inválido',
            'data' => null,
            'errores' => [],
        ];
    }
}
