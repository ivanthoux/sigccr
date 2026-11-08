<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Saldos a favor y devoluciones (D-37, RN-57, RN-73; CU-76 a CU-78). */
class Devoluciones extends MigracionSql
{
    protected string $archivo = '22_devoluciones';
}
