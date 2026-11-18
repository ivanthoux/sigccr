<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Reportes\AuditoriaConsultaService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-57 Consultar Registro de Auditoría (Administrador y Gerente; RN-45). Solo lectura. */
class Auditoria extends BaseController
{
    /** Pasos 2 a 4 (A1, A2). */
    public function index(): string|RedirectResponse
    {
        $f = [];
        foreach (['desde', 'hasta', 'responsable', 'categoria', 'objeto', 'resultado', 'pagina'] as $k) {
            $f[$k] = trim($this->campoGet($k));
        }
        try {
            $b = (new AuditoriaConsultaService())->buscar($f);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('auditoria.consultar')->with('error', $e->getMessage())->with('errores', $e instanceof ValidacionException ? $e->errores : []);
        }

        return view('auditoria/index', ['titulo' => 'Registro de auditoría', 'f' => $f, 'b' => $b]);
    }

    /** Pasos 5 y 6 (A4). */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $a = (new AuditoriaConsultaService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('auditoria.consultar')->with('error', $e->getMessage());
        }

        return view('auditoria/ver', ['titulo' => 'Registro de auditoría N.º ' . $id, 'a' => $a, 'volver' => $this->campoGet('volver')]);
    }
}
