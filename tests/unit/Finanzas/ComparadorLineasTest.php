<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\ComparadorLineas;
use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Simulación comparativa de líneas (CU-99, D-10). */
final class ComparadorLineasTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function lineas(): array
    {
        return [
            ['id' => 1, 'nombre' => 'Motos', 'tna' => '0.70', 'gasto_administrativo_cuota' => '500', 'gasto_otorgamiento_pct' => '0.02',
                'monto_minimo' => '300000', 'monto_maximo' => '1500000', 'cuotas_minimas' => 6, 'cuotas_maximas' => 24],
            ['id' => 2, 'nombre' => 'Personal', 'tna' => '0.90', 'gasto_administrativo_cuota' => '800', 'gasto_otorgamiento_pct' => '0.03',
                'monto_minimo' => '100000', 'monto_maximo' => '800000', 'cuotas_minimas' => 3, 'cuotas_maximas' => 18],
            ['id' => 3, 'nombre' => 'Electrodomésticos', 'tna' => '0.50', 'gasto_administrativo_cuota' => '0', 'gasto_otorgamiento_pct' => '0',
                'monto_minimo' => '50000', 'monto_maximo' => '400000', 'cuotas_minimas' => 3, 'cuotas_maximas' => 12],
            ['id' => 4, 'nombre' => 'Pymes', 'tna' => '0.60', 'gasto_administrativo_cuota' => '0', 'gasto_otorgamiento_pct' => '0',
                'monto_minimo' => '500000', 'monto_maximo' => '5000000', 'cuotas_minimas' => 6, 'cuotas_maximas' => 36,
                'motivo_no_admitida' => 'La línea admite solo personas jurídicas.'],
        ];
    }

    private function comparar(?string $pedido = null, ?int $cuotas = null, string $criterio = ComparadorLineas::CRITERIO_MONTO): array
    {
        return ComparadorLineas::comparar(
            Decimal::of('150000'),
            $this->lineas(),
            Decimal::of('0.21'),
            Decimal::of('1000'),
            new DateTimeImmutable('2026-11-02'),
            $pedido === null ? null : Decimal::of($pedido),
            $cuotas,
            $criterio,
        );
    }

    public function testCalculaElMontoMaximoDeCadaLineaDentroDeSusLimites(): void
    {
        $r = $this->comparar();
        $this->assertCount(3, $r['lineas']);
        $porId = array_column($r['lineas'], null, 'id');
        // la capacidad alcanza más que el máximo de Personal y de Electrodomésticos: se limitan a su tope (D-09)
        $this->assertSame('800000.00', $porId[2]['monto_maximo']->toFixed(2));
        $this->assertSame('400000.00', $porId[3]['monto_maximo']->toFixed(2));
        foreach ($r['lineas'] as $l) {
            $this->assertLessThanOrEqual(0, $l['primera_cuota']->compare('150000'));
            $this->assertTrue($l['importe']->compare($l['monto_maximo']) <= 0);
        }
        $this->assertSame(1, $r['mayor_monto']);
        $this->assertSame(1, $r['lineas'][0]['id']);
    }

    public function testLineaNoAdmitidaSeInformaConSuMotivo(): void
    {
        $r = $this->comparar();
        $this->assertCount(1, $r['no_viables']);
        $this->assertSame(4, $r['no_viables'][0]['id']);
        $this->assertSame('La línea admite solo personas jurídicas.', $r['no_viables'][0]['motivo']);
    }

    public function testImportePedidoEligeLaMenorCantidadDeCuotasQuePuedePagar(): void
    {
        $r = $this->comparar('900000');
        $porId = array_column($r['lineas'], null, 'id');
        $motos = $porId[1];
        $this->assertSame('900000.00', $motos['importe']->toFixed(2));
        // con una cuota menos, la primera cuota superaría la capacidad
        $this->assertGreaterThan(6, $motos['cuotas']);
        $this->assertLessThanOrEqual(0, $motos['primera_cuota']->compare('150000'));
        $anterior = \App\Libraries\Finanzas\AmortizacionFrancesa::plan(
            Decimal::of('900000'),
            Decimal::of('0.70'),
            $motos['cuotas'] - 1,
            new DateTimeImmutable('2026-11-02'),
            Decimal::of('500'),
            Decimal::of('0.21'),
        );
        $this->assertGreaterThan(0, $anterior->cuotaTotalMaxima()->compare('150000'));
    }

    public function testMenorCostoYOrdenPorCosto(): void
    {
        $r = $this->comparar('300000', null, ComparadorLineas::CRITERIO_COSTO);
        // Electrodomésticos: menor TNA y sin gastos
        $this->assertSame(3, $r['menor_costo']);
        $this->assertSame(3, $r['lineas'][0]['id']);
        for ($i = 1; $i < count($r['lineas']); $i++) {
            $this->assertLessThanOrEqual(0, $r['lineas'][$i - 1]['cftea']->compare($r['lineas'][$i]['cftea']));
        }
    }

    public function testImporteMayorAlMaximoSeLimitaConNota(): void
    {
        $r = $this->comparar('1000000');
        $porId = array_column($r['lineas'], null, 'id');
        $this->assertSame('400000.00', $porId[3]['importe']->toFixed(2));
        $this->assertStringContainsString('máximo de la línea', (string) $porId[3]['nota']);
    }

    public function testImporteMenorAlMinimoNoEsViable(): void
    {
        $r = $this->comparar('200000');
        $motivos = array_column($r['no_viables'], 'motivo', 'id');
        $this->assertStringContainsString('menor que el mínimo', $motivos[1]);
        $this->assertCount(2, $r['lineas']);
    }

    public function testCuotasPedidasFueraDeRangoOSinCapacidad(): void
    {
        $r = $this->comparar('400000', 24);
        $motivos = array_column($r['no_viables'], 'motivo', 'id');
        $this->assertStringContainsString('no admite 24 cuotas', $motivos[2]);
        $this->assertStringContainsString('no admite 24 cuotas', $motivos[3]);
        $this->assertSame(24, $r['lineas'][0]['cuotas']);
    }

    public function testSinCapacidadNingunaLineaEsViable(): void
    {
        $r = ComparadorLineas::comparar(Decimal::zero(), $this->lineas(), Decimal::of('0.21'), Decimal::of('1000'), new DateTimeImmutable('2026-11-02'));
        $this->assertSame([], $r['lineas']);
        $this->assertCount(4, $r['no_viables']);
        $this->assertNull($r['mayor_monto']);
    }
}
