<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Caja\CajaService;
use CodeIgniter\HTTP\RedirectResponse;

/** Cajas del Gerente: CU-80 Registrar Caja, CU-81 Cambiar Estado de Caja y CU-72 Asignar Caja a Cajero. */
class Cajas extends BaseController
{
    private CajaService $servicio;

    public function __construct()
    {
        $this->servicio = new CajaService();
    }

    /** CU-80 paso 2, CU-72 paso 2 y CU-81 paso 2. */
    public function index(): string
    {
        return view('cajas/index', ['titulo' => 'Cajas', 'cajas' => $this->servicio->listar(), 'cajeros' => $this->servicio->cajeros()]);
    }

    /** CU-80 pasos 3 a 6. */
    public function registrar(): RedirectResponse
    {
        try {
            $this->servicio->registrar((array) $this->request->getPost(['codigo', 'denominacion', 'ubicacion', 'observaciones']));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', 'alta');
        }

        return redirect()->route('cajas.registrar')->with('exito', 'Caja registrada y habilitada: ya se puede asignar a un Cajero (CU-72).');
    }

    /** CU-72 pasos 3 a 8. */
    public function asignar(int $id): RedirectResponse
    {
        try {
            $this->servicio->asignar($id, (int) $this->request->getPost('cajero_id'), (string) $this->request->getPost('desde'), (string) $this->request->getPost('hasta'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', "asignar-{$id}");
        }

        return redirect()->route('cajas.registrar')->with('exito', 'Caja asignada: el Cajero ya puede registrar su apertura (CU-31).');
    }

    /** CU-72 A5. */
    public function finalizarAsignacion(int $id): RedirectResponse
    {
        try {
            $this->servicio->finalizarAsignacion($id, (string) $this->request->getPost('motivo_fin'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', "finalizar-{$id}");
        }

        return redirect()->route('cajas.registrar')->with('exito', 'Asignación finalizada: la caja quedó disponible para una nueva asignación.');
    }

    /** CU-81 pasos 3 a 7. */
    public function cambiarEstado(int $id): RedirectResponse
    {
        try {
            $estado = $this->servicio->cambiarEstado($id, (string) $this->request->getPost('accion'), (string) $this->request->getPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', "estado-{$id}");
        }

        return redirect()->route('cajas.registrar')->with('exito', $estado === 'Inhabilitada' ? 'Caja inhabilitada: su asignación quedó finalizada y no podrá abrirse.' : 'Caja rehabilitada: ya se puede asignar.');
    }
}
