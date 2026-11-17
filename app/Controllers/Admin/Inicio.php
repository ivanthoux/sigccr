<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Comunes\PendientesService;
use Config\Database;

/** Pantalla principal del panel (CU-01 paso 6): trabajo pendiente del rol, alertas y funciones disponibles. */
class Inicio extends BaseController
{
    public function index(): string
    {
        $cuenta = session('cuenta');
        $alertas = Database::connect()->table('alertas')->where('estado', 'Pendiente')
            ->groupStart()->where('rol_destino', $cuenta['rol'])->orWhere('usuario_destino_id', $cuenta['id'])->groupEnd()
            ->countAllResults();

        $pendientes = [];
        foreach ((new PendientesService())->deCuenta((int) $cuenta['id'], $cuenta['permisos'] ?? []) as $p) {
            $url = route_to($p['ruta']);
            if ($url !== false) {
                $pendientes[] = ['texto' => $p['texto'], 'cantidad' => $p['cantidad'], 'url' => site_url(ltrim($url, '/')) . ($p['filtro'] === [] ? '' : '?' . http_build_query($p['filtro']))];
            }
        }

        return view('admin/inicio', ['titulo' => 'Inicio', 'menu' => menu_panel(), 'alertasPendientes' => $alertas, 'pendientes' => $pendientes]);
    }
}
