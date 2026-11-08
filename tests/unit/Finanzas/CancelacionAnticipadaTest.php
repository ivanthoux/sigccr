<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\CancelacionAnticipada;
use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Cancelación anticipada total (RN-67). Plan RN-68 liquidado el 10/01/2026: cuota 1 pagada, cuota 2 vencida el
 * 09/03/2026 con punitorios exigibles de 120,50 más IVA 25,31; pago el 20/03/2026.
 */
final class CancelacionAnticipadaTest extends TestCase
{
    public function testCasoDeReferencia(): void
    {
        $base = new DateTimeImmutable('2026-01-10');
        $plan = AmortizacionFrancesa::plan(Decimal::of('100000'), Decimal::of('0.60'), 6, $base, Decimal::of('500'), Decimal::of('0.21'));
        $cuotas = [];
        foreach (array_slice($plan->cuotas, 1) as $c) {
            $conceptos = ['InteresOrdinario' => $c->interes, 'GastosCargos' => $c->gastos, 'IVA' => $c->iva, 'Capital' => $c->capital];
            if ($c->numero === 2) {
                $conceptos['Punitorio'] = Decimal::of('120.50');
                $conceptos['IVA'] = $c->iva->add('25.31');
            }
            $cuotas[] = ['numero' => $c->numero, 'vencimiento' => $c->vencimiento, 'conceptos' => $conceptos];
        }
        $r = CancelacionAnticipada::calcular($cuotas, new DateTimeImmutable('2026-03-20'), $base, Decimal::of('0.21'));

        $this->assertSame('21348.19', $r['vencidas']->toFixed(2));
        $this->assertSame('69861.41', $r['capital_no_vencido']->toFixed(2));
        $this->assertSame(3, $r['cuota_en_curso']);
        $this->assertSame(11, $r['dias_en_curso']);
        $this->assertSame('1280.79', $r['interes_en_curso']->toFixed(2));
        $this->assertSame('268.97', $r['iva_interes_en_curso']->toFixed(2));
        $this->assertSame('92759.36', $r['total']->toFixed(2));
    }

    public function testElInteresProporcionalNoSuperaElDeLaCuota(): void
    {
        $base = new DateTimeImmutable('2026-01-10');
        $cuotas = [['numero' => 1, 'vencimiento' => new DateTimeImmutable('2026-02-09'),
            'conceptos' => ['InteresOrdinario' => Decimal::of('5000'), 'Capital' => Decimal::of('14701.75')]]];
        // pago el mismo día del vencimiento: 30 días transcurridos, interés completo
        $r = CancelacionAnticipada::calcular($cuotas, new DateTimeImmutable('2026-02-09'), $base, Decimal::of('0.21'));
        $this->assertSame('5000.00', $r['interes_en_curso']->toFixed(2));
    }

    public function testDistribucionYReduccion(): void
    {
        $base = new DateTimeImmutable('2026-01-10');
        $plan = AmortizacionFrancesa::plan(Decimal::of('100000'), Decimal::of('0.60'), 6, $base, Decimal::of('500'), Decimal::of('0.21'));
        $cuotas = [];
        foreach (array_slice($plan->cuotas, 1) as $c) {
            $cuotas[] = ['id' => $c->numero, 'numero' => $c->numero, 'vencimiento' => $c->vencimiento,
                'conceptos' => ['InteresOrdinario' => $c->interes, 'GastosCargos' => $c->gastos, 'IVA' => $c->iva, 'Capital' => $c->capital, 'NoCapitalizable' => Decimal::of('100')]];
        }
        $pago = new DateTimeImmutable('2026-03-20');
        $r = CancelacionAnticipada::calcular($cuotas, $pago, $base, Decimal::of('0.21'));
        // El no capitalizable de las cuotas no vencidas (3 a 6) también se cobra
        $this->assertSame('400.00', $r['no_capitalizable_no_vencido']->toFixed(2));
        $d = CancelacionAnticipada::distribuir($cuotas, $pago, $r);
        $imputado = Decimal::sum(array_map(static fn ($i) => $i['importe'], $d['imputaciones']));
        $this->assertSame($r['total']->toFixed(2), $imputado->toFixed(2));
        // Lo pendiente total = imputado + reducción
        $pendiente = Decimal::sum(array_map(static fn ($c) => Decimal::sum(array_values($c['conceptos'])), $cuotas));
        $this->assertSame($pendiente->toFixed(2), $imputado->add($d['interes_no_devengado'])->add($d['gastos_no_devengados'])->add($d['iva_no_devengado'])->toFixed(2));
        $this->assertSame('2000.00', $d['gastos_no_devengados']->toFixed(2));
    }
}
