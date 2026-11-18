<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Tipo de documento de la declaración jurada de persona expuesta políticamente, exigida para liquidar (D-55). */
class DeclaracionPep extends MigracionSql
{
    protected string $archivo = '33_declaracion_pep';
}
