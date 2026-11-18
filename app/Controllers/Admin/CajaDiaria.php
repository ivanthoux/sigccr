<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Reportes\ReporteService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-56 Consultar Informe de Caja Diaria (Gerente y Cajero; RN-47). El Cajero solo ve sus jornadas (A2). */
class CajaDiaria extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $f = ['desde' => trim($this->campoGet('desde')), 'hasta' => trim($this->campoGet('hasta')), 'caja' => $this->campoGet('caja'), 'cajero' => $this->campoGet('cajero')];
        $cuenta = session('cuenta');
        $soloCajero = ($cuenta['rol'] ?? '') === 'Cajero' ? (int) $cuenta['id'] : null;
        $s = new ReporteService();
        try {
            $i = $s->cajaDiaria($f, $soloCajero);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('cajas.informe-diario')->with('error', $e->getMessage())->with('errores', $e instanceof ValidacionException ? $e->errores : []);
        }

        return view('reportes/caja_diaria', ['titulo' => 'Informe de caja diaria', 'f' => $f, 'i' => $i, 'opciones' => $s->opciones(), 'soloCajero' => $soloCajero !== null]);
    }
}
