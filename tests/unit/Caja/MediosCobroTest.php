<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\MediosCobro;
use App\Libraries\Finanzas\Decimal;
use PHPUnit\Framework\TestCase;

/** Cobro con varios medios de pago bancarios, sin efectivo (RN-29, RN-77, D-11, D-30). */
final class MediosCobroTest extends TestCase
{
    public function testTarjetaYTransferenciaExactas(): void
    {
        // Cuota de $ 21.263,79: $ 10.000 con débito y el resto por transferencia
        $r = MediosCobro::componer([
            ['medio' => 'TarjetaDebito', 'importe' => '10000', 'numero_operacion' => '000123'],
            ['medio' => 'Transferencia', 'importe' => '11263.79', 'numero_operacion' => 'TRF-9'],
        ], Decimal::of('21263.79'));
        $this->assertSame([], $r['errores']);
        $this->assertCount(2, $r['medios']);
        $this->assertSame('TarjetaDebito', $r['medios'][0]['medio']);
        $this->assertSame('10000.00', $r['medios'][0]['recibido']->toFixed(2));
        $total = Decimal::sum(array_map(static fn ($m) => $m['importe'], $r['medios']));
        $this->assertSame('21263.79', $total->toFixed(2));
    }

    public function testNoSeRecibeEfectivo(): void
    {
        $r = MediosCobro::componer([['medio' => 'Efectivo', 'importe' => '10000']], Decimal::of('10000'));
        $this->assertCount(1, $r['errores']);
        $this->assertStringContainsString('RN-77', $r['errores'][0]);
        $this->assertSame([], $r['medios']);
    }

    public function testLosMediosNoPuedenSuperarElImporte(): void
    {
        $r = MediosCobro::componer([['medio' => 'Transferencia', 'importe' => '25000', 'numero_operacion' => 'X1']], Decimal::of('21263.79'));
        $this->assertSame(['Los medios suman $ 25000.00 y superan el importe a cobrar en $ 3736.21.'], $r['errores']);
    }

    public function testLosMediosDebenCubrirElImporte(): void
    {
        $r = MediosCobro::componer([['medio' => 'Transferencia', 'importe' => '15000', 'numero_operacion' => 'X1']], Decimal::of('21263.79'));
        $this->assertSame(['Los medios suman $ 15000.00: faltan $ 6263.79 para el importe a cobrar.'], $r['errores']);
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

    public function testDosTransferenciasConOperacionesDistintas(): void
    {
        $r = MediosCobro::componer([
            ['medio' => 'Transferencia', 'importe' => '100', 'numero_operacion' => 'T1'],
            ['medio' => 'Transferencia', 'importe' => '50', 'numero_operacion' => 'T2'],
        ], Decimal::of('150'));
        $this->assertSame([], $r['errores']);
    }

    public function testPagoParcialConUnSoloMedio(): void
    {
        // D-12: se cobra una fracción de la cuota por transferencia
        $r = MediosCobro::componer([['medio' => 'Transferencia', 'importe' => '10000', 'numero_operacion' => 'T-77']], Decimal::of('10000'));
        $this->assertSame([], $r['errores']);
        $this->assertSame('10000.00', $r['medios'][0]['importe']->toFixed(2));
    }

    /** H-26: el número de operación se guarda en VARCHAR(60): hasta 60 caracteres ASCII visibles; el medio se recorta. */
    public function testNumeroDeOperacion(): void
    {
        $total = \App\Libraries\Finanzas\Decimal::of('100');
        $m = static fn (string $op, string $medio = 'Transferencia'): array => MediosCobro::componer([['medio' => $medio, 'importe' => '100', 'numero_operacion' => $op]], $total);
        $this->assertSame([], $m(str_repeat('9', 60))['errores']);
        $this->assertSame([], $m('OP-1', ' Transferencia ')['errores'], 'el medio se recorta');
        foreach ([str_repeat('9', 61), 'OP-Ñ', 'OP-😀'] as $op) {
            $this->assertSame(['Medio 1: el número de operación admite hasta 60 letras, números o signos, sin tildes.'], $m($op)['errores'], $op);
        }
    }
}
