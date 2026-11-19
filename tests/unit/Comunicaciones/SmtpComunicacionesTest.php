<?php

declare(strict_types=1);

namespace Tests\Unit\Comunicaciones;

use App\Adapters\Comunicaciones\ComunicacionesInterface as C;
use App\Adapters\Comunicaciones\CorreoConIdentificador;
use App\Adapters\Comunicaciones\SmtpComunicaciones;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    /** D-49: el identificador de la operación viaja como Message-ID; CodeIgniter generaba uno propio y lo reemplazaba. */
    public function testElMessageIdEsElDeLaOperacion(): void
    {
        $correo = (new CorreoConIdentificador(['protocol' => 'smtp']))->setMessageId('<COM-42@sigccr.local>');
        $this->assertSame('<COM-42@sigccr.local>', (new ReflectionMethod($correo, 'getMessageID'))->invoke($correo));
    }

    /** O-05 (D-52): la biblioteca rechazaba la parte local internacional; ahora se admite, con el dominio en punycode. */
    public function testValidaCorreosInternacionales(): void
    {
        $correo = new CorreoConIdentificador(['protocol' => 'smtp']);
        $this->assertTrue($correo->isValidEmail('ñandú@prueba.local'));
        $this->assertTrue($correo->isValidEmail('ana@ñandú.com.ar'));
        $this->assertTrue($correo->isValidEmail('ana@prueba.local'));
        $this->assertFalse($correo->isValidEmail('ana@@prueba.local'));
        $this->assertFalse($correo->isValidEmail('ana.prueba.local'));
        $this->assertFalse($correo->isValidEmail('ana@'));
    }

    /** O-05 (D-52): el MAIL FROM pide SMTPUTF8 solo si algún destinatario tiene la parte local no ASCII. */
    public function testPideSmtpUtf8SoloParaDestinatariosInternacionales(): void
    {
        $this->assertSame('MAIL FROM:<sigccr@sigccr.local>', $this->mailFrom('ana@prueba.local'));
        $this->assertSame('MAIL FROM:<sigccr@sigccr.local> SMTPUTF8', $this->mailFrom('ñandú@prueba.local'));
        $this->assertSame('MAIL FROM:<sigccr@sigccr.local>', $this->mailFrom('ana@xn--and-6ma2c.com.ar'));
    }

    public function testRechazoDelMailFromConSmtpUtf8EsTransitorio(): void
    {
        $correo = $this->correoSimulado('555 5.5.4 SMTPUTF8 not supported');
        $correo->setTo('ñandú@prueba.local');
        $this->assertFalse((new ReflectionMethod($correo, 'sendCommand'))->invoke($correo, 'from', 'sigccr@sigccr.local'));
        $this->assertStringContainsString('555', (string) $correo->printDebugger([]));
    }

    private function mailFrom(string $destino): string
    {
        $correo = $this->correoSimulado('250 2.1.0 OK');
        $correo->setTo($destino);
        $this->assertTrue((new ReflectionMethod($correo, 'sendCommand'))->invoke($correo, 'from', 'sigccr@sigccr.local'));

        return $correo->enviados[0];
    }

    /** Correo con un servidor SMTP simulado que registra los comandos y responde siempre lo mismo. */
    private function correoSimulado(string $respuesta): CorreoConIdentificador
    {
        return new class (['protocol' => 'smtp'], $respuesta) extends CorreoConIdentificador {
            /** @var list<string> */
            public array $enviados = [];

            public function __construct(array $config, private readonly string $respuesta)
            {
                parent::__construct($config);
            }

            protected function sendData($data)
            {
                $this->enviados[] = $data;

                return true;
            }

            protected function getSMTPData()
            {
                return $this->respuesta;
            }
        };
    }
}
