<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Reportes\Exportacion;
use App\Libraries\Reportes\Xlsx;
use App\Services\Reportes\ReporteService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CU-65 a CU-71 Reportes de gestión (Gerente; RN-47, RN-74; D-44). El formulario de criterios y el reporte comparten
 * pantalla: con generar=1 se genera (pasos 3 a 9). La impresión y el PDF usan la vista de impresión del navegador; la
 * exportación a Excel descarga un .xlsx (paso 8).
 */
class Reportes extends BaseController
{
    /** Criterios del formulario (paso 2). */
    private const CRITERIOS = ['tipo', 'fecha', 'desde', 'hasta', 'linea', 'estado', 'riesgo', 'antiguedad', 'cliente', 'analista', 'caja', 'cajero', 'medio', 'canal', 'categoria', 'campana'];

    /** Índice de los reportes que el rol puede generar. */
    public function index(): string
    {
        $disponibles = array_filter(ReporteService::REPORTES, static fn ($r) => tiene_permiso($r[2]));

        return view('reportes/index', ['titulo' => 'Reportes de gestión', 'reportes' => $disponibles]);
    }

    public function ver(string $reporte): string|RedirectResponse|ResponseInterface
    {
        if (!isset(ReporteService::REPORTES[$reporte])) {
            return redirect()->route('reportes.index')->with('error', 'El reporte no existe.');
        }
        $f = array_map(static fn ($v) => is_string($v) ? trim($v) : '', array_intersect_key((array) $this->request->getGet(), array_flip(self::CRITERIOS)));
        $s = new ReporteService();
        $datos = ['titulo' => ReporteService::REPORTES[$reporte][1], 'clave' => $reporte, 'f' => $f, 'opciones' => $s->opciones(), 'r' => null];
        if ($this->request->getGet('generar') === null) {
            return view('reportes/reporte', $datos);
        }
        $formato = $this->request->getGet('formato') === 'xlsx' ? 'xlsx' : 'pantalla';
        try {
            $r = $s->generar($reporte, $f, $formato);
        } catch (ReglaNegocioException $e) {
            // A2: criterios inválidos; se vuelve al formulario con los valores ingresados
            return redirect()->to(url_to(ReporteService::REPORTES[$reporte][2]) . '?' . http_build_query($f))->with('error', $e->getMessage())
                ->with('errores', $e instanceof ValidacionException ? $e->errores : []);
        }
        if ($formato === 'xlsx') {
            $nombre = 'reporte_' . $reporte . '_' . str_replace('-', '', $r['periodo']['desde']) . '_' . str_replace('-', '', $r['periodo']['hasta']) . '.xlsx';

            return $this->response->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $nombre . '"')
                ->setBody(Xlsx::generar(Exportacion::hojas($r, config('Sigccr')->zonaNegocio)));
        }

        return view('reportes/reporte', ['r' => $r] + $datos);
    }
}
