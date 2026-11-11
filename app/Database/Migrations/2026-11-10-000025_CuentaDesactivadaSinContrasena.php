<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Una cuenta pendiente de activación también puede desactivarse (CU-08, CU-13). */
class CuentaDesactivadaSinContrasena extends MigracionSql
{
    protected string $archivo = '25_cuenta_desactivada_sin_contrasena';
}
