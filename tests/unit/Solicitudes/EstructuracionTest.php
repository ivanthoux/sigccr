<?php

declare(strict_types=1);

namespace Tests\Unit\Solicitudes;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Solicitudes\Estructurador;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** D03: estructuración y alternativas de financiación (CU-87) y conformidad del cliente (CU-79). */
final class EstructuracionTest extends TestCase
{
    private const VERSION = [
        'tna' => '0.60', 'gasto_otorgamiento_pct' => '0.02', 'gasto_administrativo_cuota' => '500.00',
        'monto_minimo' => '50000.00', 'monto_maximo' => '1500000.00', 'cuotas_minimas' => 3, 'cuotas_maximas' => 24,
    ];

    private const PARAMETROS = ['porcentaje_afectacion' => '0.30', 'alicuota_iva' => '0.21', 'paso_redondeo' => '1000', 'escalones' => ['1', '0.75', '0.5']];

    private function generar(string $ingresos, string $obligaciones = '0', string $pedido = '500000.00', int $cuotas = 12): array
    {
        return Estructurador::generar(self::VERSION, self::PARAMETROS, Decimal::of($ingresos), Decimal::of($obligaciones), $pedido, $cuotas, new DateTimeImmutable('2026-11-04'));
    }

    public function testPlazosSugeridosDentroDeLaLinea(): void
    {
        $this->assertSame([3, 6, 9, 12, 18, 24], Estructurador::plazosSugeridos(3, 24));
        $this->assertSame([4, 6, 9, 10], Estructurador::plazosSugeridos(4, 10));
        $this->assertSame([12], Estructurador::plazosSugeridos(12, 12));
    }

    public function testCapacidadMontoMaximoYAlternativaPedidaPrimero(): void
    {
        // 620.000 × 30 % − 95.000 = 91.000 de capacidad
        $r = $this->generar('620000', '95000');
        $this->assertSame('91000.00', $r['capacidad']->toFixed(2));
        $this->assertNull($r['motivo']);
        $primera = $r['alternativas'][0];
        $this->assertTrue($primera['pedida']);
        $this->assertSame('500000.00', $primera['importe']->toFixed(2));
        $this->assertSame(12, $primera['cuotas']);
        foreach ($r['alternativas'] as $a) {
            // Toda alternativa viable entra en la capacidad; las descartadas la superan y dicen por qué
            $this->assertSame($a['viable'], $a['primera_cuota']->compare($r['capacidad']) <= 0);
            $this->assertSame($a['viable'], $a['motivo'] === null);
            $this->assertLessThanOrEqual(0, $a['importe']->compare($r['monto_maximo']));
        }
        // El monto máximo es viable en el plazo más largo y no supera el máximo de la línea
        $plan = AmortizacionFrancesa::plan($r['monto_maximo'], Decimal::of('0.60'), 24, new DateTimeImmutable('2026-11-04'), Decimal::of('500'), Decimal::of('0.21'));
        $this->assertLessThanOrEqual(0, $plan->cuotaTotalMaxima()->compare($r['capacidad']));
    }

    public function testElMaximoDeLaLineaLimitaElMontoMaximo(): void
    {
        $r = $this->generar('20000000');
        $this->assertSame('1500000.00', $r['monto_maximo']->toFixed(2));
    }

    public function testElTopeGlobalPorClienteLimitaElMontoMaximo(): void
    {
        $r = Estructurador::generar(self::VERSION, self::PARAMETROS + ['tope_global_cliente' => '300000.00'], Decimal::of('20000000'), Decimal::zero(), '200000.00', 12, new DateTimeImmutable('2026-11-04'));
        $this->assertSame('300000.00', $r['monto_maximo']->toFixed(2));
        $r = Estructurador::generar(self::VERSION, self::PARAMETROS + ['tope_global_cliente' => '40000.00'], Decimal::of('20000000'), Decimal::zero(), '200000.00', 12, new DateTimeImmutable('2026-11-04'));
        $this->assertStringContainsString('A2', (string) $r['motivo']);
    }

    public function testSinCapacidadOSinMinimoNoHayAlternativas(): void
    {
        $r = $this->generar('300000', '95000');
        $this->assertSame([], $r['alternativas']);
        $this->assertStringContainsString('A1', (string) $r['motivo']);
        $r = $this->generar('10000');
        $this->assertStringContainsString('A2', (string) $r['motivo']);
        $this->assertStringContainsString('$ 50.000,00', (string) $r['motivo']);
        $this->assertStringContainsString('A3', (string) $this->generar('0')['motivo']);
    }

    public function testPedidoSuperiorAlMaximoNoSeIncluyeComoPedida(): void
    {
        $r = $this->generar('300000', '0', '1400000.00', 6);
        $this->assertFalse(in_array(true, array_column($r['alternativas'], 'pedida'), true));
    }

    public function testConformidadDelCliente(): void
    {
        $ok = Estructurador::validarConformidad(['fecha_conformidad' => '04/11/2026', 'medio_conformidad' => 'PresencialFirma', 'observaciones' => 'Firmó la constancia.'], '2026-11-04', '2026-11-02 12:00:00');
        $this->assertSame([], $ok['errores']);
        $this->assertSame('2026-11-04', $ok['datos']['fecha']);
        $e = Estructurador::validarConformidad(['fecha_conformidad' => '05/11/2026', 'medio_conformidad' => 'Telefono'], '2026-11-04', '2026-11-02 12:00:00')['errores'];
        $this->assertSame(['fecha_conformidad', 'medio_conformidad'], array_keys($e));
        $e = Estructurador::validarConformidad(['fecha_conformidad' => '01/11/2026', 'medio_conformidad' => 'Digital'], '2026-11-04', '2026-11-02 12:00:00')['errores'];
        $this->assertStringContainsString('anterior a la generación', $e['fecha_conformidad']);
    }
}
