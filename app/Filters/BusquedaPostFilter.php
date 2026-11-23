<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Búsquedas por POST (D-64; ASVS V14.2.1; Ley 25.326, art. 9): los formularios que admiten un DNI, un CUIT o un CUIL
 * envían los criterios en el cuerpo de la petición, no en la dirección, que queda en el historial del navegador y en los
 * registros de acceso del servidor. La ruta POST usa la misma acción que la ruta GET del listado: este filtro copia los
 * criterios al lugar donde la acción los lee, de modo que ningún controlador cambia. Uso en rutas: 'busquedapost'.
 */
class BusquedaPostFilter implements FilterInterface
{
    /** Campos del formulario que no son criterios. */
    private const AJENOS = ['csrf_sigccr'];

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request instanceof IncomingRequest && $request->is('post')) {
            $request->setGlobal('get', array_diff_key((array) $request->getPost(), array_flip(self::AJENOS)));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
