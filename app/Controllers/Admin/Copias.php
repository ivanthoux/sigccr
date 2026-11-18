<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Mantenimiento\CopiaService;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-61 Generar, CU-62 Descargar y CU-63 Restaurar Copia de Seguridad (Administrador; RNF-13, D-45). El panel solicita
 * la copia o la restauración; las ejecuta el contenedor de procesos (copias:procesar, cada minuto).
 */
class Copias extends BaseController
{
    /** CU-61 paso 2 y CU-62 paso 2: destino, copias y última restauración. */
    public function index(): string
    {
        $s = new CopiaService();

        return view('copias/index', ['titulo' => 'Copias de seguridad', 'copias' => $s->listar(), 'destino' => $s->destino(), 'ultima' => $s->ultimaRestauracion()]);
    }

    /** CU-61 pasos 3 a 8 (A1, A2; A4 con el aviso de confirmación del navegador). */
    public function generar(): RedirectResponse
    {
        $cuenta = session('cuenta');
        try {
            $id = (new CopiaService())->solicitar((int) $cuenta['id'], (string) $cuenta['nombre'], $this->campoPost('descripcion'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(url_to('copias.descargar', $id))->with('exito', 'Copia solicitada: el contenedor de procesos la genera en el próximo minuto. Esta pantalla se actualiza hasta que quede verificada.');
    }

    /** CU-62 pasos 3 a 5 y 8: datos de la copia y su código de verificación. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $c = (new CopiaService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('copias.generar')->with('error', $e->getMessage());
        }

        return view('copias/ver', ['titulo' => 'Copia de seguridad N.º ' . $id, 'c' => $c]);
    }

    /** CU-62 pasos 6 a 9 (A1). */
    public function descargar(int $id): DownloadResponse|RedirectResponse
    {
        try {
            $a = (new CopiaService())->paraDescargar($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->to(url_to('copias.descargar', $id))->with('error', $e->getMessage());
        }

        return $this->response->download($a['ruta'], null)->setFileName($a['nombre'])->setContentType('application/octet-stream');
    }

    /** CU-63 pasos 3 a 5: advertencia y confirmación explícita. */
    public function confirmarRestauracion(int $id): string|RedirectResponse
    {
        try {
            $c = (new CopiaService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('copias.generar')->with('error', $e->getMessage());
        }
        if (!$c['disponible']) {
            return redirect()->to(url_to('copias.descargar', $id))->with('error', 'La copia está corrupta, incompleta o no disponible: seleccione otra copia (A1).');
        }

        return view('copias/restaurar', ['titulo' => 'Restaurar la copia N.º ' . $id, 'c' => $c]);
    }

    /** CU-63 paso 6 (A2): deja pedida la restauración y muestra su estado. */
    public function restaurar(int $id): RedirectResponse
    {
        $cuenta = session('cuenta');
        try {
            (new CopiaService())->solicitarRestauracion($id, $this->campoPost('confirmacion'), $this->request->getPost('sin_respaldo') === '1', (int) $cuenta['id'], (string) $cuenta['nombre']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('mantenimiento');
    }
}
