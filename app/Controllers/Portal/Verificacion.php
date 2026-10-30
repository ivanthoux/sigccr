<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\ActivacionService;
use App\Services\Acceso\AutorizacionService;
use App\Services\Clientes\ClienteService;
use CodeIgniter\HTTP\RedirectResponse;

/** Verificación del correo del cliente desde el enlace (CU-11 paso 8, CU-12 paso 8; RN-08). No requiere sesión. */
class Verificacion extends BaseController
{
    public function mostrar(string $codigo): string
    {
        $valido = (new AutorizacionService())->buscarVigente($codigo, ['VerificacionCorreo'], false) !== null;

        return view('portal/verificar_correo', ['titulo' => 'Confirmar correo', 'codigo' => $codigo, 'valido' => $valido, 'resultado' => null]);
    }

    /** Confirma el correo y, si la cuenta todavía no está activa, continúa con la definición de la contraseña (D-16). */
    public function confirmar(string $codigo): string|RedirectResponse
    {
        try {
            $cuentaId = (new ClienteService())->verificarCorreo($codigo);
        } catch (ReglaNegocioException $e) {
            return view('portal/verificar_correo', ['titulo' => 'Confirmar correo', 'codigo' => $codigo, 'valido' => false, 'resultado' => ['error', $e->getMessage()]]);
        }
        $activacion = (new ActivacionService())->continuarTrasVerificacion($cuentaId);
        if ($activacion !== null) {
            return redirect()->to(site_url('portal/activar/' . $activacion))
                ->with('exito', 'Su correo quedó verificado. Ahora defina la contraseña para activar su acceso al portal.');
        }

        return redirect()->route('portal.login')->with('exito', 'Su correo quedó verificado. Si ya tiene acceso al portal, ingrese con esta dirección.');
    }
}
