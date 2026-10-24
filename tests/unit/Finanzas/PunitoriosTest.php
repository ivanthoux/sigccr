<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Punitorios;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Punitorios simples, tolerancia y cambios de tasa (RN-34, RN-35, RN-53). */
final class PunitoriosTest extends TestCase
{
    private DateTimeImmutable $venc;

    protected function setUp(): void
    {
        $this->venc = new DateTimeImmutable('2026-03-09');
    }

    public function testTasaDiariaLimitadaPorElTopeSobreLaTna(): void
    {
        // configurada 0,12 % diaria; tope 50 % de una TNA de 60 % => 0,0821917808 % diaria
        $t = Punitorios::tasaDiaria(Decimal::of('0.0012'), Decimal::of('0.5'), Decimal::of('0.60'));
        $this->assertSame('0.000821917808', $t->toFixed(12));
        // si la configurada es menor, se aplica la configurada
        $this->assertSame('0.000500000000', Punitorios::tasaDiaria(Decimal::of('0.0005'), Decimal::of('0.5'), Decimal::of('0.60'))->toFixed(12));
    }

    public function testPagoElDiaDelVencimientoNoTieneAtraso(): void
    {
        $this->assertSame(0, Punitorios::diasAtraso($this->venc, new DateTimeImmutable('2026-03-09 23:59')));
    }

    public function testEjemploRn53TercerDiaSinPunitorios(): void
    {
        $r = Punitorios::calcular(Decimal::of('15436.84'), $this->venc, new DateTimeImmutable('2026-03-12'), 3,
            static fn () => Decimal::of('0.000821917808'), Decimal::of('0.21'));
        $this->assertSame(3, $r['dias']);
        $this->assertFalse($r['exigible']);
        $this->assertCount(3, $r['tramos'], 'los tramos se registran aunque sean condicionales');
        $this->assertSame('0.00', $r['importe']->toFixed(2));
    }

    public function testEjemploRn53CuartoDiaCuatroDiasDePunitorios(): void
    {
        $r = Punitorios::calcular(Decimal::of('15436.84'), $this->venc, new DateTimeImmutable('2026-03-13'), 3,
            static fn () => Decimal::of('0.000821917808'), Decimal::of('0.21'));
        $this->assertSame(4, $r['dias']);
        $this->assertTrue($r['exigible']);
        $this->assertSame('12.6878', $r['tramos'][0]['importe']->toFixed(4));
        $this->assertSame('2.6644', $r['tramos'][0]['iva']->toFixed(4));
        $this->assertSame('50.75', $r['importe']->toFixed(2));
        $this->assertSame('10.66', $r['iva']->toFixed(2));
        $this->assertSame('2026-03-10', $r['tramos'][0]['fecha']->format('Y-m-d'));
    }

    public function testCambioDeTasaSeAplicaDesdeSuFechaEfectiva(): void
    {
        // RN-35: días 10 a 14 de marzo al 0,08 %; desde el 15 de marzo, 0,05 %
        $tasa = static fn (DateTimeImmutable $d) => $d < new DateTimeImmutable('2026-03-15') ? Decimal::of('0.0008') : Decimal::of('0.0005');
        $r = Punitorios::calcular(Decimal::of('15436.84'), $this->venc, new DateTimeImmutable('2026-03-19'), 3, $tasa, Decimal::of('0.21'));
        $this->assertSame(10, $r['dias']);
        $this->assertSame('100.34', $r['importe']->toFixed(2));
        $this->assertSame('21.07', $r['iva']->toFixed(2));
    }
}
