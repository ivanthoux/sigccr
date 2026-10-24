<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Refinanciacion;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * Refinanciación (RN-70). Cuotas 2 a 6 del plan RN-68 con fecha de corte 20/03/2026; la cuota 2 está vencida con
 * punitorios de 120,50 más IVA 25,31. Nuevo plan: TNA 48 %, 12 cuotas, aplicado el 25/03/2026 con 35,40 de
 * punitorios devengados después del corte.
 */
final class RefinanciacionTest extends TestCase
{
    /** @return list<array{vencimiento: DateTimeImmutable, conceptos: array<string, Decimal>}> */
    private function cuotas(): array
    {
        $plan = AmortizacionFrancesa::plan(Decimal::of('100000'), Decimal::of('0.60'), 6, new DateTimeImmutable('2026-01-10'), Decimal::of('500'), Decimal::of('0.21'));
        $out = [];
        foreach (array_slice($plan->cuotas, 1) as $c) {
            $k = ['InteresOrdinario' => $c->interes, 'GastosCargos' => $c->gastos, 'IVA' => $c->iva, 'Capital' => $c->capital];
            if ($c->numero === 2) {
                $k['Punitorio'] = Decimal::of('120.50');
                $k['IVA'] = $c->iva->add('25.31');
            }
            $out[] = ['vencimiento' => $c->vencimiento, 'conceptos' => $k];
        }

        return $out;
    }

    public function testComposicionDeLaDeuda(): void
    {
        $c = Refinanciacion::composicion($this->cuotas(), new DateTimeImmutable('2026-03-20'));
        $this->assertSame('85298.25', $c['capital']->toFixed(2));
        $this->assertSame('5911.35', $c['no_capitalizable']->toFixed(2));
    }

    public function testPlanConConceptoNoCapitalizableDistribuido(): void
    {
        $c = Refinanciacion::composicion($this->cuotas(), new DateTimeImmutable('2026-03-20'));
        $noCap = Refinanciacion::incorporarPunitorios($c['no_capitalizable'], Decimal::of('35.40'));
        $this->assertSame('5946.75', $noCap->toFixed(2));
        $plan = Refinanciacion::plan($c['capital'], $noCap, Decimal::of('0.48'), 12, new DateTimeImmutable('2026-03-25'), Decimal::of('0.21'));

        $this->assertSame('9088.71', $plan->cuotaOrdinaria->toFixed(2));
        $p = $plan->cuotas[0]->toArray();
        $this->assertSame(['2026-04-24', '5676.78', '3411.93', '716.51', '495.56', '10300.78'], [$p['vencimiento'], $p['capital'], $p['interes'], $p['iva'], $p['no_capitalizable'], $p['total']]);
        $u = $plan->cuotas[11]->toArray();
        $this->assertSame(['2027-03-24', '8739.18', '349.57', '73.41', '495.59', '9657.75'], [$u['vencimiento'], $u['capital'], $u['interes'], $u['iva'], $u['no_capitalizable'], $u['total']]);
        $this->assertSame('85298.25', $plan->totalCapital()->toFixed(2));
        $this->assertSame('5946.75', \App\Libraries\Finanzas\Decimal::sum(array_map(static fn ($x) => $x->noCapitalizable, $plan->cuotas))->toFixed(2));
    }

    public function testCondicionesFueraDeLimites(): void
    {
        $this->expectException(DomainException::class);
        Refinanciacion::validarCondiciones(Decimal::of('0.48'), 40, Decimal::of('0.30'), Decimal::of('0.60'), 36);
    }
}
