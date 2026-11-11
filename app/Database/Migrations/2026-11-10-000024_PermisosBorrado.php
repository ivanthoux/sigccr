<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Permiso de borrado del rol de la aplicación sobre las tablas que la aplicación borra (4.3.5). */
class PermisosBorrado extends MigracionSql
{
    protected string $archivo = '24_permisos_borrado';
}
