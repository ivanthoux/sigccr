<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Comunes\AuditoriaService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autorización por permiso en el servidor (RN-01, RNF-01). Uso en rutas: 'permiso:cobros.registrar'.
 * Se ejecuta después de 'auth'. Los permisos del rol se cargan en la sesión al iniciarla y se recargan al cambiar
 * el rol (la version_seguridad invalida la sesión anterior). El rechazo es genérico y se audita.
 */
class PermisoFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requeridos = (array) ($arguments ?? []);
        $permisos = session('cuenta')['permisos'] ?? [];
        foreach ($requeridos as $p) {
            if (!in_array($p, $permisos, true)) {
                (new AuditoriaService())->registrar('acceso.denegado', 'ruta', (string) $request->getUri()->getPath(), 'Rechazado', "Permiso requerido: {$p}");
                if ($request instanceof IncomingRequest && $request->isAJAX()) {
                    return service('response')->setStatusCode(403)->setJSON(['mensaje' => 'No tiene permiso para realizar esta operación.']);
                }

                return service('response')->setStatusCode(403)->setBody(view('errores/acceso_denegado'));
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
