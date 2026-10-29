<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\CapacidadPago;
use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Capacidad de pago, capital máximo y alternativas (RF-14, RF-15, CU-87). */
final class CapacidadPagoTest extends TestCase
{
    public function testCapacidadDePago(): void
    {
        $k = CapacidadPago::capacidad(Decimal::of('500000'), Decimal::of('0.30'), Decimal::of('50000'));
        $this->assertSame('100000.00', $k->toFixed(2));
    }

    public function testCapitalMaximoRespetaLaCapacidadConIva(): void
    {
        $c = CapacidadPago::capitalMaximo(Decimal::of('100000'), Decimal::of('0.60'), 12, Decimal::of('500'), Decimal::of('0.21'), Decimal::of('1000'));
        $this->assertSame('805000', (string) $c);
        $fecha = new DateTimeImmutable('2026-01-10');
        $plan = AmortizacionFrancesa::plan($c, Decimal::of('0.60'), 12, $fecha, Decimal::of('500'), Decimal::of('0.21'));
        $this->assertSame('99881.96', $plan->cuotaTotalMaxima()->toFixed(2));
        // un escalón más supera la capacidad
        $siguiente = AmortizacionFrancesa::plan($c->add(1000), Decimal::of('0.60'), 12, $fecha, Decimal::of('500'), Decimal::of('0.21'));
        $this->assertSame('100005.28', $siguiente->cuotaTotalMaxima()->toFixed(2));
    }

    public function testSinCapacidadNoHayCapital(): void
    {
        $c = CapacidadPago::capitalMaximo(Decimal::of('400'), Decimal::of('0.60'), 12, Decimal::of('500'), Decimal::of('0.21'), Decimal::of('1000'));
        $this->assertTrue($c->isZero());
    }

    public function testAlternativasDescartanLasQueSuperanLaCapacidad(): void
    {
        $r = CapacidadPago::alternativas(
            Decimal::of('30000'),
            Decimal::of('0.60'),
            [6, 12],
            Decimal::of('500'),
            Decimal::of('0.02'),
            Decimal::of('0.21'),
            Decimal::of('50000'),
            Decimal::of('1000000'),
            Decimal::of('1000'),
            ['1', '0.5'],
            new DateTimeImmutable('2026-01-10'),
        );
        $this->assertTrue($r['monto_maximo']->isPositive());
        $viables = array_filter($r['alternativas'], static fn ($a) => $a['viable']);
        $this->assertGreaterThan(0, count($viables));
        foreach ($r['alternativas'] as $a) {
            if ($a['viable']) {
                $this->assertLessThanOrEqual(0, $a['plan']->cuotaTotalMaxima()->compare('30000'));
            } else {
                $this->assertTrue($a['motivo'] !== null);
            }
        }
    }
}
