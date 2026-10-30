<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/**
 * Ajustes por las devoluciones de la presentación de la v8 (26/10/2026): cobros con varios medios de pago (D-11),
 * pago parcial ordinario (D-12) y simulación comparativa de líneas (D-10). Registro de decisiones en docs/decisiones.md.
 */
class AjustesPresentacionV8 extends MigracionSql
{
    protected string $archivo = '10_ajustes_v9';
}
