<?php

declare(strict_types=1);

namespace Tests\Unit\Comunicaciones;

use App\Libraries\Comunicaciones\CifradoDatos;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * S-06 · Cifrado AES-256-GCM de los datos de las comunicaciones (ASVS V11.3.2).
 *
 * @internal
 */
final class CifradoDatosTest extends TestCase
{
    private const CLAVE = 'hex2bin:000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testCifraYDescifra(): void
    {
        $texto = '{"nombre":"Ana Gómez","enlace":"https://x/ñ"}';
        $cifrado = CifradoDatos::cifrar($texto, self::CLAVE);
        $this->assertTrue(CifradoDatos::lleva($cifrado));
        $this->assertStringNotContainsString('Gómez', $cifrado);
        $this->assertSame($texto, CifradoDatos::descifrar($cifrado, self::CLAVE));
    }

    public function testCadaCifradoUsaUnVectorDistinto(): void
    {
        $this->assertNotSame(CifradoDatos::cifrar('igual', self::CLAVE), CifradoDatos::cifrar('igual', self::CLAVE));
    }

    public function testUnDatoAlteradoNoSeDescifra(): void
    {
        $cifrado = CifradoDatos::cifrar('dato', self::CLAVE);
        $alterado = substr($cifrado, 0, -1) . ($cifrado[-1] === "\x00" ? "\x01" : "\x00");
        $this->expectException(RuntimeException::class);
        CifradoDatos::descifrar($alterado, self::CLAVE);
    }

    public function testOtraClaveNoDescifra(): void
    {
        $cifrado = CifradoDatos::cifrar('dato', self::CLAVE);
        $this->expectException(RuntimeException::class);
        CifradoDatos::descifrar($cifrado, 'hex2bin:' . str_repeat('ab', 32));
    }

    public function testLoCifradoConElCifradorAnteriorNoLlevaLaMarca(): void
    {
        $this->assertFalse(CifradoDatos::lleva("\x02" . random_bytes(80)));
    }

    public function testLaClaveDeLasComunicacionesNoEsLaDeLasCopias(): void
    {
        $this->assertNotSame(
            \App\Libraries\Copias\CifradoCopia::clave(self::CLAVE),
            \App\Libraries\Copias\CifradoCopia::derivar(self::CLAVE, 'sigccr-comunicaciones-v1'),
        );
    }
}
