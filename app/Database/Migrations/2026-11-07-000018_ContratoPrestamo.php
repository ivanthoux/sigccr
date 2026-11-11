<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Contrato de préstamo de cada aprobación y su constancia firmada en el legajo (D-31, RN-78). */
class ContratoPrestamo extends MigracionSql
{
    protected string $archivo = '18_contrato_prestamo';
}
