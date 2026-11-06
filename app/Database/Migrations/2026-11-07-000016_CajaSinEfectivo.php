<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Caja y cobros sin efectivo: apertura sin fondo inicial y cierre por conciliación de cada medio de pago (D-30). */
class CajaSinEfectivo extends MigracionSql
{
    protected string $archivo = '16_caja_sin_efectivo';
}
