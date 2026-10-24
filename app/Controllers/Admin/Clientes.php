<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Clientes\ArchivoLegajo;
use App\Services\Clientes\ClienteService;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Padrón de clientes: CU-54 Localizar, CU-11 Registrar, CU-12 Modificar, CU-13 Desactivar, CU-14 Reactivar y
 * CU-15 Consultar perfil e historial crediticio. Cada acción llama a una operación de ClienteService.
 */
class Clientes extends BaseController
{
    private ClienteService $servicio;

    public function __construct()
    {
        $this->servicio = new ClienteService();
    }

    /** CU-54. */
    public function index(): string
    {
        $criterios = array_map('strval', array_intersect_key((array) $this->request->getGet(), array_flip(['texto', 'documento', 'cuit', 'tipo', 'estado'])));
        $buscado = array_filter($criterios, static fn ($v) => $v !== '') !== [];

        return view('clientes/index', [
            'titulo' => 'Clientes',
            'criterios' => $criterios,
            'buscado' => $buscado,
            'clientes' => $buscado ? $this->servicio->buscar($criterios) : [],
        ]);
    }

    /** CU-11 pasos 1 y 2. */
    public function nuevo(): string
    {
        return view('clientes/formulario', ['titulo' => 'Registrar cliente', 'c' => null, 'tipos' => $this->servicio->tiposDocumentacion()]);
    }

    /** CU-11 pasos 3 a 9. */
    public function registrar(): RedirectResponse
    {
        try {
            $id = $this->servicio->registrar((array) $this->request->getPost(), $this->archivos(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/clientes/{$id}"))->with('exito', 'Cliente registrado. Enviamos un enlace a su correo para verificarlo; hasta entonces no puede activar el portal.');
    }

    /** CU-15 pasos 1 a 3. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $cliente = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('clientes.localizar')->with('error', $e->getMessage());
        }
        $this->servicio->registrarConsulta($id, 'perfil');

        return view('clientes/ver', ['titulo' => $cliente['denominacion'], 'c' => $cliente, 'tipos' => $this->servicio->tiposDocumentacion()]);
    }

    /** CU-15 pasos 4 a 7. */
    public function historial(int $id): string|RedirectResponse
    {
        try {
            $cliente = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('clientes.localizar')->with('error', $e->getMessage());
        }
        $this->servicio->registrarConsulta($id, 'historial');

        return view('clientes/historial', ['titulo' => 'Historial de ' . $cliente['denominacion'], 'c' => $cliente, 'h' => $this->servicio->historial($id)]);
    }

    /** CU-12 pasos 1 a 3. */
    public function editar(int $id): string|RedirectResponse
    {
        try {
            $cliente = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('clientes.localizar')->with('error', $e->getMessage());
        }

        return view('clientes/formulario', ['titulo' => 'Modificar cliente', 'c' => $cliente, 'tipos' => []]);
    }

    /** CU-12 pasos 4 a 10. */
    public function actualizar(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->modificar($id, (int) $this->request->getPost('version_fila'), (array) $this->request->getPost(), (string) $this->request->getPost('evidencia_identidad'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = 'Datos actualizados.';
        if ($r['verificacion']) {
            $mensaje .= ' Enviamos un enlace al correo nuevo para verificarlo; hasta entonces sigue vigente el anterior.';
        }
        if ($r['solicitudes_afectadas'] > 0) {
            $mensaje .= " El cambio afecta {$r['solicitudes_afectadas']} solicitud(es) en curso: su evaluación quedó invalidada y se alertó a los responsables (RN-12).";
        }

        return redirect()->to(site_url("admin/clientes/{$id}"))->with('exito', $mensaje);
    }

    /** Documentación adicional del legajo. */
    public function agregarDocumentos(int $id): RedirectResponse
    {
        try {
            $n = $this->servicio->agregarDocumentos($id, $this->archivos(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/clientes/{$id}") . '#legajo')->with('exito', $n === 1 ? 'Documento incorporado al legajo.' : "{$n} documentos incorporados al legajo.");
    }

    public function descargarDocumento(int $id, int $documentoId): DownloadResponse|RedirectResponse
    {
        try {
            $d = $this->servicio->documento($id, $documentoId);
        } catch (ReglaNegocioException $e) {
            return redirect()->to(site_url("admin/clientes/{$id}"))->with('error', $e->getMessage());
        }

        return $this->response->download($d['ruta'], null)->setFileName($d['nombre'])->setContentType($d['mime']);
    }

    /** CU-13 y CU-14: confirmación con motivo. */
    public function confirmar(int $id, string $operacion): string|RedirectResponse
    {
        try {
            $cliente = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('clientes.localizar')->with('error', $e->getMessage());
        }

        return view('clientes/operacion', ['titulo' => $cliente['denominacion'], 'c' => $cliente, 'operacion' => $operacion]);
    }

    /** CU-13 pasos 5 a 7. */
    public function desactivar(int $id): RedirectResponse
    {
        try {
            $this->servicio->desactivar($id, (int) $this->request->getPost('version_fila'), (string) $this->request->getPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/clientes/{$id}"))->with('exito', 'Cliente desactivado. Su acceso al portal quedó desactivado y no puede iniciar nuevas operaciones.');
    }

    /** CU-14 pasos 5 a 7. */
    public function reactivar(int $id): RedirectResponse
    {
        try {
            $verificado = $this->servicio->reactivar($id, (int) $this->request->getPost('version_fila'), (string) $this->request->getPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = 'Cliente reactivado. Para volver a usar el portal debe activar o rehabilitar su acceso (CU-04).';
        if (!$verificado) {
            $mensaje .= ' Su correo no está verificado: actualícelo con "Modificar datos" antes de que active el acceso.';
        }

        return redirect()->to(site_url("admin/clientes/{$id}"))->with('exito', $mensaje);
    }

    /**
     * Archivos de documentos[<código>][] para el servicio. Los rechazados por PHP (por tamaño) se informan como
     * excedidos para que el servicio marque el campo.
     *
     * @return list<array{codigo: string, nombre: string, temporal: string, tamano: int}>
     */
    private function archivos(): array
    {
        $resultado = [];
        foreach ((array) ($this->request->getFiles()['documentos'] ?? []) as $codigo => $lista) {
            foreach (is_array($lista) ? $lista : [$lista] as $f) {
                if (!$f instanceof UploadedFile || $f->getError() === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $excedido = in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                $resultado[] = [
                    'codigo' => (string) $codigo,
                    'nombre' => $f->getClientName(),
                    'temporal' => $f->isValid() ? $f->getTempName() : '',
                    'tamano' => $excedido ? ArchivoLegajo::TAMANO_MAXIMO + 1 : ($f->isValid() ? (int) $f->getSize() : 0),
                ];
            }
        }

        return $resultado;
    }
}
