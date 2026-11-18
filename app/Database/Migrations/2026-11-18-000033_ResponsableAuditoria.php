<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Responsable de la auditoría en NVARCHAR(254), el largo de un correo (H-29). */
class ResponsableAuditoria extends MigracionSql
{
    protected string $archivo = '33_responsable_auditoria';
}
