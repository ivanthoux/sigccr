<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Clientes\ArchivoLegajo;
use App\Services\Solicitudes\ContratoService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-103 Registrar Contrato Firmado (RN-78, D-31). */
class Contratos extends BaseController
{
    private ContratoService $servicio;

    public function __construct()
    {
        $this->servicio = new ContratoService();
    }

    /** Paso 1: solicitudes aprobadas pendientes de liquidación y el estado de su constancia. */
    public function index(): string
    {
        $texto = trim((string) $this->request->getGet('texto'));

        return view('contratos/index', ['titulo' => 'Contratos de préstamo', 'texto' => $texto, 'solicitudes' => $this->servicio->pendientes($texto)]);
    }

    /** Pasos 2 y 3: contrato para imprimir y firmar, y formulario de la constancia. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('contratos.registrar-firmado')->with('error', $e->getMessage());
        }

        return view('contratos/contrato', ['titulo' => 'Contrato de la solicitud N.º ' . $s['numero'], 's' => $s]);
    }

    /** Pasos 4 a 6 (A1 a A3). */
    public function registrar(int $id): RedirectResponse
    {
        $f = $this->request->getFile('constancia');
        $archivo = null;
        if ($f instanceof UploadedFile && $f->getError() !== UPLOAD_ERR_NO_FILE) {
            $excedido = in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $archivo = [
                'nombre' => $f->getClientName(),
                'temporal' => $f->isValid() ? $f->getTempName() : '',
                'tamano' => $excedido ? ArchivoLegajo::TAMANO_MAXIMO + 1 : ($f->isValid() ? (int) $f->getSize() : 0),
            ];
        }
        try {
            $this->servicio->registrarConstancia($id, $archivo, (string) $this->request->getPost('fecha_recepcion'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/contratos/{$id}"))->with('exito', 'Constancia firmada incorporada al legajo: la solicitud ya puede liquidarse (CU-27). El plazo de revocación corre desde la recepción del contrato o el desembolso, lo que ocurra último (RN-79).');
    }
}
