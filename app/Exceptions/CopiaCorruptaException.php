<?php

declare(strict_types=1);

namespace App\Exceptions;

/** La copia de seguridad no supera la verificación de integridad (CU-62 A1, CU-63 A1). */
class CopiaCorruptaException extends ReglaNegocioException
{
}
