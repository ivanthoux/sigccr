<?php

declare(strict_types=1);

namespace App\Adapters\Comunicaciones;

use App\Libraries\Validacion\Campos;
use CodeIgniter\Email\Email;

/**
 * Correo de CodeIgniter que conserva el Message-ID indicado: la biblioteca genera uno propio al armar los
 * encabezados y reemplaza el que se haya definido con setHeader(). El identificador de la operación viaja como
 * Message-ID para que el proveedor descarte los duplicados de un reintento (RNF-09, CU-95).
 *
 * También admite destinatarios internacionales (O-05, D-52): valida la parte local con letras no ASCII y, si algún
 * destinatario la tiene, pide la extensión SMTPUTF8 en el MAIL FROM (RFC 6531). El dominio se envía en punycode.
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

    /** Igual que la biblioteca, pero con la parte local internacional (RFC 6531) y el dominio en punycode. */
    public function isValidEmail($email)
    {
        $email = (string) $email;
        $arroba = strrpos($email, '@');
        if ($arroba === false) {
            return false;
        }
        $dominio = Campos::dominioAscii(substr($email, $arroba + 1));

        return $dominio !== null
            && filter_var(substr($email, 0, $arroba) . '@' . $dominio, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
    }

    /** Indica si algún destinatario necesita SMTPUTF8 (parte local con caracteres no ASCII). */
    public function requiereSmtpUtf8(): bool
    {
        foreach ((array) $this->recipients as $destinatario) {
            if (preg_match('/[^\x00-\x7F]/', (string) $destinatario) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function sendCommand($cmd, $data = '')
    {
        if ($cmd !== 'from' || !$this->requiereSmtpUtf8()) {
            return parent::sendCommand($cmd, $data);
        }
        $this->sendData('MAIL FROM:<' . $data . '> SMTPUTF8');
        $respuesta = $this->getSMTPData();
        $this->debugMessage[] = '<pre>from: ' . $respuesta . '</pre>';
        if ((int) substr($respuesta, 0, 3) !== 250) {
            $this->setErrorMessage(lang('Email.SMTPError', [$respuesta]));

            return false;
        }

        return true;
    }
}
