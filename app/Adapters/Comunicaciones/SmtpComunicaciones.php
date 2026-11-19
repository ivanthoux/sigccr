<?php

declare(strict_types=1);

namespace App\Adapters\Comunicaciones;

use App\Libraries\Validacion\Campos;
use CodeIgniter\Email\Email;

/**
 * Servicio de comunicaciones real por SMTP (RNF-09), con la biblioteca Email de CodeIgniter.
 *
 * Configuración en .env: sigccr.adaptadorComunicaciones = real, sigccr.smtpHost, sigccr.smtpPuerto,
 * sigccr.smtpUsuario, sigccr.smtpClave, sigccr.smtpCifrado (tls o ssl) y sigccr.correoRemitente.
 * El identificador de operación viaja como Message-ID, para que el proveedor descarte los duplicados de un reintento.
 *
 * Clasificación del resultado a partir de la respuesta del servidor (RFC 5321):
 *  - aceptado: el servidor respondió 250 al final del mensaje;
 *  - fallo definitivo: 5xx sobre el destinatario o el mensaje (550, 551, 552, 553, 554);
 *  - incierto: la conexión se cortó después de enviar el mensaje, sin confirmación;
 *  - fallo transitorio: no se pudo conectar, 4xx o cualquier otro error antes de enviar el mensaje.
 */
class SmtpComunicaciones implements ComunicacionesInterface
{
    public function __construct(private readonly ?Email $email = null)
    {
    }

    public function enviar(string $destino, string $asunto, string $cuerpoHtml, string $identificadorOperacion): array
    {
        $cfg = config('Sigccr');
        $email = $this->email ?? new CorreoConIdentificador([
            'protocol' => 'smtp',
            'SMTPHost' => $cfg->smtpHost,
            'SMTPPort' => $cfg->smtpPuerto,
            'SMTPUser' => $cfg->smtpUsuario,
            'SMTPPass' => $cfg->smtpClave,
            'SMTPCrypto' => $cfg->smtpCifrado,
            'SMTPTimeout' => $cfg->tiempoEsperaExterno,
            'mailType' => 'html',
            'charset' => 'UTF-8',
            'wordWrap' => false,
        ]);
        $dominio = substr((string) strrchr($cfg->correoRemitente, '@'), 1) ?: 'sigccr.local';
        $email->setFrom($cfg->correoRemitente, 'SIG-CCR');
        $email->setTo(Campos::correoParaEnvio($destino));
        $email->setSubject($asunto);
        $email->setMessage($cuerpoHtml);
        $idMensaje = '<' . $identificadorOperacion . '@' . $dominio . '>';
        if ($email instanceof CorreoConIdentificador) {
            $email->setMessageId($idMensaje);
        } else {
            $email->setHeader('Message-ID', $idMensaje);
        }

        $ok = $email->send(false);
        $traza = (string) $email->printDebugger(['headers']);

        return self::clasificar($ok, $traza, $identificadorOperacion);
    }

    /**
     * @return array{resultado: string, id_proveedor: ?string, detalle: ?string}
     */
    public static function clasificar(bool $ok, string $traza, string $identificadorOperacion): array
    {
        if ($ok) {
            return ['resultado' => self::ACEPTADO, 'id_proveedor' => $identificadorOperacion, 'detalle' => null];
        }
        $detalle = mb_substr(trim(strip_tags($traza)), 0, 500);
        if (preg_match('/\b55[0-4][ -]/', $traza) === 1) {
            return ['resultado' => self::FALLO_DEFINITIVO, 'id_proveedor' => null, 'detalle' => $detalle];
        }
        // El cuerpo ya se transmitió (respuesta 354 al DATA) pero no llegó la confirmación final
        if (preg_match('/\b354[ -]/', $traza) === 1 && preg_match('/\b250[ -][^\n]*(queued|accepted|ok)/i', substr($traza, (int) strpos($traza, '354'))) !== 1) {
            return ['resultado' => self::INCIERTO, 'id_proveedor' => null, 'detalle' => $detalle];
        }

        return ['resultado' => self::FALLO_TRANSITORIO, 'id_proveedor' => null, 'detalle' => $detalle];
    }
}
