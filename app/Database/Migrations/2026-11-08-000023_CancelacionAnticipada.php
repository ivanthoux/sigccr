<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Cancelación anticipada total de un crédito (D-38, RN-67, CU-97). */
class CancelacionAnticipada extends MigracionSql
{
    protected string $archivo = '23_cancelacion_anticipada';
}
