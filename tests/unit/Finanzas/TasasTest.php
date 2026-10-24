<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Tasas;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

/** TEA, CFTEA e importe neto a desembolsar (RN-25, RN-63, RN-68). */
final class TasasTest extends TestCase
{
    public function testTeaDelCasoRn68(): void
    {
        $this->assertSame('79.59', Tasas::tea(Decimal::of('0.60'))->toPercent());
    }

    public function testDesembolsoDelCasoRn68(): void
    {
        $d = Tasas::desembolso(Decimal::of('100000'), Decimal::of('0.02'), Decimal::of('0.21'));
        $this->assertSame('2000.00', $d['gasto']->toFixed(2));
        $this->assertSame('420.00', $d['iva']->toFixed(2));
        $this->assertSame('97580.00', $d['neto']->toFixed(2));
    }

    public function testCfteaDelCasoRn68(): void
    {
        $plan = AmortizacionFrancesa::plan(Decimal::of('100000'), Decimal::of('0.60'), 6, new DateTimeImmutable('2026-01-10'), Decimal::of('500'), Decimal::of('0.21'));
        $this->assertSame('146.33', Tasas::cftea(Decimal::of('97580.00'), $plan->totales())->toPercent());
    }

    public function testSinGastosNiIvaElCfteaIgualaLaTea(): void
    {
        $plan = AmortizacionFrancesa::plan(Decimal::of('100000'), Decimal::of('0.60'), 12, new DateTimeImmutable('2026-01-10'));
        $cftea = Tasas::cftea(Decimal::of('100000'), $plan->totales());
        // difiere solo por el redondeo de las cuotas a centavos
        $this->assertSame(Tasas::tea(Decimal::of('0.60'))->toPercent(), $cftea->toPercent());
    }

    public function testRechazaCuotasQueNoDevuelvenElNeto(): void
    {
        $this->expectException(DomainException::class);
        Tasas::cftea(Decimal::of('1000'), [Decimal::of('400'), Decimal::of('400')]);
    }
}
