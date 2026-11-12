<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Base de las pantallas del portal con sesión (filtro auth:portal): el cliente es siempre el de la sesión, nunca un
 * identificador recibido en la petición (RN-01, RN-08). Sin reglas de negocio en los controladores (RNF-05).
 */
abstract class BasePortal extends BaseController
{
    protected function clienteId(): int
    {
        return (int) (session('cuenta')['cliente_id'] ?? 0);
    }

    /** Vuelve al listado con el mensaje de la regla (el genérico si el objeto no es del cliente). */
    protected function volverA(string $ruta, ReglaNegocioException $e): RedirectResponse
    {
        return redirect()->route($ruta)->with('error', $e->getMessage());
    }

    protected function volverConErrores(ReglaNegocioException $e): RedirectResponse
    {
        return redirect()->back()->withInput()->with('error', $e instanceof ValidacionException ? 'Revise los datos marcados.' : $e->getMessage())
            ->with('errores', $e instanceof ValidacionException ? $e->errores : []);
    }
}
