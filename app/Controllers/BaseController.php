<?php

namespace App\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\ArchivoLegajo;
use App\Libraries\Validacion\Campos;
use App\Services\Acceso\CambioContrasenaService;
use App\Services\Clientes\ClienteService;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Búsqueda y selección del cliente (CU-54) para las operaciones que parten de él: sin ?cliente= presenta la
     * búsqueda; con él, devuelve los datos del cliente o vuelve a la búsqueda si no existe.
     *
     * @return array<string, mixed>|string|RedirectResponse
     */
    protected function clienteSeleccionado(string $destino, string $titulo): array|string|RedirectResponse
    {
        $clienteId = $this->enteroGet('cliente');
        if ($clienteId === 0) {
            return $this->busquedaCliente($destino, $titulo);
        }
        try {
            return (new SimulacionService())->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route($destino)->with('error', $e->getMessage());
        }
    }

    /**
     * Pantalla única de búsqueda del cliente (CU-54) con la que empiezan las operaciones sobre un cliente: el mismo
     * campo, la misma tabla de resultados y la misma forma de elegirlo en todas. $soloActivos deja fuera a los
     * clientes que no pueden iniciar un crédito (simulación y solicitud); $datos agrega lo propio de la pantalla.
     *
     * @param array<string, mixed> $datos
     */
    protected function busquedaCliente(string $destino, string $titulo, bool $soloActivos = false, array $datos = []): string
    {
        $texto = trim($this->campoGet('texto'));
        $clientes = $texto === '' ? [] : (new ClienteService())->buscar(['texto' => $texto]);
        if ($soloActivos) {
            $clientes = array_values(array_filter($clientes, static fn ($c) => $c['estado'] === 'Activo'));
        }

        return view('simulaciones/cliente', ['titulo' => $titulo, 'texto' => $texto, 'destino' => $destino, 'clientes' => $clientes] + $datos);
    }

    /**
     * Vuelve al formulario con los datos ingresados, el mensaje general y, si corresponde, el error de cada campo
     * (session('errores')), que las vistas marcan con campo_invalido() y error_campo().
     */
    /**
     * CU-107 (panel y portal): cambia la contraseña de la cuenta con sesión. La sesión toma la versión de seguridad nueva
     * y un identificador nuevo (ASVS V7.2.4); las demás sesiones de la cuenta se cierran.
     */
    protected function cambiarContrasena(string $rutaExito, string $mensajeExito): RedirectResponse
    {
        $cuenta = session('cuenta');
        try {
            $version = (new CambioContrasenaService())->cambiar((int) $cuenta['id'], $this->campoPost('actual'), $this->campoPost('contrasena'), $this->campoPost('confirmacion'));
        } catch (ReglaNegocioException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        $cuenta['version_seguridad'] = $version;
        session()->set('cuenta', $cuenta);
        session()->regenerate(true);

        return redirect()->route($rutaExito)->with('exito', $mensajeExito);
    }

    /**
     * Campo del formulario (POST) como texto, sin transformar. Un campo ausente o enviado como lista (campo[]=x) se
     * trata como vacío, para que la validación responda con el mensaje del campo y no con un error del servidor
     * (D-51, clase COM-02).
     */
    protected function campoPost(string $campo): string
    {
        return Campos::crudo($this->request->getPost($campo));
    }

    /**
     * Identificador o versión de fila (POST) como entero. Solo dígitos: ausente, vacío, con otros caracteres o enviado
     * como lista vale 0, que no corresponde a ningún registro ni a ninguna versión (H-21). Un (int) directo convertía
     * una lista en 1 y «1abc» en 1, y podía elegir otro registro o pasar el control de versión (RN-24).
     */
    protected function enteroPost(string $campo): int
    {
        return Campos::identificador($this->request->getPost($campo));
    }

    /** Identificador de la dirección (GET) como entero, con la regla de enteroPost() (H-21). */
    protected function enteroGet(string $campo): int
    {
        return Campos::identificador($this->request->getGet($campo));
    }

    /** Parámetro de la dirección (GET) como texto; ausente o enviado como lista, vacío (D-51, clase FIL-02). */
    protected function campoGet(string $campo): string
    {
        return Campos::crudo($this->request->getGet($campo));
    }

    /**
     * Filtros de una búsqueda (GET): solo los parámetros admitidos que llegaron, como texto. Uno enviado como lista se
     * trata como vacío (D-51, clase FIL-02).
     *
     * @param list<string> $admitidos
     * @return array<string, string>
     */
    protected function filtrosBusqueda(array $admitidos): array
    {
        $filtros = [];
        foreach (array_intersect_key((array) $this->request->getGet(), array_flip($admitidos)) as $clave => $valor) {
            $filtros[(string) $clave] = Campos::crudo($valor);
        }

        return $filtros;
    }

    protected function volverConError(ReglaNegocioException $e): RedirectResponse
    {
        return redirect()->back()->withInput()
            ->with('error', $e->getMessage())
            ->with('errores', $e instanceof ValidacionException ? $e->errores : []);
    }

    /**
     * Archivos del formulario documentos[<código>][] para el legajo (RN-11, RNF-12). Los rechazados por PHP por su
     * tamaño se informan como excedidos para que el servicio marque el campo. reemplaza[<código>] indica el documento
     * observado que el archivo reemplaza (RN-44, CU-84).
     *
     * @return list<array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza: ?int}>
     */
    protected function archivosLegajo(): array
    {
        $reemplazos = (array) $this->request->getPost('reemplaza');
        $resultado = [];
        foreach ((array) ($this->request->getFiles()['documentos'] ?? []) as $codigo => $lista) {
            foreach (is_array($lista) ? $lista : [$lista] as $f) {
                if (!$f instanceof UploadedFile || $f->getError() === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $excedido = in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                $reemplaza = $reemplazos[$codigo] ?? '';
                $resultado[] = [
                    'codigo' => (string) $codigo,
                    'nombre' => $f->getClientName(),
                    'temporal' => $f->isValid() ? $f->getTempName() : '',
                    'tamano' => $excedido ? ArchivoLegajo::TAMANO_MAXIMO + 1 : ($f->isValid() ? (int) $f->getSize() : 0),
                    'reemplaza' => is_string($reemplaza) && ctype_digit($reemplaza) ? (int) $reemplaza : null,
                ];
            }
        }

        return $resultado;
    }
}
