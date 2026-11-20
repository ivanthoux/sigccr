<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * S-11 · Configuración del servidor web (RNF-12; OWASP ASVS 5.0 nivel 1: V3.4.1, V3.2.1, V12.1.1, V12.2.1, V13.4.1).
 * Se verifica el archivo de nginx que usa la entrega, que es lo que protege a la aplicación en producción. Plan de pruebas 7.10.
 *
 * @internal
 */
final class ConfiguracionServidorTest extends CIUnitTestCase
{
    private string $conf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conf = (string) file_get_contents(ROOTPATH . 'docker/nginx/default.conf');
    }

    public function testSoloTlsActual(): void
    {
        $this->assertMatchesRegularExpression('/ssl_protocols\s+TLSv1\.2 TLSv1\.3;/', $this->conf, 'V12.1.1');
    }

    public function testElPuertoHttpSoloRedirigeAHttps(): void
    {
        $this->assertMatchesRegularExpression('/return 301 https:\/\/\$host\$request_uri;/', $this->conf, 'V12.2.1');
    }

    public function testCabecerasDeSeguridadEnTodasLasRespuestas(): void
    {
        $this->assertMatchesRegularExpression('/add_header Strict-Transport-Security "max-age=(\d+)[^"]*" always;/', $this->conf, 'V3.4.1');
        preg_match('/max-age=(\d+)/', $this->conf, $m);
        $this->assertGreaterThanOrEqual(15552000, (int) $m[1], 'HSTS: al menos 180 días.');
        $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $this->conf, 'V3.2.1');
    }

    public function testNoSeSirvenArchivosOcultos(): void
    {
        $this->assertMatchesRegularExpression('/location ~ \/\\\\\.\s*\{\s*deny all;/', $this->conf, 'V13.4.1: .git y similares');
    }
}
