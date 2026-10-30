<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\ActivacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-04 Activar o Rehabilitar Acceso del Cliente, en el portal y sin sesión. */
class Activacion extends BaseController
{
    /** Pasos 1 y 2. */
    public function solicitud(): string
    {
        return view('portal/activar', ['titulo' => 'Activar o rehabilitar acceso']);
    }

    /** Pasos 3 a 6, A1, A2 y A5. */
    public function solicitar(): RedirectResponse
    {
        $tipo = (string) $this->request->getPost('tipo_documento');
        $numero = trim((string) $this->request->getPost('numero'));
        $correo = trim((string) $this->request->getPost('correo'));
        if ($numero === '' || $correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()->withInput()->with('error', 'Ingrese su documento (o CUIT) y un correo electrónico válido.');
        }
        $enviado = (new ActivacionService())->solicitar($tipo, $numero, $correo);
        if (!$enviado) {
            return redirect()->back()->withInput()->with('error', ActivacionService::MENSAJE_NO_VERIFICADO);
        }

        return redirect()->route('portal.activar')->with('exito', ActivacionService::MENSAJE_ENVIADO);
    }

    /** Paso 7 y A3. */
    public function contrasena(string $codigo): string|RedirectResponse
    {
        if (!(new ActivacionService())->esValida($codigo)) {
            return redirect()->route('portal.activar')->with('error', ActivacionService::MENSAJE_INVALIDA);
        }

        return view('portal/activar_contrasena', ['titulo' => 'Definir su contraseña', 'codigo' => $codigo]);
    }

    /** Pasos 8 a 10 y A4. El paso 10 lleva al inicio de sesión del portal con el correo ya cargado (CU-01). */
    public function guardarContrasena(string $codigo): RedirectResponse
    {
        try {
            $correo = (new ActivacionService())->definirContrasena($codigo, (string) $this->request->getPost('contrasena'), (string) $this->request->getPost('confirmacion'));
        } catch (ReglaNegocioException $e) {
            if ($e->getMessage() === ActivacionService::MENSAJE_INVALIDA) {
                return redirect()->route('portal.activar')->with('error', $e->getMessage());
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('portal.login')->with('correo', $correo)
            ->with('exito', 'Su acceso al portal quedó activado. Ingrese con su correo y la contraseña que definió.');
    }
}
