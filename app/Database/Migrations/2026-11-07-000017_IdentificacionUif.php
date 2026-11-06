<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Nacionalidad y declaración de persona expuesta políticamente del cliente (D-31, RN-80). */
class IdentificacionUif extends MigracionSql
{
    protected string $archivo = '17_identificacion_uif';
}
