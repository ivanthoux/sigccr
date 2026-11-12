<?php

declare(strict_types=1);

namespace Tests\Unit\Copias;

use App\Exceptions\CopiaCorruptaException;
use App\Libraries\Copias\CifradoCopia;
use App\Libraries\Copias\EstadoRestauracion;
use App\Libraries\Copias\PaqueteCopia;
use App\Libraries\Copias\PoliticaCopias;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Copias de seguridad (CU-61 a CU-63, RNF-13, D-45): cifrado, empaquetado, conservación y estado de la restauración. */
final class CopiasTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/copias_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function clave(): string
    {
        return CifradoCopia::clave('hex2bin:' . str_repeat('ab', 32));
    }

    public function testCifradoEnVariosBloques(): void
    {
        $plano = random_bytes(CifradoCopia::BLOQUE * 2 + 12345);
        file_put_contents("{$this->dir}/a.bin", $plano);
        CifradoCopia::cifrar("{$this->dir}/a.bin", "{$this->dir}/a.sccr", $this->clave());
        $cifrado = (string) file_get_contents("{$this->dir}/a.sccr");
        $this->assertStringStartsWith(CifradoCopia::CABECERA, $cifrado);
        $this->assertFalse(str_contains($cifrado, substr($plano, 1000, 64)));
        $this->assertSame(strlen($plano) + strlen(CifradoCopia::CABECERA) + 3 * 33, strlen($cifrado));
        CifradoCopia::descifrar("{$this->dir}/a.sccr", "{$this->dir}/b.bin", $this->clave());
        $this->assertSame(hash('sha256', $plano), hash_file('sha256', "{$this->dir}/b.bin"));
    }

    public function testArchivoVacioYBloqueExacto(): void
    {
        foreach (['' => 1, str_repeat('x', CifradoCopia::BLOQUE) => 1] as $plano => $bloques) {
            file_put_contents("{$this->dir}/a.bin", $plano);
            CifradoCopia::cifrar("{$this->dir}/a.bin", "{$this->dir}/a.sccr", $this->clave());
            $this->assertSame(strlen($plano) + strlen(CifradoCopia::CABECERA) + $bloques * 33, strlen((string) file_get_contents("{$this->dir}/a.sccr")));
            CifradoCopia::descifrar("{$this->dir}/a.sccr", "{$this->dir}/b.bin", $this->clave());
            $this->assertSame($plano, file_get_contents("{$this->dir}/b.bin"));
        }
    }

    public function testCopiaAlteradaTruncadaOConOtraClave(): void
    {
        file_put_contents("{$this->dir}/a.bin", random_bytes(CifradoCopia::BLOQUE + 500));
        CifradoCopia::cifrar("{$this->dir}/a.bin", "{$this->dir}/a.sccr", $this->clave());
        $original = (string) file_get_contents("{$this->dir}/a.sccr");
        $alterada = $original;
        $alterada[200] = $alterada[200] === 'A' ? 'B' : 'A';
        $casos = [
            'alterada' => $alterada,
            'truncada' => substr($original, 0, strlen(CifradoCopia::CABECERA) + 17 + CifradoCopia::BLOQUE + 16),
            'cortada' => substr($original, 0, -10),
            'agregada' => $original . 'x',
            'ajena' => 'No es una copia',
        ];
        foreach ($casos as $caso => $contenido) {
            file_put_contents("{$this->dir}/c.sccr", $contenido);
            try {
                CifradoCopia::descifrar("{$this->dir}/c.sccr", "{$this->dir}/c.bin", $this->clave());
                $this->fail("Debía rechazar la copia {$caso}.");
            } catch (CopiaCorruptaException) {
                $this->assertFalse(is_file("{$this->dir}/c.bin"), $caso);
            }
        }
        $this->expectException(CopiaCorruptaException::class);
        CifradoCopia::descifrar("{$this->dir}/a.sccr", "{$this->dir}/d.bin", CifradoCopia::clave('otra clave de la aplicación, distinta'));
    }

    public function testClaveDerivada(): void
    {
        $this->assertSame(32, strlen($this->clave()));
        $this->assertTrue(str_repeat("\xab", 32) !== $this->clave());
        $this->assertSame(CifradoCopia::clave('base64:' . base64_encode(str_repeat("\xab", 32))), $this->clave());
        $this->expectException(\RuntimeException::class);
        CifradoCopia::clave('');
    }

    public function testPaqueteConAdjuntos(): void
    {
        $doc = 'legajos/5/' . str_repeat('ab', 16) . '.pdf';
        mkdir("{$this->dir}/origen/legajos/5", 0777, true);
        file_put_contents("{$this->dir}/origen/{$doc}", 'PDF');
        file_put_contents("{$this->dir}/base.bak", 'BACKUP');
        // Solo se incluyen las rutas válidas que existen
        $this->assertSame(1, PaqueteCopia::armar("{$this->dir}/base.bak", "{$this->dir}/origen", [$doc, $doc, 'legajos/5/no-existe.pdf', '../../etc/passwd'], "{$this->dir}/p.zip"));
        PaqueteCopia::extraerBase("{$this->dir}/p.zip", "{$this->dir}/r.bak");
        $this->assertSame('BACKUP', file_get_contents("{$this->dir}/r.bak"));
        mkdir("{$this->dir}/destino");
        $this->assertSame(1, PaqueteCopia::extraerAdjuntos("{$this->dir}/p.zip", "{$this->dir}/destino", [$doc, 'legajos/9/' . str_repeat('cd', 16) . '.pdf']));
        $this->assertSame('PDF', file_get_contents("{$this->dir}/destino/{$doc}"));
        $this->assertSame(0, PaqueteCopia::armar("{$this->dir}/base.bak", null, [$doc], "{$this->dir}/q.zip"));
        $this->assertSame(0, PaqueteCopia::extraerAdjuntos("{$this->dir}/q.zip", "{$this->dir}/destino", [$doc]));
    }

    public function testPaqueteInvalido(): void
    {
        // Los nombres del paquete nunca se usan como ruta: solo las rutas que registra la base restaurada
        $zip = new \ZipArchive();
        $zip->open("{$this->dir}/m.zip", \ZipArchive::CREATE);
        $zip->addFromString(PaqueteCopia::BASE, 'B');
        $zip->addFromString('adjuntos/../../fuera.txt', 'X');
        $zip->close();
        mkdir("{$this->dir}/adj");
        $this->assertSame(0, PaqueteCopia::extraerAdjuntos("{$this->dir}/m.zip", "{$this->dir}/adj", ['../../fuera.txt', 'adjuntos/../../fuera.txt']));
        $this->assertFalse(is_file(dirname($this->dir) . '/fuera.txt'));
        $this->assertTrue(PaqueteCopia::rutaValida('legajos/12/' . str_repeat('0f', 16) . '.jpeg'));
        $this->assertFalse(PaqueteCopia::rutaValida('legajos/12/../x.pdf'));
        $this->assertFalse(PaqueteCopia::rutaValida('/etc/legajos/1/' . str_repeat('0f', 16) . '.pdf'));
        $zip->open("{$this->dir}/n.zip", \ZipArchive::CREATE);
        $zip->addFromString('otra.txt', 'X');
        $zip->close();
        $this->expectException(CopiaCorruptaException::class);
        PaqueteCopia::extraerBase("{$this->dir}/n.zip", "{$this->dir}/n.bak");
    }

    public function testPoliticaDeCopias(): void
    {
        $nombre = PoliticaCopias::nombreArchivo(new DateTimeImmutable('2026-11-12 02:00:05', new \DateTimeZone('America/Argentina/Buenos_Aires')), 'Automatica', 'A1-b2');
        $this->assertSame('sigccr_20261112_050005_automatica_a1b2.sccr', $nombre);
        $this->assertTrue(PoliticaCopias::nombreValido($nombre));
        $this->assertFalse(PoliticaCopias::nombreValido('../etc/passwd'));
        $this->assertSame(2 * 3000 + PoliticaCopias::MARGEN_BYTES, PoliticaCopias::espacioRequerido(2000, 1000));
        $this->assertTrue(PoliticaCopias::confirmacionValida(' restaurar '));
        $this->assertFalse(PoliticaCopias::confirmacionValida('si'));
        $this->assertSame('1,5 MB', PoliticaCopias::tamano(1572864));
        $this->assertSame('512 B', PoliticaCopias::tamano(512));
        $this->assertSame('—', PoliticaCopias::tamano(null));
        $this->assertSame(['1 cobro', '12 registros de auditoría'], PoliticaCopias::posteriores(['cobros' => 1, 'auditoria' => 12, 'solicitudes' => 0]));
    }

    public function testConservacionDeTreintaDias(): void
    {
        $ahora = new DateTimeImmutable('2026-11-12 12:00:00+00:00');
        $c = static fn (int $id, string $fecha, string $estado = 'Verificada'): array => ['id' => $id, 'estado' => $estado, 'fecha_generacion' => $fecha];
        $copias = [$c(1, '2026-09-01 05:00:00.000 +00:00'), $c(2, '2026-10-12 11:00:00.000 +00:00'), $c(3, '2026-10-13 05:00:00.000 +00:00'), $c(4, '2026-08-01 05:00:00.000 +00:00', 'Fallida')];
        $this->assertSame([2, 1], PoliticaCopias::aDepurar($copias, $ahora));
        // Si todas son viejas se conserva la más reciente
        $this->assertSame([1], PoliticaCopias::aDepurar([$c(1, '2026-09-01 05:00:00.000 +00:00'), $c(2, '2026-09-02 05:00:00.000 +00:00')], $ahora));
        $this->assertSame([], PoliticaCopias::aDepurar([], $ahora));
    }

    public function testManifiesto(): void
    {
        $fila = ['archivo' => 'sigccr_20261112_050005_manual_ab12.sccr', 'fecha_generacion' => '2026-11-12 05:00:05.000 +00:00', 'origen' => 'Manual', 'hash_verificacion' => str_repeat('a', 64),
            'tamano_bytes' => 100, 'descripcion' => 'Antes del cierre', 'responsable' => 'Pérez, Ana', 'incluye_adjuntos' => 1, 'usuario_id' => 3, 'id' => 9];
        $m = PoliticaCopias::leerManifiesto(PoliticaCopias::manifiesto($fila));
        $this->assertNotNull($m);
        $this->assertSame('Pérez, Ana', $m['responsable']);
        $this->assertArrayNotHasKey('usuario_id', $m);
        $this->assertArrayNotHasKey('id', $m);
        $this->assertNull(PoliticaCopias::leerManifiesto(PoliticaCopias::manifiesto(['archivo' => '../x.sccr'] + $fila)));
        $this->assertNull(PoliticaCopias::leerManifiesto(PoliticaCopias::manifiesto(['hash_verificacion' => 'x'] + $fila)));
        $this->assertNull(PoliticaCopias::leerManifiesto('no es json'));
    }

    public function testEstadoDeLaRestauracion(): void
    {
        $e = new EstadoRestauracion("{$this->dir}/copias");
        $this->assertNull($e->actual());
        $this->assertFalse($e->activa());
        $e->guardar(['estado' => 'Pendiente', 'archivo' => 'x.sccr']);
        $this->assertTrue($e->activa());
        $e->guardar(['estado' => 'EnCurso', 'etapa' => 'Restaurando la base de datos']);
        $this->assertSame('Restaurando la base de datos', $e->actual()['etapa']);
        $this->assertTrue($e->activa());
        $e->finalizar(['estado' => 'Finalizada', 'exitosa' => true, 'mensaje' => 'Base restaurada.']);
        $this->assertFalse($e->activa());
        $this->assertNull($e->actual());
        $this->assertSame('Base restaurada.', $e->ultima()['mensaje']);
    }
}
