<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Cartera\DeudaRefinanciable;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Refinanciacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Composición y revalidación de la deuda refinanciada (RN-36, RN-70, D-36). Corte 20/03/2026: la cuota 2 venció el
 * 10/03 con punitorios exigibles; la cuota 3 vence el 10/04.
 */
final class DeudaRefinanciableTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function cuotas(): array
    {
        $base = ['credito_id' => 7, 'credito_numero' => 1001, 'gastos_pendiente' => '500.00', 'no_capitalizable_pendiente' => '0.00', 'estado' => 'Pendiente'];

        return [
            $base + ['id' => 2, 'numero' => 2, 'vencimiento' => '2026-03-10', 'estado' => 'Vencida', 'capital_pendiente' => '15123.40', 'interes_pendiente' => '4250.00',
                'iva_pendiente' => '997.50', 'punitorio_pendiente' => '120.5049', 'iva_punitorio_pendiente' => '25.3060'],
            $base + ['id' => 3, 'numero' => 3, 'vencimiento' => '2026-04-10', 'capital_pendiente' => '15879.57', 'interes_pendiente' => '3493.83',
                'iva_pendiente' => '838.70', 'punitorio_pendiente' => '0', 'iva_punitorio_pendiente' => '0'],
        ];
    }

    /** @return array<int, array{capital_pendiente: string, no_capitalizable: string, punitorio: string}> */
    private function guardadas(): array
    {
        $g = [];
        foreach (DeudaRefinanciable::composicion($this->cuotas(), '2026-03-20')['cuotas'] as $id => $p) {
            $g[$id] = ['capital_pendiente' => $p['capital']->toFixed(2), 'no_capitalizable' => $p['no_capitalizable']->toFixed(2), 'punitorio' => $p['punitorio']->toFixed(2)];
        }

        return $g;
    }

    public function testComposicionCoincideConElMotor(): void
    {
        $t = DeudaRefinanciable::composicion($this->cuotas(), '2026-03-20');
        $this->assertSame('31002.97', $t['capital']->toFixed(2));
        // 4250 + 500 + 997,50 + 120,50 + 25,31 de la cuota vencida; la no vencida solo aporta capital
        $this->assertSame('5893.31', $t['no_capitalizable']->toFixed(2));
        $this->assertSame('145.81', $t['punitorio']->toFixed(2));

        $motor = Refinanciacion::composicion(array_map(static fn ($c) => [
            'vencimiento' => new DateTimeImmutable($c['vencimiento']),
            'conceptos' => [
                'Capital' => Decimal::of($c['capital_pendiente']), 'InteresOrdinario' => Decimal::of($c['interes_pendiente']), 'GastosCargos' => Decimal::of($c['gastos_pendiente']),
                'IVA' => Decimal::of($c['iva_pendiente'])->add(Decimal::of($c['iva_punitorio_pendiente'])->round(2)), 'Punitorio' => Decimal::of($c['punitorio_pendiente'])->round(2),
                'NoCapitalizable' => Decimal::of($c['no_capitalizable_pendiente']),
            ],
        ], $this->cuotas()), new DateTimeImmutable('2026-03-20'));
        $this->assertSame($motor['capital']->toFixed(2), $t['capital']->toFixed(2));
        $this->assertSame($motor['no_capitalizable']->toFixed(2), $t['no_capitalizable']->toFixed(2));
    }

    public function testPunitoriosPosterioresNoSonVariacionYSeIncorporan(): void
    {
        $actuales = $this->cuotas();
        $actuales[0]['punitorio_pendiente'] = '150.0000';
        $actuales[0]['iva_punitorio_pendiente'] = '31.5000';
        $actuales[1]['estado'] = 'Vencida';
        $actuales[1]['punitorio_pendiente'] = '10.0000';
        $actuales[1]['iva_punitorio_pendiente'] = '2.1000';
        $this->assertSame([], DeudaRefinanciable::variaciones($this->guardadas(), $actuales, '2026-03-20'));
        // (150 + 31,50 − 145,81) + (10 + 2,10)
        $this->assertSame('47.79', DeudaRefinanciable::punitoriosDesdeCorte($this->guardadas(), $actuales, '2026-03-20')->toFixed(2));
    }

    public function testPagoCondonacionYAnulacionSonVariaciones(): void
    {
        $pago = $this->cuotas();
        $pago[0]['punitorio_pendiente'] = '0';
        $pago[0]['iva_punitorio_pendiente'] = '0';
        $pago[0]['interes_pendiente'] = '4000.00';
        $this->assertCount(2, DeudaRefinanciable::variaciones($this->guardadas(), $pago, '2026-03-20'));

        $pagada = $this->cuotas();
        $pagada[1]['estado'] = 'Pagada';
        $this->assertSame(['Crédito 1001, cuota 3: ya no está pendiente (Pagada).'], DeudaRefinanciable::variaciones($this->guardadas(), $pagada, '2026-03-20'));

        $guardadas = $this->guardadas();
        unset($guardadas[3]);
        $this->assertCount(1, DeudaRefinanciable::variaciones($guardadas, $this->cuotas(), '2026-03-20'));
    }
}
