<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\AutorizacionService;
use App\Services\Clientes\ClienteService;

/** Verificación del correo del cliente desde el enlace (CU-11 paso 8, CU-12 paso 8; RN-08). No requiere sesión. */
class Verificacion extends BaseController
{
    public function mostrar(string $codigo): string
    {
        $valido = (new AutorizacionService())->buscarVigente($codigo, ['VerificacionCorreo'], false) !== null;

        return view('portal/verificar_correo', ['titulo' => 'Confirmar correo', 'codigo' => $codigo, 'valido' => $valido, 'resultado' => null]);
    }

    public function confirmar(string $codigo): string
    {
        try {
            (new ClienteService())->verificarCorreo($codigo);
            $resultado = ['exito', 'Su correo quedó verificado. Ya puede activar su acceso al portal de autogestión.'];
        } catch (ReglaNegocioException $e) {
            $resultado = ['error', $e->getMessage()];
        }

        return view('portal/verificar_correo', ['titulo' => 'Confirmar correo', 'codigo' => $codigo, 'valido' => false, 'resultado' => $resultado]);
    }
}
