<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Alertas\OperacionAlerta;
use App\Services\Comunes\AlertaService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-83 Consultar Alertas: listado con filtros, detalle (queda registrada como vista) e «Ir a la operación». */
class Alertas extends BaseController
{
    public function index(): string
    {
        $cuenta = session('cuenta');
        $filtros = $this->filtrosBusqueda(['estado', 'tipo', 'desde', 'hasta']);
        $filtros['estado'] ??= 'Pendiente';

        return view('alertas/index', [
            'titulo' => 'Alertas',
            'filtros' => $filtros,
            'alertas' => (new AlertaService())->listar((int) $cuenta['id'], (string) $cuenta['rol'], $filtros),
        ]);
    }

    public function ver(int $id): string|RedirectResponse
    {
        $cuenta = session('cuenta');
        try {
            $alerta = (new AlertaService())->verDetalle($id, (int) $cuenta['id'], (string) $cuenta['rol']);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('alertas.consultar')->with('error', $e->getMessage());
        }
        // A2: una alerta resuelta no redirige a la operación; A3: sin permiso, solo se muestra la información
        $operacion = $alerta['estado'] === 'Pendiente'
            ? OperacionAlerta::para($alerta['objeto_tipo'], $alerta['objeto_id'], (array) ($cuenta['permisos'] ?? []), $this->rutasPanel())
            : null;

        return view('alertas/ver', ['titulo' => 'Alerta', 'a' => $alerta, 'operacion' => $operacion]);
    }

    /** @return list<string> rutas GET del panel con (:num) en lugar de la expresión del identificador */
    private function rutasPanel(): array
    {
        return array_map(
            static fn (string $r): string => str_replace(['([0-9]{1,9})', '([0-9]+)', '(\d+)'], '(:num)', $r),
            array_keys(service('routes')->getRoutes('GET')),
        );
    }
}
