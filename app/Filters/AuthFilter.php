<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

/**
 * Sesión autenticada (RN-02, RNF-01, RNF-11). Uso en rutas: 'auth:panel' o 'auth:portal'.
 *
 * - Exige una cuenta del tipo correspondiente (usuario interno para el panel, cliente para el portal, RN-08).
 * - Aplica la inactividad (30 min en el panel, 15 en el portal) y la duración máxima de 12 horas.
 * - Compara version_seguridad de la sesión con la de la cuenta: desactivar un usuario, cambiar su rol o restablecer
 *   sus credenciales la incrementa e invalida todas sus sesiones abiertas (RN-04, RN-06).
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $ambito = ($arguments[0] ?? 'panel') === 'portal' ? 'portal' : 'panel';
        $tipoEsperado = $ambito === 'portal' ? 'Cliente' : 'UsuarioInterno';
        $cfg = config('Sigccr');
        $sesion = session();
        $cuenta = $sesion->get('cuenta');
        $ahora = time();

        if (!is_array($cuenta) || ($cuenta['tipo'] ?? null) !== $tipoEsperado) {
            return $this->rechazar($request, $ambito, null);
        }
        $limite = $ambito === 'portal' ? $cfg->inactividadPortal : $cfg->inactividadPanel;
        if ($ahora - (int) $cuenta['ultima_actividad'] > $limite || $ahora - (int) $cuenta['inicio'] > $cfg->duracionMaximaSesion) {
            finalizar_sesion();

            return $this->rechazar($request, $ambito, 'La sesión finalizó por inactividad.');
        }
        $actual = Database::connect()->table('cuentas_acceso')->select('estado, version_seguridad')
            ->where('id', (int) $cuenta['id'])->get()->getRowArray();
        if ($actual === null || $actual['estado'] !== 'Habilitada' || (int) $actual['version_seguridad'] !== (int) $cuenta['version_seguridad']) {
            finalizar_sesion();

            return $this->rechazar($request, $ambito, 'La sesión ya no es válida. Ingrese nuevamente.');
        }
        $cuenta['ultima_actividad'] = $ahora;
        $sesion->set('cuenta', $cuenta);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function rechazar(RequestInterface $request, string $ambito, ?string $mensaje): ResponseInterface
    {
        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON(['mensaje' => $mensaje ?? 'Sesión requerida.']);
        }
        $redirect = redirect()->to(site_url($ambito === 'portal' ? 'portal/login' : 'admin/login'));

        return $mensaje === null ? $redirect : $redirect->with('aviso', $mensaje);
    }
}
