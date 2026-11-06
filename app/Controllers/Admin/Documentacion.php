<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Comunes\DocumentacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-100 Configurar Catálogo de Documentación (D-25). */
class Documentacion extends BaseController
{
    /** Pasos 1 y 2: tipos de documento y documentación base por categoría. */
    public function index(): string
    {
        $s = new DocumentacionService();

        return view('documentacion/index', ['titulo' => 'Documentación exigible', 'tipos' => $s->tipos(), 'sugeridos' => $s->sugeridos()]);
    }

    /** Pasos 3 a 5 (A1). */
    public function registrarTipo(): RedirectResponse
    {
        try {
            (new DocumentacionService())->registrarTipo((array) $this->request->getPost());
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('abierto', 'nuevo');
        }

        return redirect()->route('documentacion.configurar')->with('exito', 'Tipo de documento registrado. Ya se puede exigir en las líneas de crédito y proponer para una categoría.');
    }

    /** Modificación o desactivación de un tipo (A2, A3). */
    public function modificarTipo(int $id): RedirectResponse
    {
        try {
            (new DocumentacionService())->modificarTipo($id, (array) $this->request->getPost());
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e)->with('abierto', "tipo-{$id}");
        }

        return redirect()->route('documentacion.configurar')->with('exito', 'Tipo de documento actualizado. Las líneas y los legajos ya registrados lo conservan.');
    }

    /** Pasos 6 a 8 (A4). */
    public function guardarSugeridos(): RedirectResponse
    {
        try {
            (new DocumentacionService())->guardarSugeridos((array) $this->request->getPost('sugeridos'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('documentacion.configurar')->with('exito', 'Documentación base actualizada. Se propone al registrar o modificar una línea; las versiones de condiciones vigentes no cambian.');
    }
}
