<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Enlace de pago de las órdenes electrónicas (CU-34 A7). */
class PagosElectronicos extends MigracionSql
{
    protected string $archivo = '20_pagos_electronicos';
}
