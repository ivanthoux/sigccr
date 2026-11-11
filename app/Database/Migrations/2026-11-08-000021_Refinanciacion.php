<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Refinanciación y condonación de punitorios (D-36, RN-36 a RN-38, RN-70; CU-39 a CU-44, CU-74, CU-96). */
class Refinanciacion extends MigracionSql
{
    protected string $archivo = '21_refinanciacion';
}
