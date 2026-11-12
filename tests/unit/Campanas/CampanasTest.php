<?php

declare(strict_types=1);

namespace Tests\Unit\Campanas;

use App\Exceptions\ValidacionException;
use App\Libraries\Campanas\ReglasCampanas;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\ValidadorParametrosCampanas as P;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Campañas crediticias (SS-08, D-43): parámetros de CU-51 y reglas de CU-52, CU-93 y CU-94 (RN-18, RN-40 a RN-43,
 * RN-64, RN-66).
 */
final class CampanasTest extends TestCase
{
    /** @return array<string, mixed> */
    private function formulario(): array
    {
        return [
            'umbral_disponibilidad' => '5.000.000', 'periodo_proyeccion_dias' => '30', 'factor_cobrabilidad' => '90', 'reserva_minima' => '1000000',
            'linea_id' => '4', 'capital_maximo' => '3.000.000,50', 'cantidad_maxima_ofertas' => '200', 'categorias_admitidas' => ['RelacionDependencia', 'Inventada'],
            'antiguedad_minima_meses' => '0', 'morosidad_maxima_dias' => '0', 'admite_obligaciones_vigentes' => '1', 'canales' => ['Correo', 'Paloma'],
            'mensaje' => ' Oferta de fin de año ', 'duracion_dias' => '15',
        ];
    }

    /** @return array{categoria_ingresos: string, fecha_alta: string, portal_habilitado: bool, consentimiento: bool, max_dias_atraso: int, creditos_vigentes: int, solicitud_en_tramite: bool} */
    private static function cliente(array $c = []): array
    {
        return $c + ['categoria_ingresos' => 'RelacionDependencia', 'fecha_alta' => '2026-01-10 12:00:00.000 +00:00', 'portal_habilitado' => true, 'consentimiento' => true,
            'max_dias_atraso' => 0, 'creditos_vigentes' => 1, 'solicitud_en_tramite' => false];
    }

    /** @return array{importe: Decimal, cuotas: int, viable: bool, primera_cuota: Decimal, tea: Decimal, cftea: Decimal} */
    private static function alternativa(string $importe, int $cuotas, bool $viable = true): array
    {
        return ['importe' => Decimal::of($importe), 'cuotas' => $cuotas, 'viable' => $viable, 'primera_cuota' => Decimal::of('1000'), 'tea' => Decimal::of('0.8'), 'cftea' => Decimal::of('1.1')];
    }

    public function testNormalizaYValidaLosParametros(): void
    {
        $p = P::normalizar($this->formulario());
        $this->assertSame('5000000.00', $p['umbral_disponibilidad']);
        $this->assertSame('0.9000', $p['factor_cobrabilidad']);
        $this->assertSame('3000000.50', $p['capital_maximo']);
        $this->assertSame(['RelacionDependencia'], $p['categorias_admitidas']);
        $this->assertSame(['Correo'], $p['canales']);
        $this->assertSame('Oferta de fin de año', $p['mensaje']);
        $this->assertTrue($p['admite_obligaciones_vigentes']);
        $this->assertFalse($p['cierre_por_disponibilidad']);
        $this->assertSame([], P::validar($p, [4, 7]));
    }

    public function testValoresIncoherentes(): void
    {
        $p = P::normalizar(['umbral_disponibilidad' => '-1', 'factor_cobrabilidad' => '120', 'capital_maximo' => '0', 'linea_id' => '9', 'periodo_proyeccion_dias' => '0', 'duracion_dias' => '91'] + $this->formulario());
        $e = P::validar($p, [4]);
        foreach (['umbral_disponibilidad', 'factor_cobrabilidad', 'capital_maximo', 'linea_id', 'periodo_proyeccion_dias', 'duracion_dias'] as $campo) {
            $this->assertArrayHasKey($campo, $e, $campo);
        }
        $e = P::validar(P::normalizar(['categorias_admitidas' => [], 'canales' => [], 'mensaje' => str_repeat('a', 501)] + $this->formulario()), [4]);
        $this->assertArrayHasKey('categorias_admitidas', $e);
        $this->assertArrayHasKey('canales', $e);
        $this->assertArrayHasKey('mensaje', $e);
    }

    public function testFormularioDesdeLaCargaInicial(): void
    {
        $f = P::aFormulario(['umbral_disponibilidad' => '5000000.00', 'periodo_proyeccion_dias' => 30, 'factor_cobrabilidad' => '0.90', 'reserva_minima' => '1000000.00', 'capital_maximo' => '3000000.00', 'duracion_dias' => 15]);
        $this->assertSame('5000000,00', $f['umbral_disponibilidad']);
        $this->assertSame('90', $f['factor_cobrabilidad']);
        $this->assertSame('', $f['linea_id']);
        $this->assertSame('200', $f['cantidad_maxima_ofertas']);
        $this->assertSame(['Correo'], $f['canales']);
        $this->assertTrue($f['cierre_por_disponibilidad']);
        $this->assertArrayHasKey('Empresa', P::categorias());
    }

    public function testCapitalAsignado(): void
    {
        $this->assertSame('3000000.00', ReglasCampanas::capitalAsignado('3000000.00', '7250000.00')->toFixed(2));
        $this->assertSame('1200000.00', ReglasCampanas::capitalAsignado('3000000.00', '1200000.00')->toFixed(2));
    }

