<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Database;

/** Pantalla principal del panel (CU-01 paso 6): funciones disponibles según el rol y alertas pendientes. */
class Inicio extends BaseController
{
    public function index(): string
    {
        $cuenta = session('cuenta');
        $alertas = Database::connect()->table('alertas')->where('estado', 'Pendiente')
            ->groupStart()->where('rol_destino', $cuenta['rol'])->orWhere('usuario_destino_id', $cuenta['id'])->groupEnd()
            ->countAllResults();

        return view('admin/inicio', ['titulo' => 'Inicio', 'menu' => menu_panel(), 'alertasPendientes' => $alertas]);
    }
}
