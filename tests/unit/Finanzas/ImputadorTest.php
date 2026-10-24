<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Imputador;
use DomainException;
use PHPUnit\Framework\TestCase;

/** Prelación de imputación y selección consecutiva (RN-29, RN-56, RN-57). */
final class ImputadorTest extends TestCase
{
    /** @return list<array{id: int, conceptos: array<string, Decimal>}> */
    private function cuotas(): array
    {
        $c = static fn (array $v) => array_map(static fn ($x) => Decimal::of($x), $v);

        return [
            ['id' => 2, 'conceptos' => $c(['Punitorio' => '50.75', 'InteresOrdinario' => '4264.91', 'GastosCargos' => '500.00', 'IVA' => '1011.29', 'Capital' => '15436.84'])],
            ['id' => 3, 'conceptos' => $c(['InteresOrdinario' => '3493.07', 'GastosCargos' => '500.00', 'IVA' => '838.54', 'Capital' => '16208.68'])],
        ];
    }

    public function testCobroOrdinarioRespetaLaPrelacion(): void
    {
        $r = Imputador::imputar(Decimal::of('42304.08'), $this->cuotas());
        $this->assertSame(['Punitorio', 'InteresOrdinario', 'GastosCargos', 'IVA', 'Capital'], array_column(array_slice($r['imputaciones'], 0, 5), 'concepto'));
        $this->assertSame([2, 3], $r['canceladas']);
        $this->assertTrue($r['remanente']->isZero());
    }

    public function testCobroOrdinarioNoAdmiteFracciones(): void
    {
        $this->expectException(DomainException::class);
        Imputador::imputar(Decimal::of('20000'), $this->cuotas());
    }

    public function testImputacionParcialExcepcional(): void
    {
        // RN-56: el importe recibido no alcanza; se imputa por prelación y la cuota no queda pagada
        $r = Imputador::imputar(Decimal::of('6000'), $this->cuotas(), true);
        $this->assertSame([], $r['canceladas']);
        $ultima = end($r['imputaciones']);
        $this->assertSame('Capital', $ultima['concepto']);
        $this->assertTrue($ultima['parcial']);
        $this->assertSame('173.05', $ultima['importe']->toFixed(2));
        $this->assertTrue($r['remanente']->isZero());
    }

    public function testExcedenteQuedaComoRemanente(): void
    {
        // RN-57: el excedente de un pago electrónico se registra como saldo a favor
        $r = Imputador::imputar(Decimal::of('42400'), $this->cuotas(), true);
        $this->assertSame('95.92', $r['remanente']->toFixed(2));
    }

    public function testSeleccionConsecutivaDesdeLaMasAntigua(): void
    {
        $this->assertTrue(Imputador::seleccionConsecutiva([2, 3, 4, 5], [3, 2]));
        $this->assertFalse(Imputador::seleccionConsecutiva([2, 3, 4, 5], [3]));
        $this->assertFalse(Imputador::seleccionConsecutiva([2, 3, 4, 5], [2, 4]));
    }
}
