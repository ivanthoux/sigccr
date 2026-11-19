<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** No se pueden generar los datos de la prueba de rendimiento (RendimientoSeeder): falta configuración o datos base. */
class DatosDePruebaException extends RuntimeException
{
}
