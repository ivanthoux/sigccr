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
        // H-15: un criterio enviado como lista vale vacío (antes, (string) de una lista era un error)
        $f = ['tipo' => $this->request->getGet('tipo') === null ? 'mes' : trim($this->campoGet('tipo')), 'fecha' => trim($this->campoGet('fecha')), 'desde' => trim($this->campoGet('desde')), 'hasta' => trim($this->campoGet('hasta'))];
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