    public function testElegibilidad(): void
    {
        $p = P::completar(['categorias_admitidas' => ['RelacionDependencia'], 'antiguedad_minima_meses' => 6]);
        $hoy = '2026-11-12';
        $this->assertNull(ReglasCampanas::motivoNoElegible(self::cliente(), $p, $hoy));
        $this->assertStringContainsString('portal', (string) ReglasCampanas::motivoNoElegible(self::cliente(['portal_habilitado' => false]), $p, $hoy));
        $this->assertStringContainsString('RN-64', (string) ReglasCampanas::motivoNoElegible(self::cliente(['consentimiento' => false]), $p, $hoy));
        $this->assertStringContainsString('Categoría', (string) ReglasCampanas::motivoNoElegible(self::cliente(['categoria_ingresos' => 'Monotributista']), $p, $hoy));
        $this->assertStringContainsString('Antigüedad', (string) ReglasCampanas::motivoNoElegible(self::cliente(['fecha_alta' => '2026-08-01']), $p, $hoy));
        $this->assertStringContainsString('Atraso', (string) ReglasCampanas::motivoNoElegible(self::cliente(['max_dias_atraso' => 2]), $p, $hoy));
        $this->assertNull(ReglasCampanas::motivoNoElegible(self::cliente(['max_dias_atraso' => 2]), ['morosidad_maxima_dias' => 3] + $p, $hoy));
        $this->assertStringContainsString('créditos vigentes', (string) ReglasCampanas::motivoNoElegible(self::cliente(), ['admite_obligaciones_vigentes' => false] + $p, $hoy));
        $this->assertStringContainsString('RN-18', (string) ReglasCampanas::motivoNoElegible(self::cliente(['solicitud_en_tramite' => true]), $p, $hoy));
        $this->assertSame(10, ReglasCampanas::mesesEntre('2026-01-10', $hoy));
        $this->assertSame(0, ReglasCampanas::mesesEntre('2026-12-01', $hoy));
    }

    public function testCondicionesDeLaOferta(): void
    {
        $c = ReglasCampanas::condicionesOferta([self::alternativa('900000', 24, false), self::alternativa('600000', 18), self::alternativa('600000', 12), self::alternativa('450000', 6)]);
        $this->assertNotNull($c);
        $this->assertSame('600000.00', $c['importe']->toFixed(2));
        $this->assertSame(12, $c['cuotas']);
        $this->assertNull(ReglasCampanas::condicionesOferta([self::alternativa('900000', 24, false)]));
    }

    public function testAlternativasAceptablesYEleccion(): void
    {
        $alt = [self::alternativa('300000', 6), self::alternativa('800000', 12), self::alternativa('600000', 12), self::alternativa('600000', 6), self::alternativa('400000', 9, false)];
        $ok = ReglasCampanas::aceptables($alt, '600000.00');
        $this->assertSame(['600000.00|6', '600000.00|12', '300000.00|6'], array_map([ReglasCampanas::class, 'clave'], $ok));
        $this->assertSame(12, ReglasCampanas::eleccion($ok, '600000.00|12')['cuotas']);
        try {
            ReglasCampanas::eleccion($ok, '800000.00|12');
            $this->fail('Debía rechazar una alternativa fuera de las permitidas (A4).');
        } catch (ValidacionException $e) {
            $this->assertStringContainsString('$ 600.000,00 en 6 cuotas', $e->errores['alternativa']);
        }
    }

    public function testSinAlternativasParaElegir(): void
    {
        $this->expectException(ValidacionException::class);
        ReglasCampanas::eleccion([], '600000.00|12');
    }

    public function testCausaDeCierre(): void
    {
        $ahora = new DateTimeImmutable('2026-11-12 12:00:00+00:00');
        $this->assertSame('Vigencia', ReglasCampanas::causaCierre('2026-11-12 11:59:00.000 +00:00', $ahora, '0', '1000'));
        $this->assertSame('Cupo', ReglasCampanas::causaCierre('2026-11-27 12:00:00.000 +00:00', $ahora, '1000.00', '1000.00'));
        $this->assertNull(ReglasCampanas::causaCierre('2026-11-27 12:00:00.000 +00:00', $ahora, '999.99', '1000.00'));
    }

    public function testResultados(): void
    {
        $r = ReglasCampanas::resultados([
            ['estado' => 'Aceptada', 'solicitud_importe' => '600000.00', 'liquidado' => '600000.00'],
            ['estado' => 'Aceptada', 'solicitud_importe' => '300000.00', 'liquidado' => null],
            ['estado' => 'Rechazada', 'solicitud_importe' => null],
            ['estado' => 'Vencida'],
        ]);
        $this->assertSame(4, $r['emitidas']);
        $this->assertSame(2, $r['aceptadas']);
        $this->assertSame(1, $r['rechazadas']);
        $this->assertSame(1, $r['vencidas']);
        $this->assertSame(2, $r['solicitudes']);
        $this->assertSame('900000.00', $r['capital_solicitado']);
        $this->assertSame('600000.00', $r['capital_colocado']);
        $this->assertSame('50.00', $r['tasa_aceptacion']);
        $this->assertSame('0.00', ReglasCampanas::resultados([])['tasa_aceptacion']);
    }
}
