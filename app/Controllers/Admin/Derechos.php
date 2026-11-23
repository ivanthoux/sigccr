<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Clientes\ClienteService;
use App\Services\Clientes\DerechosTitularService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-108 Atender Solicitud de Derechos del Titular, pasos 4 a 8 (panel): el Analista ve las solicitudes por vencimiento,
 * registra las que llegan en la sucursal y responde (atiende o deniega) con su motivo. La revisión de una decisión
 * automatizada la resuelve el Gerente (art. 20, RN-86).
 */
class Derechos extends BaseController
{
    private DerechosTitularService $servicio;

    public function __construct()
    {
        $this->servicio = new DerechosTitularService();
    }

    public function index(): string
    {
        $estado = $this->request->getGet('estado') === null ? 'Pendiente' : trim($this->campoGet('estado'));
        $tipo = trim($this->campoGet('tipo'));

        return view('derechos/index', [
            'titulo' => 'Derechos del titular de los datos', 'estado' => $estado, 'tipo' => $tipo,
            'solicitudes' => $this->servicio->buscar($estado, $tipo),
        ]);
    }

    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('derechos.atender')->with('error', $e->getMessage());
        }

        return view('derechos/ver', ['titulo' => 'Solicitud de derechos N.º ' . $id, 's' => $s, 'puedeRevisar' => tiene_permiso('solicitudes.resolver')]);
    }

    /** Pantalla de registro de una solicitud recibida en la sucursal; el cliente llega por su identificador interno. */
    public function nueva(): string|RedirectResponse
    {
        $clienteId = $this->enteroGet('cliente');
        try {
            $c = (new ClienteService())->obtener($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('derechos.atender')->with('error', $e->getMessage());
        }

        return view('derechos/nueva', ['titulo' => 'Registrar solicitud de derechos', 'c' => $c, 'revisables' => $this->servicio->solicitudesRevisables($clienteId)]);
    }

    public function registrar(): RedirectResponse
    {
        $clienteId = $this->enteroPost('cliente_id');
        try {
            $id = $this->servicio->registrar($clienteId, [
                'tipo' => $this->campoPost('tipo'), 'detalle' => $this->campoPost('detalle'), 'solicitud_id' => $this->enteroPost('solicitud_id'),
            ], 'Sucursal');
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/derechos/{$id}"))->with('exito', 'Solicitud registrada. Responda antes de la fecha límite.');
    }

    public function resolver(int $id): RedirectResponse
    {
        try {
            $this->servicio->resolver($id, $this->enteroPost('version_fila'), $this->campoPost('estado'), $this->campoPost('respuesta'), (int) session('cuenta')['id'], tiene_permiso('solicitudes.resolver'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/derechos/{$id}"))->with('exito', 'Respuesta registrada.');
    }
}
