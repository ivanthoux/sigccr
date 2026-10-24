<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Modo de mantenimiento del portal de clientes (RNF-13, plan correctivo de R7). El panel sigue operando. */
class MantenimientoPortalFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (config('Sigccr')->portalEnMantenimiento) {
            return service('response')->setStatusCode(503)->setHeader('Retry-After', '3600')->setBody(view('errores/portal_mantenimiento'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
