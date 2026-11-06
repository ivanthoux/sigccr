<?php

namespace App\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\ArchivoLegajo;
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
     * Vuelve al formulario con los datos ingresados, el mensaje general y, si corresponde, el error de cada campo
     * (session('errores')), que las vistas marcan con campo_invalido() y error_campo().
     */
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
