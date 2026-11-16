<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Copias\EstadoRestauracion;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CU-63 paso 8: mientras se restaura una copia, el panel y el portal quedan en modo mantenimiento (D-45). Solo
 * responde la página del estado de la restauración, que no usa la base de datos.
 */
class RestauracionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!EstadoRestauracion::predeterminado()->activa()) {
            return null;
        }

        return service('response')->setStatusCode(503)->setHeader('Retry-After', '60')->setBody(view('errores/restauracion', ['estado' => EstadoRestauracion::predeterminado()->actual()]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
