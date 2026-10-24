<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Incumplimiento de una regla de negocio. El mensaje es apto para mostrar al usuario; $regla identifica la regla
 * (por ejemplo, 'RN-29') para la auditoría y las pruebas.
 */
class ReglaNegocioException extends DomainException
{
    public function __construct(string $mensaje, public readonly ?string $regla = null)
    {
        parent::__construct($mensaje);
    }
}
