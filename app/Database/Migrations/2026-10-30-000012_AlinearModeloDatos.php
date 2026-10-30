<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/**
 * Alineación del esquema con la especificación del modelo de datos: alertas_vistas sin clave foránea hacia SS-01 e
 * índices de las claves foráneas de la migración 10.
 */
class AlinearModeloDatos extends MigracionSql
{
    protected string $archivo = '12_alineacion_modelo';
}
