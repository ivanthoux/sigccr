<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Copias de seguridad y restauración (CU-61 a CU-63, RNF-13, D-45). */
class CopiasSeguridad extends MigracionSql
{
    protected string $archivo = '29_copias_seguridad';
}
