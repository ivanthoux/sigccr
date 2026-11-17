<?php

declare(strict_types=1);

namespace App\Adapters\Comunicaciones;

use CodeIgniter\Email\Email;

/**
 * Correo de CodeIgniter que conserva el Message-ID indicado: la biblioteca genera uno propio al armar los
 * encabezados y reemplaza el que se haya definido con setHeader(). El identificador de la operación viaja como
 * Message-ID para que el proveedor descarte los duplicados de un reintento (RNF-09, CU-95).
 */
class CorreoConIdentificador extends Email
{
    private ?string $idMensaje = null;

    /** @param string $id identificador completo, con el dominio y entre < > */
    public function setMessageId(string $id): static
    {
        $this->idMensaje = $id;

        return $this;
    }

    protected function getMessageID()
    {
        return $this->idMensaje ?? parent::getMessageID();
    }
}
