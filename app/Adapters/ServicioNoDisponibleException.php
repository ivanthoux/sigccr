<?php

declare(strict_types=1);

namespace App\Adapters;

use RuntimeException;

/** El servicio externo no respondió dentro del tiempo y los reintentos admitidos (RNF-08, R6). */
class ServicioNoDisponibleException extends RuntimeException
{
}
