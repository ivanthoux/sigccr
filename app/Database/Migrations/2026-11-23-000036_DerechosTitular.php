<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Solicitudes de derechos del titular de los datos personales: acceso, rectificación, supresión y revisión (CU-108, D-64). */
class DerechosTitular extends MigracionSql
{
    protected string $archivo = '36_derechos_titular';
}
