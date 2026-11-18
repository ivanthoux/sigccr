<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Cartera\RefinanciacionService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-39 Simular Plan de Refinanciación, CU-40 Registrar Propuesta, CU-41 Resolver, CU-42 Localizar, CU-43 Consultar,
 * CU-74 Registrar Aceptación y Aplicar y CU-96 Cancelar Propuesta.
 */
class Refinanciaciones extends BaseController
{
    private RefinanciacionService $servicio;

    public function __construct()
    {
        $this->servicio = new RefinanciacionService();
    }

    /** CU-42 pasos 1 a 4 (A1, A2). */
    public function index(): string
    {
        $f = [];
        foreach (['numero', 'texto', 'estado', 'desde', 'hasta'] as $k) {
            $f[$k] = trim($this->campoGet($k));
        }
        $errores = [];
        try {
            $propuestas = $this->servicio->buscar($f);
        } catch (ReglaNegocioException $e) {
            $propuestas = [];
            $errores = $e instanceof ValidacionException ? $e->errores : [];
        }

        return view('refinanciaciones/index', ['titulo' => 'Propuestas de refinanciación', 'filtros' => $f, 'propuestas' => $propuestas, 'erroresFiltro' => $errores]);
    }

    /** CU-39 pasos 1 a 6 (A1). */
    public function simular(): string|RedirectResponse
    {
        $cliente = $this->clienteSeleccionado('refinanciaciones.simular', 'Simular refinanciación');
        if (!is_array($cliente) || !isset($cliente['id'])) {
            return $cliente;
        }

        return view('refinanciaciones/simular', [
            'titulo' => 'Simular refinanciación', 'cliente' => $cliente, 'creditos' => $this->servicio->creditos((int) $cliente['id']), 'limites' => $this->servicio->limites(),
        ]);
    }

    /** CU-39 pasos 7 a 10 (A2). */
    public function crearSimulacion(): RedirectResponse
    {
        try {
            $id = $this->servicio->simular(
                (int) session('cuenta')['id'],
                $this->enteroPost('cliente_id'),
                (array) $this->request->getPost('creditos'),
                $this->campoPost('tasa'),
                $this->campoPost('cuotas'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/refinanciaciones/simulaciones/{$id}"))
            ->with('exito', 'Simulación registrada, vigente por 7 días corridos. No modifica la deuda ni genera una propuesta (RN-36).');
    }

    public function simulacion(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->simulacion($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('refinanciaciones.simular')->with('error', $e->getMessage());
        }

        return view('refinanciaciones/simulacion', ['titulo' => 'Simulación de refinanciación', 's' => $s]);
    }

    /** CU-40 pasos 1 a 3. */
    public function nueva(): string|RedirectResponse
    {
        $cliente = $this->clienteSeleccionado('refinanciaciones.registrar-propuesta', 'Registrar propuesta de refinanciación');
        if (!is_array($cliente) || !isset($cliente['id'])) {
            return $cliente;
        }

        return view('refinanciaciones/nueva', ['titulo' => 'Registrar propuesta', 'cliente' => $cliente, 'simulaciones' => $this->servicio->simulacionesVigentes((int) $cliente['id'])]);
    }

    /** CU-40 pasos 4 a 10 (A1 a A3, A5). */
    public function registrarPropuesta(int $simulacionId): RedirectResponse
    {
        try {
            $r = $this->servicio->registrarPropuesta((int) session('cuenta')['id'], $simulacionId);
        } catch (ReglaNegocioException $e) {
            if (str_starts_with((string) $e->regla, 'propuesta:')) {
                return redirect()->to(site_url('admin/refinanciaciones/' . substr((string) $e->regla, 10)))->with('error', $e->getMessage());
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url("admin/refinanciaciones/{$r['id']}"))
            ->with('exito', "Propuesta N.º {$r['numero']}" . ($r['version'] > 1 ? " (versión {$r['version']})" : '') . ' registrada, pendiente de resolución gerencial y vigente por 7 días corridos. No modifica las obligaciones ni suspende vencimientos o punitorios.');
    }

    /** CU-43 (A1 a A4). */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $p = $this->servicio->propuesta($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('refinanciaciones.localizar')->with('error', $e->getMessage());
        }

        return view('refinanciaciones/propuesta', ['titulo' => 'Propuesta N.º ' . $p['numero'], 'p' => $p]);
    }

    /** CU-41 pasos 3 a 6. */
    public function resolucion(int $id): string|RedirectResponse
    {
        return $this->formulario($id, 'refinanciaciones/resolver', 'Resolver propuesta');
    }

    /** CU-41 pasos 6 a 11 (A1 a A5). */
    public function resolver(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->resolver(
                (int) session('cuenta')['id'],
                $id,
                $this->enteroPost('version_fila'),
                $this->campoPost('decision'),
                $this->campoPost('fundamento'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/refinanciaciones/{$id}"))->with($r['ok'] ? 'exito' : 'error', $r['mensaje']);
    }

    /** CU-74 pasos 2 a 4 (A1, A2): plan definitivo con los punitorios posteriores al corte. */
    public function aceptacion(int $id): string|RedirectResponse
    {
        return $this->formulario($id, 'refinanciaciones/aplicar', 'Aplicar refinanciación', true);
    }

    /** CU-74 pasos 5 a 9 (A1, A2, A4). */
    public function aplicar(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->aplicar(
                (int) session('cuenta')['id'],
                $id,
                $this->enteroPost('version_fila'),
                $this->campoPost('fecha_constancia'),
                $this->campoPost('medio'),
                $this->campoPost('observaciones'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/refinanciaciones/{$id}"))->with($r['ok'] ? 'exito' : 'error', $r['mensaje']);
    }

    /** CU-96 pasos 2 a 4. */
    public function cancelacion(int $id): string|RedirectResponse
    {
        return $this->formulario($id, 'refinanciaciones/cancelar', 'Cancelar propuesta');
    }

    /** CU-96 pasos 5 a 7 (A1 a A3). */
    public function cancelar(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->cancelar(
                (int) session('cuenta')['id'],
                (string) session('cuenta')['rol'],
                $id,
                $this->enteroPost('version_fila'),
                $this->campoPost('motivo'),
                $this->campoPost('fecha_desistimiento'),
                $this->campoPost('medio_desistimiento'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/refinanciaciones/{$id}"))->with($r['ok'] ? 'exito' : 'error', $r['mensaje']);
    }

    private function formulario(int $id, string $vista, string $titulo, bool $definitivo = false): string|RedirectResponse
    {
        try {
            $p = $this->servicio->propuesta($id);
            $d = $definitivo ? $this->servicio->planDefinitivo($id) : null;
        } catch (ReglaNegocioException $e) {
            return redirect()->route('refinanciaciones.localizar')->with('error', $e->getMessage());
        }

        return view($vista, ['titulo' => $titulo, 'p' => $p, 'definitivo' => $d]);
    }
}
