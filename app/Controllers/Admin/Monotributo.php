<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Comunes\MonotributoService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-101 Actualizar Categorías del Monotributo (D-26). */
class Monotributo extends BaseController
{
    /** Pasos 1 y 2: escala vigente, próximas y anteriores. */
    public function index(): string
    {
        $s = new MonotributoService();

        return view('monotributo/index', ['titulo' => 'Categorías del monotributo', 'vigentes' => $s->vigentes(), 'historial' => $s->historial()]);
    }

    /** Pasos 3 a 6 (A1, A2). */
    public function registrar(): RedirectResponse
    {
        try {
            $desde = (new MonotributoService())->registrarEscala((string) $this->request->getPost('vigente_desde'), (array) $this->request->getPost('topes'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('monotributo.actualizar')->with('exito', 'Escala registrada: rige desde el ' . formato_fecha($desde) . '. Los clientes se validan con la escala vigente al registrar o modificar sus datos.');
    }
}
