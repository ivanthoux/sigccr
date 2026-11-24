<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-107 Cambiar Contraseña, para cualquier usuario interno con sesión (no requiere un permiso del rol). */
class Cuenta extends BaseController
{
    public function contrasena(): string
    {
        return view('acceso/cambiar_contrasena', ['titulo' => 'Cambiar contraseña', 'menu' => menu_panel(), 'base' => 'admin']);
    }

    public function guardarContrasena(): RedirectResponse
    {
        return $this->cambiarContrasena('panel.inicio', 'La contraseña se cambió. Las demás sesiones abiertas con esta cuenta se cerraron.');
    }
}
