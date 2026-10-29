<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Seguridad\ControlIntentos;
use App\Libraries\Seguridad\PoliticaContrasena;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Política de contraseñas y bloqueo por intentos (RN-62, CU-01 A1, CU-02 paso 7). */
final class PoliticaYControlIntentosTest extends TestCase
{
    public function testLargoEntre15Y128Caracteres(): void
    {
        $this->assertCount(1, PoliticaContrasena::validar('corta-14-carac'));
        $this->assertSame([], PoliticaContrasena::validar('quince-caracter'));
        $this->assertSame([], PoliticaContrasena::validar(str_repeat('ñ', 128)));
        $this->assertCount(1, PoliticaContrasena::validar(str_repeat('a', 129) . 'x'));
    }

    public function testRechazaContrasenasComprometidasSinDistinguirMayusculas(): void
    {
        $this->assertCount(1, PoliticaContrasena::validar('PasswordPassword'));
        $this->assertCount(1, PoliticaContrasena::validar('  123456789012345 '));
        $this->assertSame([], PoliticaContrasena::validar('mate amargo en la terraza'));
    }

    public function testConfirmacion(): void
    {
        $this->assertCount(1, PoliticaContrasena::validar('mate amargo en la terraza', 'mate amargo en la terrazA'));
        $this->assertSame([], PoliticaContrasena::validar('mate amargo en la terraza', 'mate amargo en la terraza'));
    }

    public function testCincoFallosEnQuinceMinutosBloquean(): void
    {
        $c = new ControlIntentos();
        $t0 = new DateTimeImmutable('2026-10-28 10:00:00');
        $intentos = 0;
        $ventana = null;
        for ($i = 1; $i <= 4; $i++) {
            $r = $c->registrarFallo($intentos, $ventana, $t0->modify("+{$i} minutes"));
            $this->assertFalse($r['bloquear']);
            [$intentos, $ventana] = [$r['intentos_fallidos'], $r['inicio_ventana_intentos']];
        }
        $r = $c->registrarFallo($intentos, $ventana, $t0->modify('+14 minutes'));
        $this->assertTrue($r['bloquear']);
        $this->assertSame('2026-10-28 10:29:00', $r['bloqueada_hasta']->format('Y-m-d H:i:s'));
        $this->assertSame(5, $r['intentos_fallidos']);
    }

    public function testFueraDeLaVentanaElContadorReinicia(): void
    {
        $c = new ControlIntentos();
        $t0 = new DateTimeImmutable('2026-10-28 10:00:00');
        $r = $c->registrarFallo(4, $t0, $t0->modify('+16 minutes'));
        $this->assertFalse($r['bloquear']);
        $this->assertSame(1, $r['intentos_fallidos']);
    }

    public function testBloqueoVencido(): void
    {
        $c = new ControlIntentos();
        $hasta = new DateTimeImmutable('2026-10-28 10:29:00');
        $this->assertFalse($c->bloqueoVencido('BloqueadaTemporalmente', $hasta, $hasta->modify('-1 second')));
        $this->assertTrue($c->bloqueoVencido('BloqueadaTemporalmente', $hasta, $hasta));
        $this->assertFalse($c->bloqueoVencido('Desactivada', $hasta, $hasta->modify('+1 day')));
    }

    public function testMensajeAdviertePocosIntentosEInformaElBloqueo(): void
    {
        $c = new ControlIntentos();
        $g = 'Usuario o contraseña incorrectos.';
        $this->assertSame($g, $c->mensajeRechazo($g, 1, false));
        $this->assertSame($g, $c->mensajeRechazo($g, 2, false));
        $this->assertSame("{$g} Le quedan 2 intentos antes de que el acceso se bloquee durante 15 minutos.", $c->mensajeRechazo($g, 3, false));
        $this->assertSame("{$g} Le queda un intento: si vuelve a fallar, el acceso se bloqueará durante 15 minutos.", $c->mensajeRechazo($g, 4, false));
        $bloqueo = 'Por seguridad, el acceso quedó bloqueado durante 15 minutos por intentos fallidos. Vuelva a intentarlo más tarde.';
        $this->assertSame($bloqueo, $c->mensajeRechazo($g, 5, true));
        // Durante el bloqueo, aunque el contador venga de una ventana anterior
        $this->assertSame($bloqueo, $c->mensajeRechazo($g, 0, true));
    }

    public function testElMensajeSigueLaConfiguracionDeLaEntidad(): void
    {
        $c = new ControlIntentos(3, 600, 1800);
        $this->assertStringContainsString('Le queda un intento', $c->mensajeRechazo('X.', 2, false));
        $this->assertStringContainsString('30 minutos', $c->mensajeRechazo('X.', 3, true));
    }

    public function testCincoFallosSeguidosDanLaSecuenciaDeMensajes(): void
    {
        $c = new ControlIntentos();
        $t = new DateTimeImmutable('2026-10-29 16:00:00');
        $intentos = 0;
        $ventana = null;
        $mensajes = [];
        for ($i = 0; $i < 5; $i++) {
            $r = $c->registrarFallo($intentos, $ventana, $t->modify("+{$i} minutes"));
            [$intentos, $ventana] = [$r['intentos_fallidos'], $r['inicio_ventana_intentos']];
            $mensajes[] = $c->mensajeRechazo('G.', $r['intentos_fallidos'], $r['bloquear']);
        }
        $this->assertSame(['G.', 'G.'], array_slice($mensajes, 0, 2));
        $this->assertStringContainsString('Le quedan 2 intentos', $mensajes[2]);
        $this->assertStringContainsString('Le queda un intento', $mensajes[3]);
        $this->assertStringStartsWith('Por seguridad, el acceso quedó bloqueado', $mensajes[4]);
    }
}
