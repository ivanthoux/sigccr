<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Services\Portal\PortalService;
use Config\Menu;
use Throwable;

/** Pantalla principal del portal (CU-01 paso 6 para el Cliente). */
class Inicio extends BaseController
{
    public function index(): string
    {
        $cuenta = session('cuenta');
        $funciones = [];
        foreach (config(Menu::class)->portal as $f) {
            $url = route_to($f['ruta']);
            $funciones[] = $f + ['url' => $url === false ? null : site_url($url)];
        }
        $anterior = $cuenta['ultimo_acceso_anterior'] ?? null;
        // Lo que el cliente viene a ver, a la vista apenas ingresa: deuda, cuotas vencidas y el próximo vencimiento. Si la
        // consulta falla, el inicio se muestra igual, sin el resumen (los accesos siguen disponibles).
        try {
            $resumen = (new PortalService())->estadoCuenta((int) ($cuenta['cliente_id'] ?? 0), false);
        } catch (Throwable $e) {
            log_message('error', 'Resumen del inicio del portal: {mensaje}', ['mensaje' => $e->getMessage()]);
            $resumen = null;
        }

        return view('portal/inicio', [
            'titulo' => 'Inicio',
            'funciones' => $funciones,
            'ultimoAcceso' => $anterior === null ? null : formato_fecha_hora((string) $anterior),
            'resumen' => $resumen,
        ]);
    }
}
