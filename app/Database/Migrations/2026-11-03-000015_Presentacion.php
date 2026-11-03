<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Rangos estrictos, gasto administrativo porcentual, documentación sugerida y categorías del monotributo (D-23, D-25, D-26, D-28). */
class Presentacion extends MigracionSql
{
    protected string $archivo = '15_presentacion';
}
