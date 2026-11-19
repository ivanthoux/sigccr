<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Validacion\Campos;
use App\Services\Caja\CajaService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Cajas del Gerente: CU-80 Registrar Caja, CU-81 Cambiar Estado de Caja y CU-72 Asignar Caja a Cajero; y CU-32
 * Consultar Movimientos de Caja (Gerente y Cajero).
 */
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
            $this->servicio->registrar(array_map(fn (string $c): string => $this->campoPost($c), ['codigo' => 'codigo', 'denominacion' => 'denominacion', 'ubicacion' => 'ubicacion', 'observaciones' => 'observaciones']));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', 'alta');
        }

        return redirect()->route('cajas.registrar')->with('exito', 'Caja registrada y habilitada: ya se puede asignar a un Cajero (CU-72).');
    }

    /** CU-72 pasos 3 a 8. */
    public function asignar(int $id): RedirectResponse
    {
        try {
            $this->servicio->asignar($id, $this->enteroPost('cajero_id'), $this->campoPost('desde'), $this->campoPost('hasta'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', "asignar-{$id}");
        }

        return redirect()->route('cajas.registrar')->with('exito', 'Caja asignada: el Cajero ya puede registrar su apertura (CU-31).');
    }

    /** CU-72 A5. */
    public function finalizarAsignacion(int $id): RedirectResponse
    {
        try {
            $this->servicio->finalizarAsignacion($id, $this->campoPost('motivo_fin'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', "finalizar-{$id}");
        }

        return redirect()->route('cajas.registrar')->with('exito', 'Asignación finalizada: la caja quedó disponible para una nueva asignación.');
    }

    /** CU-81 pasos 3 a 7. */
    public function cambiarEstado(int $id): RedirectResponse
    {
        try {
            $estado = $this->servicio->cambiarEstado($id, $this->campoPost('accion'), $this->campoPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('formulario', "estado-{$id}");
        }

        return redirect()->route('cajas.registrar')->with('exito', $estado === 'Inhabilitada' ? 'Caja inhabilitada: su asignación quedó finalizada y no podrá abrirse.' : 'Caja rehabilitada: ya se puede asignar.');
    }

    /** CU-32 pasos 1 a 4 (A1, A3). */
    public function movimientos(): string
    {
        $f = [
            'caja_id' => $this->enteroGet('caja'),
            'cajero_id' => $this->enteroGet('cajero'),
            'estado' => $this->campoGet('estado'),
            'fecha' => Campos::fecha($this->campoGet('fecha')),
            'fecha_texto' => $this->campoGet('fecha'),
        ];
        $soloCajero = $this->soloCajero();

        return view('cajas/movimientos', [
            'titulo' => 'Movimientos de caja', 'f' => $f, 'jornadas' => $this->servicio->jornadas($f, $soloCajero),
            'cajas' => $this->servicio->listar(), 'cajeros' => $soloCajero === null ? $this->servicio->cajeros() : [],
        ]);
    }

    /** CU-32 pasos 5 a 7 (A2, A3). */
    public function jornada(int $id): string|RedirectResponse
    {
        try {
            $j = $this->servicio->jornada($id, $this->soloCajero());
        } catch (ReglaNegocioException $e) {
            return redirect()->route('cajas.consultar-movimientos')->with('error', $e->getMessage());
        }

        return view('cajas/jornada', ['titulo' => 'Jornada de ' . $j['codigo'], 'j' => $j]);
    }

    /** CU-32 A3: el Cajero consulta solo sus jornadas; el Gerente, todas. */
    private function soloCajero(): ?int
    {
        return session('cuenta')['rol'] === 'Cajero' ? (int) session('cuenta')['id'] : null;
    }
}
