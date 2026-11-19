<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Modelo de contrato configurable: grupo de parámetros «Contrato» y versión de cada contrato (D-56). */
class ModeloContrato extends MigracionSql
{
    protected string $archivo = '34_modelo_contrato';
}
