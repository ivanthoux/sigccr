<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Copias\EstadoRestauracion;

/** Estado de la restauración de una copia (CU-63, D-45). Público y sin base de datos: la base se está reemplazando. */
class Mantenimiento extends BaseController
{
    public function index(): string
    {
        $e = EstadoRestauracion::predeterminado();

        return view('errores/restauracion', ['estado' => $e->actual(), 'ultima' => $e->ultima()]);
    }
}
