<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Intento de un cliente de ver en el portal un objeto que no le pertenece (CU-46 A3, CU-47 A4, CU-48 A3). El mensaje
 * es genérico y el mismo si el objeto no existe, para no revelar información de otros clientes.
 */
class AccesoAjenoException extends ReglaNegocioException
{
    public function __construct()
    {
        parent::__construct('No encontramos la información solicitada entre sus operaciones.', 'RN-01');
    }
}
