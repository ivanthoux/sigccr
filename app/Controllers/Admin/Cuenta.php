<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\CambioContrasenaService;
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
        $cuenta = session('cuenta');
        try {
            $version = (new CambioContrasenaService())->cambiar((int) $cuenta['id'], $this->campoPost('actual'), $this->campoPost('contrasena'), $this->campoPost('confirmacion'));
        } catch (ReglaNegocioException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        $cuenta['version_seguridad'] = $version;
        session()->set('cuenta', $cuenta);
        // V7.2.4: la sesión recibe un identificador nuevo cuando cambia la credencial
        session()->regenerate(true);

        return redirect()->route('panel.inicio')->with('exito', 'La contraseña se cambió. Las demás sesiones abiertas con esta cuenta se cerraron.');
    }
}
