<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Régimen informativo de deudores de la Central de Deudores del BCRA (CU-105, RN-84, D-44). */
class RegimenInformativo extends MigracionSql
{
    protected string $archivo = '28_regimen_informativo';
}
