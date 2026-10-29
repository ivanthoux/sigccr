<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/**
 * CU-83 Consultar Alertas: alertas vistas por cada usuario y responsable de la resolución.
 */
class CrearVistasAlertas extends MigracionSql
{
    protected string $archivo = '11_alertas';
}
