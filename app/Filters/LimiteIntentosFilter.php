<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Comunes\AuditoriaService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Límite de 100 intentos de inicio de sesión por dirección de origen cada 15 minutos (RN-62).
 * El bloqueo por cuenta (5 intentos en 15 minutos) lo aplica el servicio de acceso sobre cuentas_acceso.
 */
class LimiteIntentosFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }
        $cfg = config('Sigccr');
        $clave = 'login_' . md5((string) $request->getIPAddress());
        // check(clave, capacidad, segundos): admite $intentosPorIp solicitudes por ventana
        if (service('throttler')->check($clave, $cfg->intentosPorIp, $cfg->ventanaIntentos) === false) {
            // CU-01 A4: rechazo genérico y registro en auditoría
            (new AuditoriaService())->registrar('acceso.limite_origen', 'ruta', (string) $request->getUri()->getPath(), 'Rechazado', 'Límite de intentos por dirección de origen (RN-62)', null, null, null, (string) $request->getIPAddress());

            return service('response')->setStatusCode(429)->setBody(view('errores/demasiados_intentos'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
