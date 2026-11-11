<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Revocación del préstamo dentro de los 10 días hábiles (D-31, RN-79, CU-102). */
class Revocacion extends MigracionSql
{
    protected string $archivo = '19_revocacion';
}
