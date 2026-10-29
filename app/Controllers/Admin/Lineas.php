<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Lineas\LineaService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Líneas de crédito: CU-20 Localizar, CU-73 Consultar, CU-16 Registrar, CU-17 Modificar, CU-18 Suspender y CU-19
 * Reactivar. Cada acción llama a una operación de LineaService.
 */
class Lineas extends BaseController
{
    private LineaService $servicio;

    public function __construct()
    {
        $this->servicio = new LineaService();
    }

    /** CU-20: sin criterios se listan todas las líneas (el catálogo es chico); los filtros acotan el listado. */
    public function index(): string
    {
        $criterios = array_map('strval', array_intersect_key((array) $this->request->getGet(), array_flip(['texto', 'estado', 'categoria'])));

        return view('lineas/index', [
            'titulo' => 'Líneas de crédito',
            'criterios' => $criterios,
            'lineas' => $this->servicio->buscar($criterios),
            'categorias' => LineaService::categorias(),
        ]);
    }

    /** CU-73. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $linea = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('lineas.localizar')->with('error', $e->getMessage());
        }
        $this->servicio->registrarConsulta($id);

        return view('lineas/ver', ['titulo' => $linea['nombre'], 'l' => $linea, 'categorias' => LineaService::categorias()]);
    }

    /** CU-16 pasos 1 y 2. */
    public function nuevo(): string
    {
        return view('lineas/formulario', ['titulo' => 'Registrar línea de crédito', 'l' => null, 'tipos' => $this->servicio->tiposDocumentacion()]);
    }

    /** CU-16 pasos 3 a 6. */
    public function registrar(): RedirectResponse
    {
        try {
            $id = $this->servicio->registrar((array) $this->request->getPost());
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/lineas/{$id}"))->with('exito', 'Línea registrada con su versión 1 de condiciones. Ya está disponible para simulaciones y solicitudes dentro de su período de vigencia.');
    }

    /** CU-17 pasos 1 a 3. */
    public function editar(int $id): string|RedirectResponse
    {
        try {
            $linea = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('lineas.localizar')->with('error', $e->getMessage());
        }

        return view('lineas/formulario', ['titulo' => 'Modificar línea de crédito', 'l' => $linea, 'tipos' => $this->servicio->tiposDocumentacion()]);
    }

    /** CU-17 pasos 4 a 9. */
    public function actualizar(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->modificar(
                $id,
                (int) $this->request->getPost('version_fila'),
                (array) $this->request->getPost(),
                (string) $this->request->getPost('fecha_vigencia'),
                (string) $this->request->getPost('motivo'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = "Se registró la versión {$r['version']} de condiciones, vigente desde el " . formato_fecha_hora($r['vigente_desde']) . '.';
        if ($r['operaciones_anteriores'] > 0) {
            $mensaje .= " {$r['operaciones_anteriores']} operación(es) en curso conservan la versión anterior (RN-15).";
        }

        return redirect()->to(site_url("admin/lineas/{$id}"))->with('exito', $mensaje);
    }

    /** CU-18 y CU-19: confirmación con motivo. */
    public function confirmar(int $id, string $operacion): string|RedirectResponse
    {
        try {
            $linea = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('lineas.localizar')->with('error', $e->getMessage());
        }
        $esperado = $operacion === 'suspender' ? 'Activa' : 'Suspendida';
        if ($linea['estado'] !== $esperado) {
            $mensaje = match (true) {
                $linea['estado'] === 'Vencida' => 'La línea superó su período de vigencia: no admite nuevas operaciones, por eso la operación no corresponde.',
                $operacion === 'suspender' => 'La línea ya está suspendida.',
                default => 'La línea no está suspendida.',
            };

            return redirect()->to(site_url("admin/lineas/{$id}"))->with('error', $mensaje);
        }

        return view('lineas/operacion', ['titulo' => $linea['nombre'], 'l' => $linea, 'operacion' => $operacion]);
    }

    /** CU-18 pasos 5 a 7. */
    public function suspender(int $id): RedirectResponse
    {
        try {
            $continuan = $this->servicio->suspender($id, (int) $this->request->getPost('version_fila'), (string) $this->request->getPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = 'Línea suspendida: no admite nuevas simulaciones, ofertas ni solicitudes independientes.';
        if ($continuan > 0) {
            $mensaje .= " {$continuan} solicitud(es) u oferta(s) previas continúan con su versión de condiciones (RN-16).";
        }

        return redirect()->to(site_url("admin/lineas/{$id}"))->with('exito', $mensaje);
    }

    /** CU-19 pasos 5 a 8. */
    public function reactivar(int $id): RedirectResponse
    {
        try {
            $this->servicio->reactivar($id, (int) $this->request->getPost('version_fila'), (string) $this->request->getPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/lineas/{$id}"))->with('exito', 'Línea reactivada con la versión de condiciones vigente.');
    }
}
