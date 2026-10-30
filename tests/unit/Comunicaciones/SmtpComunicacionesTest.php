<?php

declare(strict_types=1);

namespace Tests\Unit\Comunicaciones;

use App\Adapters\Comunicaciones\ComunicacionesInterface as C;
use App\Adapters\Comunicaciones\SmtpComunicaciones;
use PHPUnit\Framework\TestCase;

/** Clasificación de las respuestas SMTP en los resultados de RNF-09. */
final class SmtpComunicacionesTest extends TestCase
{
    public function testAceptado(): void
    {
        $r = SmtpComunicaciones::clasificar(true, '250 2.0.0 OK queued', 'op-1');
        $this->assertSame(C::ACEPTADO, $r['resultado']);
        $this->assertSame('op-1', $r['id_proveedor']);
    }

    public function testDestinatarioRechazadoEsDefinitivo(): void
    {
        $traza = "220 smtp.ejemplo.com ESMTP\n250 OK\n550 5.1.1 <nadie@ejemplo.com>: Recipient address rejected";
        $this->assertSame(C::FALLO_DEFINITIVO, SmtpComunicaciones::clasificar(false, $traza, 'op-2')['resultado']);
    }

    public function testSinConexionOTemporalEsTransitorio(): void
    {
        $this->assertSame(C::FALLO_TRANSITORIO, SmtpComunicaciones::clasificar(false, 'Unable to connect to the socket: Connection timed out', 'op-3')['resultado']);
        $this->assertSame(C::FALLO_TRANSITORIO, SmtpComunicaciones::clasificar(false, "250 OK\n421 4.7.0 Try again later", 'op-4')['resultado']);
    }

    public function testCorteDespuesDelMensajeEsIncierto(): void
    {
        $traza = "250 OK\n354 End data with <CR><LF>.<CR><LF>\nUnable to send data: timed out";
        $this->assertSame(C::INCIERTO, SmtpComunicaciones::clasificar(false, $traza, 'op-5')['resultado']);
    }
}
