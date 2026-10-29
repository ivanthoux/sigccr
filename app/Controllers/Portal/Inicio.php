<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use Config\Menu;

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

        return view('portal/inicio', [
            'titulo' => 'Inicio',
            'funciones' => $funciones,
            'ultimoAcceso' => $anterior === null ? null : formato_fecha_hora((string) $anterior),
        ]);
    }
}
