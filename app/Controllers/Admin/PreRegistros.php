<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Clientes\PreRegistroService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pre-registros del portal (CU-104 paso 7): el Analista los revisa, verifica la identidad de la persona en la
 * sucursal y completa el alta con CU-11, o los descarta con su motivo.
 */
class PreRegistros extends BaseController
{
    public function index(): string
    {
        $estado = (string) ($this->request->getGet('estado') ?? 'PendienteValidacion');
        $texto = trim($this->campoGet('texto'));

        return view('clientes/pre_registros', [
            'titulo' => 'Pre-registros del portal', 'estado' => $estado, 'texto' => $texto,
            'pre' => (new PreRegistroService())->buscar($estado, $texto),
        ]);
    }

    public function ver(int $id): string|RedirectResponse
    {
        try {
            $p = (new PreRegistroService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('pre-registros.consultar')->with('error', $e->getMessage());
        }

        return view('clientes/pre_registro', ['titulo' => 'Pre-registro de ' . $p['apellido'] . ', ' . $p['nombre'], 'p' => $p]);
    }

    public function descartar(int $id): RedirectResponse
    {
        try {
            (new PreRegistroService())->descartar($id, (int) $this->request->getPost('version_fila'), $this->campoPost('motivo'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('pre-registros.consultar')->with('exito', 'Pre-registro descartado.');
    }
}
