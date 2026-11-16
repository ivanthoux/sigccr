<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Reportes\ReporteService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-55 Consultar Tablero Gerencial (Gerente; RN-47, RN-74). */
class Tablero extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $f = ['tipo' => (string) ($this->request->getGet('tipo') ?? 'mes'), 'fecha' => trim((string) $this->request->getGet('fecha')), 'desde' => trim((string) $this->request->getGet('desde')), 'hasta' => trim((string) $this->request->getGet('hasta'))];
        try {
            $t = (new ReporteService())->tablero($f);
        } catch (ReglaNegocioException $e) {
            if ($this->request->getGet('tipo') === null) {
                throw $e;
            }

            return redirect()->route('tablero.consultar')->with('error', $e->getMessage())->with('errores', $e instanceof ValidacionException ? $e->errores : []);
        }

        return view('reportes/tablero', ['titulo' => 'Tablero gerencial', 'f' => $f, 't' => $t]);
    }
}
