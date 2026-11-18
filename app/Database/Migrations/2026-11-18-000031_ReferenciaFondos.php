<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Referencia de los movimientos de fondos en NVARCHAR(100) (CU-85, H-17). */
class ReferenciaFondos extends MigracionSql
{
    protected string $archivo = '31_referencia_fondos';
}
