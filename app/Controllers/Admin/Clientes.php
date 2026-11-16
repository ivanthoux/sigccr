<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Adapters\Fabrica;
use App\Adapters\ServicioNoDisponibleException;
use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Validacion\Campos;
use App\Services\Clientes\ClienteService;
use App\Services\Clientes\PreRegistroService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\DocumentacionService;
use App\Services\Comunes\GeografiaService;
use App\Services\Comunes\MonotributoService;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

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
            'clientes' => $buscado ? $this->servicio->buscar($criterios) : $this->servicio->recientes(),
        ]);
    }

    /** CU-11 pasos 1 y 2. */
    public function nuevo(): string|RedirectResponse
    {
        // CU-104 paso 7: el alta puede partir de un pre-registro verificado, con sus datos propuestos
        $pre = null;
        $preId = (string) $this->request->getGet('pre');
        if (ctype_digit($preId)) {
            try {
                $pre = (new PreRegistroService())->datosParaAlta((int) $preId);
            } catch (ReglaNegocioException $e) {
                return redirect()->route('pre-registros.consultar')->with('error', $e->getMessage());
            }
        }

        return view('clientes/formulario', ['titulo' => 'Registrar cliente', 'c' => null, 'pre' => $pre, 'tipos' => $this->servicio->tiposDocumentacion()] + $this->geografia(null));
    }

    /**
     * CU-11 A9: datos del padrón de ARCA para el CUIL ingresado, que el Analista confirma o corrige. Si el servicio no
     * responde, se informa y los datos se cargan a mano (ACT-10, RNF-08).
     */
    public function padron(): ResponseInterface
    {
        $cuil = str_replace(['-', ' ', '.'], '', (string) $this->request->getGet('cuil'));
        $error = Campos::obligatorio($cuil, 'el CUIL') ?? Campos::cuit($cuil, 'PersonaHumana');
        if ($error !== null) {
            return $this->response->setStatusCode(422)->setJSON(['mensaje' => $error]);
        }
        try {
            $datos = Fabrica::padron()->consultar($cuil);
        } catch (ServicioNoDisponibleException) {
            (new AuditoriaService())->registrar('clientes.consultar-padron', 'clientes', null, 'Fallido', 'El padrón de ARCA no respondió');

            return $this->response->setStatusCode(503)->setJSON(['mensaje' => 'El padrón de ARCA no responde: complete los datos manualmente.']);
        }
        (new AuditoriaService())->registrar('clientes.consultar-padron', 'clientes', null, $datos === null ? 'Sin datos' : 'Exitoso', null, ['cuil' => $cuil]);
        if ($datos === null) {
            return $this->response->setStatusCode(404)->setJSON(['mensaje' => 'El padrón no tiene datos para ese CUIL: complete los datos manualmente.']);
        }

        return $this->response->setJSON(['datos' => $datos, 'mensaje' => 'Datos propuestos por el padrón de ARCA: confírmelos o corríjalos antes de registrar.']);
    }

    /** CU-11 pasos 3 a 9. */
    public function registrar(): RedirectResponse
    {
        try {
            $id = $this->servicio->registrar((array) $this->request->getPost(), $this->archivosLegajo(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/clientes/{$id}"))->with('exito', 'Cliente registrado. Enviamos un enlace a su correo: al abrirlo, el cliente lo verifica y define su contraseña del portal.');
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

        return view('clientes/ver', ['titulo' => $cliente['denominacion'], 'c' => $cliente, 'tipos' => $this->servicio->tiposDocumentacion(), 'sugeridos' => (new DocumentacionService())->sugeridos()[$cliente['categoria_ingresos']] ?? []]);
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

        return view('clientes/formulario', ['titulo' => 'Modificar cliente', 'c' => $cliente, 'tipos' => []] + $this->geografia($cliente));
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
            $n = $this->servicio->agregarDocumentos($id, $this->archivosLegajo(), (int) session('cuenta')['id']);
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

        $r = $this->response->download($d['ruta'], null)->setFileName($d['nombre'])->setContentType($d['mime']);

        // ?ver=1 abre el PDF o la imagen en el navegador, por ejemplo desde la resolución gerencial (D-23)
        return $this->request->getGet('ver') === '1' ? $r->inline() : $r;
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
     * D-18: localidades activas de una provincia para el formulario del cliente (se cargan al elegir la provincia).
     */
    public function localidades(int $provinciaId): ResponseInterface
    {
        return $this->response->setJSON((new GeografiaService())->localidades($provinciaId));
    }

    /**
     * Provincias y, si ya hay una elegida, sus localidades, para mostrar el formulario sin esperar la carga.
     *
     * @param array<string, mixed>|null $cliente
     * @return array{provincias: list<array{id: int, nombre: string}>, localidades: list<array{id: int, nombre: string}>}
     */
    private function geografia(?array $cliente): array
    {
        $g = new GeografiaService();
        $provincia = (int) (old('provincia_id') ?? $cliente['provincia_id'] ?? 0);

        return ['provincias' => $g->provincias(), 'localidades' => $provincia === 0 ? [] : $g->localidades($provincia), 'escalaMonotributo' => (new MonotributoService())->vigentes()];
    }
}
