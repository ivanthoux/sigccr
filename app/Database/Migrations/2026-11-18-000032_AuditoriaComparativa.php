<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Posición de la simulación comparativa en INT (H-22) y motivo de la auditoría en NVARCHAR(2000) (H-23). */
class AuditoriaComparativa extends MigracionSql
{
    protected string $archivo = '32_auditoria_comparativa';
}
