<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Portal de autogestión: pre-registro de personas que todavía no son clientes (CU-104, D-42). */
class PortalAutogestion extends MigracionSql
{
    protected string $archivo = '26_portal_autogestion';
}
