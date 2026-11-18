<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Otra operación modificó el objeto después de leerlo (control de version_fila, RN-24, RNF-06).
 * La transacción se revierte y el usuario debe volver a consultar el estado actualizado.
 *
 * El mensaje para el usuario no nombra la tabla ni el identificador interno (RNF-12, D-49); quedan en las propiedades
 * para el registro técnico.
 */
class ConflictoConcurrenciaException extends ReglaNegocioException
{
    public const MENSAJE = 'El registro fue modificado por otra operación. Actualice la pantalla y vuelva a intentarlo.';

    public function __construct(public readonly string $objeto, public readonly int|string $id)
    {
        parent::__construct(self::MENSAJE, 'RN-24');
    }
}
