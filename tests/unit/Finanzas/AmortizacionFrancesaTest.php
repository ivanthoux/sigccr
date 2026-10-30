<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Amortización francesa (RN-50, RN-51, RN-68). Valores esperados: tests/referencia/resultados_referencia.json,
 * calculados de manera independiente.
 */
final class AmortizacionFrancesaTest extends TestCase
{
    private function planRn68(): \App\Libraries\Finanzas\Plan
    {
        return AmortizacionFrancesa::plan(
            Decimal::of('100000'),
            Decimal::of('0.60'),
            6,
            new DateTimeImmutable('2026-01-10'),
            Decimal::of('500'),
            Decimal::of('0.21'),
        );
    }

    public function testCuotaOrdinariaDelCasoRn68(): void
    {
        $this->assertSame('19701.75', $this->planRn68()->cuotaOrdinaria->toFixed(2));
    }

    public function testPrimeraCuotaDelCasoRn68(): void
    {
        $c = $this->planRn68()->cuotas[0]->toArray();
        $this->assertSame('14701.75', $c['capital']);
        $this->assertSame('5000.00', $c['interes']);
        $this->assertSame('500.00', $c['gastos']);
        $this->assertSame('1155.00', $c['iva']);
        $this->assertSame('21356.75', $c['total']);
    }

    public function testPlanCompletoCoincideConElCalculoIndependiente(): void
    {
        $esperado = [
            ['2026-02-09', '14701.75', '5000.00', '1155.00', '21356.75'],
            ['2026-03-09', '15436.84', '4264.91', '1000.63', '21202.38'],
            ['2026-04-09', '16208.68', '3493.07', '838.54', '21040.29'],
            ['2026-05-09', '17019.11', '2682.64', '668.35', '20870.10'],
            ['2026-06-09', '17870.07', '1831.68', '489.65', '20691.40'],
            ['2026-07-09', '18763.55', '938.18', '302.02', '20503.75'],
        ];
        foreach ($this->planRn68()->cuotas as $k => $c) {
            $a = $c->toArray();
            $this->assertSame($esperado[$k], [$a['vencimiento'], $a['capital'], $a['interes'], $a['iva'], $a['total']], "cuota " . ($k + 1));
        }
    }

    public function testAjusteFinalLaSumaDeCapitalEsExacta(): void
    {
        $plan = $this->planRn68();
        $this->assertSame('100000.00', $plan->totalCapital()->toFixed(2));
        $this->assertSame('125664.67', $plan->totalAPagar()->toFixed(2));
        // la última cuota absorbe la diferencia: su capital difiere de cuota − interés
        $ultima = $plan->cuotas[5];
        $this->assertSame('18763.55', $ultima->capital->toFixed(2));
        $this->assertSame('19701.73', $ultima->capital->add($ultima->interes)->toFixed(2));
    }

    public function testTasaCero(): void
    {
        $plan = AmortizacionFrancesa::plan(Decimal::of('1000'), Decimal::zero(), 3, new DateTimeImmutable('2026-01-10'));
        $this->assertSame('333.33', $plan->cuotaOrdinaria->toFixed(2));
        $this->assertSame(['333.33', '333.33', '333.34'], array_map(static fn ($c) => $c->capital->toFixed(2), $plan->cuotas));
        $this->assertSame('0.00', $plan->totalIntereses()->toFixed(2));
    }

    public function testUnaSolaCuota(): void
    {
        $plan = AmortizacionFrancesa::plan(Decimal::of('10000'), Decimal::of('0.60'), 1, new DateTimeImmutable('2026-01-10'));
        $this->assertSame('10500.00', $plan->cuotas[0]->total()->toFixed(2));
    }

    public function testRechazaCapitalNoPositivo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AmortizacionFrancesa::plan(Decimal::zero(), Decimal::of('0.60'), 6, new DateTimeImmutable('2026-01-10'));
    }

    public function testRechazaCantidadDeCuotasInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AmortizacionFrancesa::cuotaOrdinaria(Decimal::of('1000'), Decimal::of('0.60'), 0);
    }
}
