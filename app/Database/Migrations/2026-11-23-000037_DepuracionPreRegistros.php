<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Permiso para eliminar los pre-registros que no se completaron en 90 días (D-64, Ley 25.326 art. 4 inc. 7). */
class DepuracionPreRegistros extends MigracionSql
{
    protected string $archivo = '37_depuracion_pre_registros';
}
