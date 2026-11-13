<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Largo del origen de las copias de seguridad, para la copia previa a una restauración (CU-63, D-45). */
class OrigenCopias extends MigracionSql
{
    protected string $archivo = '30_origen_copias';
}
