<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Observaciones de la caja y corrección de movimientos de fondos con un movimiento inverso vinculado (D-21). */
class CajasFondos extends MigracionSql
{
    protected string $archivo = '14_cajas_fondos';
}
