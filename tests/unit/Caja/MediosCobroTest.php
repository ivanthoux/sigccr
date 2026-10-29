<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\MediosCobro;
use App\Libraries\Finanzas\Decimal;
use PHPUnit\Framework\TestCase;

/** Cobro con varios medios de pago (RN-29, D-11). */
final class MediosCobroTest extends TestCase
{
    public function testEfectivoYTransferenciaConCambio(): void
    {
        // Cuota de $ 21.263,79: $ 15.000 por transferencia y $ 7.000 en efectivo => cambio $ 736,21
        $r = MediosCobro::componer([
            ['medio' => 'Transferencia', 'importe' => '15000', 'numero_operacion' => 'TRF-881'],
            ['medio' => 'Efectivo', 'importe' => '7000'],
        ], Decimal::of('21263.79'));
        $this->assertSame([], $r['errores']);
        $this->assertSame('736.21', $r['cambio']->toFixed(2));
        $this->assertSame('Efectivo', $r['medios'][0]['medio']);
        $this->assertSame('6263.79', $r['medios'][0]['importe']->toFixed(2));
        $this->assertSame('7000.00', $r['medios'][0]['recibido']->toFixed(2));
        $total = Decimal::sum(array_map(static fn ($m) => $m['importe'], $r['medios']));
        $this->assertSame('21263.79', $total->toFixed(2));
    }

    public function testTarjetaYTransferenciaExactas(): void
    {
        $r = MediosCobro::componer([
            ['medio' => 'TarjetaDebito', 'importe' => '10000', 'numero_operacion' => '000123'],
            ['medio' => 'Transferencia', 'importe' => '11263.79', 'numero_operacion' => 'TRF-9'],
        ], Decimal::of('21263.79'));
        $this->assertSame([], $r['errores']);
        $this->assertTrue($r['cambio']->isZero());
        $this->assertCount(2, $r['medios']);
    }

    public function testMediosElectronicosNoPuedenSuperarElImporte(): void
    {
        $r = MediosCobro::componer([['medio' => 'Transferencia', 'importe' => '25000', 'numero_operacion' => 'X1']], Decimal::of('21263.79'));
        $this->assertCount(1, $r['errores']);
        $this->assertStringContainsString('no generan cambio', $r['errores'][0]);
    }

    public function testFaltaDinero(): void
    {
        $r = MediosCobro::componer([
            ['medio' => 'Transferencia', 'importe' => '15000', 'numero_operacion' => 'X1'],
            ['medio' => 'Efectivo', 'importe' => '5000'],
        ], Decimal::of('21263.79'));
        $this->assertStringContainsString('faltan $ 1263.79', $r['errores'][0]);
    }

    public function testExigeNumeroDeOperacionYNoRepetirlo(): void
    {
        $r = MediosCobro::componer([
            ['medio' => 'Transferencia', 'importe' => '100', 'numero_operacion' => ''],
            ['medio' => 'TarjetaCredito', 'importe' => '100', 'numero_operacion' => 'A'],
            ['medio' => 'TarjetaCredito', 'importe' => '100', 'numero_operacion' => 'A'],
        ], Decimal::of('300'));
        $this->assertCount(2, $r['errores']);
    }

    public function testUnaSolaLineaDeEfectivoYSinEfectivoSobrante(): void
    {
        $dos = MediosCobro::componer([['medio' => 'Efectivo', 'importe' => '100'], ['medio' => 'Efectivo', 'importe' => '100']], Decimal::of('150'));
        $this->assertCount(1, $dos['errores']);
        $sobra = MediosCobro::componer([
            ['medio' => 'Transferencia', 'importe' => '150', 'numero_operacion' => 'T'],
            ['medio' => 'Efectivo', 'importe' => '20'],
        ], Decimal::of('150'));
        $this->assertStringContainsString('quite el efectivo', $sobra['errores'][0]);
    }

    public function testPagoParcialConUnSoloMedio(): void
    {
        // D-12: se cobra una fracción de la cuota en efectivo
        $r = MediosCobro::componer([['medio' => 'Efectivo', 'importe' => '10000']], Decimal::of('10000'));
        $this->assertSame([], $r['errores']);
        $this->assertTrue($r['cambio']->isZero());
    }
}
