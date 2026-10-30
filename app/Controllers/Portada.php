<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Portada pública del SIG-CCR (raíz del sitio). Orienta al cliente hacia el portal de autogestión, con un acceso
 * discreto para el personal de la entidad. No muestra datos ni usa la base: la protección del panel la dan la
 * autenticación, los permisos por ruta y el control de intentos (4.4.6), no que su dirección sea desconocida.
 */
class Portada extends BaseController
{
    public function index(): string
    {
        $tipo = session('cuenta')['tipo'] ?? null;

        return view('portada', [
            'titulo' => 'Inicio',
            // Con una sesión abierta, la portada ofrece volver a ella en lugar de iniciar otra
            'continuar' => match ($tipo) {
                'Cliente' => ['url' => url_to('portal.inicio'), 'texto' => 'Continuar en el portal'],
                'UsuarioInterno' => ['url' => url_to('panel.inicio'), 'texto' => 'Continuar en el panel'],
                default => null,
            },
        ]);
    }
}
