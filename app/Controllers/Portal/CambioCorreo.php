<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\AutorizacionService;
use App\Services\Clientes\ClienteService;

/**
 * CU-75 pasos 6 y 7 (A2): confirmación del cambio desde el enlace enviado al correo actual. No requiere sesión: el
 * enlace de un solo uso demuestra que el cliente controla ese correo (RN-03, RN-58).
 */
class CambioCorreo extends BaseController
{
    public function mostrar(string $codigo): string
    {
        $a = (new AutorizacionService())->buscarVigente($codigo, ['CambioCorreo'], false);

        return view('portal/confirmar_cambio_correo', ['titulo' => 'Confirmar cambio de correo', 'codigo' => $codigo, 'nuevo' => $a['correo_destino'] ?? null, 'resultado' => null]);
    }

    public function confirmar(string $codigo): string
    {
        try {
            $nuevo = (new ClienteService())->confirmarCambioCorreo($codigo);
        } catch (ReglaNegocioException $e) {
            return view('portal/confirmar_cambio_correo', ['titulo' => 'Confirmar cambio de correo', 'codigo' => $codigo, 'nuevo' => null, 'resultado' => ['error', $e->getMessage()]]);
        }

        return view('portal/confirmar_cambio_correo', [
            'titulo' => 'Confirmar cambio de correo', 'codigo' => $codigo, 'nuevo' => $nuevo,
            'resultado' => ['exito', "Cambio confirmado. Enviamos un enlace a {$nuevo}: ábralo dentro de los próximos 30 minutos para verificar el correo nuevo y completar el cambio."],
        ]);
    }
}
