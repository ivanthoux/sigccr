<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Otra operación modificó el objeto después de leerlo (control de version_fila, RN-24, RNF-06).
 * La transacción se revierte y el usuario debe volver a consultar el estado actualizado.
 */
class ConflictoConcurrenciaException extends ReglaNegocioException
{
    public function __construct(string $objeto, int|string $id)
    {
        parent::__construct("El registro {$objeto} #{$id} fue modificado por otra operación. Actualice la pantalla y vuelva a intentarlo.", 'RN-24');
    }
}
