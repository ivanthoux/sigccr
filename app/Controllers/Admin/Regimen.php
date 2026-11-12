<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Cartera\RegimenService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/** CU-105 Generar Régimen Informativo de Deudores: consulta, descarga, generación y rectificación del Gerente. */
class Regimen extends BaseController
{
    public function index(): string
    {
        return view('regimen/index', ['titulo' => 'Régimen informativo de deudores', 'regimenes' => (new RegimenService())->listar()]);
    }

    public function ver(int $id): string|RedirectResponse
    {
        try {
            $r = (new RegimenService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('regimen.consultar')->with('error', $e->getMessage());
        }

        return view('regimen/ver', ['titulo' => 'Régimen informativo ' . $r['periodo'], 'r' => $r]);
    }

    /** Generación de un período cerrado o rectificación (A1). */
    public function generar(): RedirectResponse
    {
        try {
            $r = (new RegimenService())->generar((string) $this->request->getPost('periodo'), (int) session('cuenta')['id'], (string) $this->request->getPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(url_to('regimen.ver', $r['id']))->with('exito', $r['version'] > 1 ? "Rectificación registrada (versión {$r['version']}): la versión anterior quedó como antecedente." : "Régimen generado con {$r['deudores']} deudores.");
    }

    /** Paso 6: descarga del archivo. */
    public function descargar(int $id): ResponseInterface|RedirectResponse
    {
        try {
            $a = (new RegimenService())->archivo($id, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('regimen.consultar')->with('error', $e->getMessage());
        }

        return $this->response->setContentType('text/plain; charset=UTF-8')->setHeader('Content-Disposition', 'attachment; filename="' . $a['nombre'] . '"')->setBody($a['contenido']);
    }
}
