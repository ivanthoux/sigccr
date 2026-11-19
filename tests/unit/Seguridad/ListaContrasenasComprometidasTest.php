<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Seguridad\PoliticaContrasena;
use PHPUnit\Framework\TestCase;

/**
 * S-07 · Lista de contraseñas comprometidas con al menos 3.000 entradas que cumplen la política (ASVS V6.2.4, RN-62).
 *
 * @internal
 */
final class ListaContrasenasComprometidasTest extends TestCase
{
    /** @return list<string> */
    private function entradas(): array
    {
        $lineas = file(dirname(__DIR__, 3) . '/app/Libraries/Seguridad/contrasenas_comprometidas.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_filter($lineas, static fn (string $l): bool => !str_starts_with($l, '#')));
    }

    public function testTieneAlMenosTresMilEntradas(): void
    {
        $this->assertGreaterThanOrEqual(3000, count(array_unique(array_map('mb_strtolower', $this->entradas()))));
    }

    public function testTodasCumplenElLargoDeLaPolitica(): void
    {
        foreach ($this->entradas() as $e) {
            $this->assertGreaterThanOrEqual(PoliticaContrasena::MINIMO, mb_strlen($e), "«{$e}» es más corta que el mínimo.");
            $this->assertLessThanOrEqual(PoliticaContrasena::MAXIMO, mb_strlen($e));
        }
    }

    public function testRechazaUnaEntradaDeLaListaSinImportarLasMayusculas(): void
    {
        $e = $this->entradas()[2500];
        $this->assertMatchesRegularExpression('/^[\x20-\x7e]+$/', $e, 'La prueba usa una entrada ASCII.');
        $this->assertCount(1, PoliticaContrasena::validar($e));
        $this->assertCount(1, PoliticaContrasena::validar(mb_strtoupper($e)));
    }

    public function testAceptaUnaContrasenaLargaQueNoEstaEnLaLista(): void
    {
        $this->assertSame([], PoliticaContrasena::validar('Cielo.Gris.Montaña.9271'));
    }
}
