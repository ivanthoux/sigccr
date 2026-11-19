<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Clientes\PreRegistroService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-104 Pre-registrarse en el Portal (sin sesión; límite de intentos por dirección en Config\Filters). */
class PreRegistro extends BaseController
{
    /** Pasos 1 y 2. */
    public function nuevo(): string
    {
        return view('portal/pre_registro', ['titulo' => 'Pre-registrarse']);
    }

    /** Pasos 3 a 5 (A1, A2, A4). */
    public function registrar(): string|RedirectResponse
    {
        try {
            (new PreRegistroService())->registrar((array) $this->request->getPost(), $this->request->getIPAddress());
        } catch (ValidacionException $e) {
            return redirect()->back()->withInput()->with('error', 'Revise los datos marcados.')->with('errores', $e->errores);
        } catch (ReglaNegocioException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return view('portal/pre_registro_enviado', ['titulo' => 'Verifique su correo', 'correo' => $this->campoPost('correo')]);
    }

    /** Paso 6: la confirmación se hace con un botón, para que un antivirus que abre el enlace no lo consuma. */
    public function mostrarVerificacion(string $codigo): string
    {
        return view('portal/pre_registro_verificar', ['titulo' => 'Verificar correo', 'codigo' => $codigo, 'resultado' => null]);
    }

    /** Pasos 6 y 7 (A3). */
    public function verificar(string $codigo): string
    {
        try {
            (new PreRegistroService())->verificar($codigo);
        } catch (ReglaNegocioException $e) {
            return view('portal/pre_registro_verificar', ['titulo' => 'Verificar correo', 'codigo' => $codigo, 'resultado' => ['error', $e->getMessage()]]);
        }

        return view('portal/pre_registro_verificar', ['titulo' => 'Verificar correo', 'codigo' => $codigo, 'resultado' => ['exito', 'Su correo quedó verificado. Acérquese a la sucursal con su documento de identidad para completar el alta: un analista verificará su identidad y le enviará el enlace para activar el portal.']]);
    }
}
