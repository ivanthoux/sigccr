<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Domicilio del cliente con provincia y localidad: catálogos geográficos y columnas de clientes (D-18). */
class DomicilioLocalidad extends MigracionSql
{
    protected string $archivo = '13_domicilio_localidad';
}
