<?php

declare(strict_types=1);

namespace Tests\Unit\Clientes;

use App\Adapters\Padron\BcraPadron;
use App\Adapters\Padron\SimuladorPadron;
use App\Adapters\ServicioNoDisponibleException;
use App\Libraries\Clientes\DatosPadron;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * D-59: al ingresar el CUIT o CUIL se propone solo el nombre y el apellido o la razón social, según la Central de
 * Deudores del BCRA, y el DNI que contiene el CUIL (CU-11 A9).
 */
final class PadronTest extends TestCase
{
    public function testPersonaHumanaApellidoPrimeroYDniDelCuil(): void
    {
        $this->assertSame(
            ['tipo_cliente' => 'PersonaHumana', 'tipo_documento' => 'DNI', 'nro_documento' => '40222309', 'apellido' => 'Perez', 'nombre' => 'Gomez Ana María'],
            DatosPadron::desde('20402223090', '  PEREZ   GOMEZ ANA MARÍA '),
        );
        $this->assertSame('Ñandú', DatosPadron::desde('27394445520', 'ÑANDÚ')['apellido']);
        $this->assertSame('', DatosPadron::desde('27394445520', 'ÑANDÚ')['nombre']);
        // DNI de siete dígitos: sin el cero de la izquierda
        $this->assertSame('5123456', DatosPadron::dniDelCuil('23051234569'));
    }

    public function testSinLaPersonaSoloElDniDelCuil(): void
    {
        $this->assertSame(['tipo_cliente' => 'PersonaHumana', 'tipo_documento' => 'DNI', 'nro_documento' => '41555104'], DatosPadron::desde('27415551040', null));
        $this->assertSame(['tipo_cliente' => 'PersonaJuridica'], DatosPadron::desde('30712345671', null));
    }

    public function testPersonaJuridicaRazonSocialSinCambios(): void
    {
        $this->assertSame(['tipo_cliente' => 'PersonaJuridica', 'razon_social' => 'YERBATERA APÓSTOLES S.R.L.'], DatosPadron::desde('30712345671', 'YERBATERA APÓSTOLES S.R.L.'));
        $this->assertFalse(DatosPadron::esPersonaHumana('33712345679'));
        $this->assertTrue(DatosPadron::esPersonaHumana('24123456781'));
    }

    public function testSimuladorComoLaCentralDeDeudores(): void
    {
        $p = new SimuladorPadron();
        $this->assertSame('DÍAZ MARTINA', $p->consultar('27394445520'));
        $this->assertSame($p->consultar('20301112225'), $p->consultar('20301112225'));
        $this->assertMatchesRegularExpression('/ (S\.A\.|S\.R\.L\.|S\.A\.S\.)$/', (string) $p->consultar('30712345671'));
        $this->assertNull($p->consultar('20981112223'));
        $this->expectException(ServicioNoDisponibleException::class);
        $p->consultar('20991112227');
    }

    public function testCentralDeDeudoresDenominacionYNoEncontrado(): void
    {
        $urls = [];
        $respuestas = [
            ['status' => 200, 'body' => '{"status":200,"results":{"identificacion":20402223090,"denominacion":"ACOSTA  MATEO","periodos":[]}}'],
            ['status' => 404, 'body' => '{"status":404,"errorMessages":["No se encontró datos para la identificación ingresada."]}'],
        ];
        $bcra = new BcraPadron('https://bcra.prueba/v1.0/', 10, static function (string $url) use (&$urls, &$respuestas): array {
            $urls[] = $url;

            return array_shift($respuestas);
        });
        $this->assertSame('ACOSTA MATEO', $bcra->consultar('20402223090'));
        $this->assertNull($bcra->consultar('27415551040'));
        $this->assertSame(['https://bcra.prueba/v1.0/Deudas/20402223090', 'https://bcra.prueba/v1.0/Deudas/27415551040'], $urls);
    }

    /** Sin respuesta, con otro código o sin denominación: el servicio no está disponible, sin reintentos. */
    public function testCentralDeDeudoresNoDisponible(): void
    {
        $casos = [
            static fn (): array => throw new RuntimeException('timeout'),
            static fn (): array => ['status' => 503, 'body' => ''],
            static fn (): array => ['status' => 200, 'body' => '{"status":200,"results":{"denominacion":"  "}}'],
            static fn (): array => ['status' => 200, 'body' => 'no es json'],
        ];
        foreach ($casos as $i => $http) {
            $llamadas = 0;
            $bcra = new BcraPadron('https://bcra.prueba', 10, static function (string $url) use ($http, &$llamadas): array {
                $llamadas++;

                return $http();
            });
            try {
                $bcra->consultar('20402223090');
                $this->fail("caso {$i}: se esperaba ServicioNoDisponibleException");
            } catch (ServicioNoDisponibleException) {
                $this->assertSame(1, $llamadas, "caso {$i}");
            }
        }
        $this->expectException(ServicioNoDisponibleException::class);
        $bcra->consultar('2040222309');
    }
}
