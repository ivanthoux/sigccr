<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Datos ingresados inválidos, con el error de cada campo para marcarlo en el formulario (por ejemplo, CU-05 A3:
 * "el sistema marca los campos faltantes").
 */
class ValidacionException extends ReglaNegocioException
{
    /**
     * @param array<string, string> $errores campo => mensaje
     */
    public function __construct(public readonly array $errores, ?string $regla = null)
    {
        parent::__construct(count($errores) === 1 ? reset($errores) : 'Revise los campos marcados.', $regla);
    }
}
