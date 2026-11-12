<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionSql;

/** Solicitudes originadas por el cliente al aceptar una oferta de campaña, sin Analista (CU-52, D-43). */
class SolicitudDesdeOferta extends MigracionSql
{
    protected string $archivo = '27_solicitud_desde_oferta';
}
