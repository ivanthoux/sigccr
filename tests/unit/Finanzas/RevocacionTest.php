<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Cartera\Revocacion;
use App\Libraries\Finanzas\Decimal;
use PHPUnit\Framework\TestCase;

/** CU-102 Registrar Revocación del Préstamo: plazo e importe a restituir (RN-79, D-34). */
final class RevocacionTest extends TestCase
{
    /** 10 días hábiles desde lo último entre la recepción del contrato y el desembolso, sin fines de semana ni feriados. */
    public function testFechaLimite(): void
    {
        // Desembolso el viernes 06/11/2026; contrato recibido antes: 10 hábiles = viernes 20/11
        $this->assertSame('2026-11-20', Revocacion::fechaLimite('2026-11-05', '2026-11-06', []));
        // Desde el martes 10/11: sin feriados termina el martes 24/11; con el feriado del lunes 23/11, el miércoles 25/11
        $this->assertSame('2026-11-25', Revocacion::fechaLimite('2026-11-10', '2026-11-06', ['2026-11-23']));
        $this->assertSame('2026-11-24', Revocacion::fechaLimite('2026-11-10', '2026-11-06', []));
    }

    public function testImporteARestituir(): void
    {
        // Neto $ 950.000, TNA 70 %, 8 días: interés 950.000 × 0,70 × 8 / 365 = 14.575,34; IVA 21 % = 3.060,82
        $r = Revocacion::importe(Decimal::of('950000'), Decimal::of('0.70'), '2026-11-06', '2026-11-14', Decimal::of('0.21'), Decimal::zero());
        $this->assertSame(8, $r['dias']);
        $this->assertSame('14575.34', $r['interes']->toFixed(2));
        $this->assertSame('3060.82', $r['iva']->toFixed(2));
        $this->assertSame('967636.16', $r['restituir']->toFixed(2));
        $this->assertSame('0.00', $r['saldo_a_favor']->toFixed(2));
    }

    public function testLoPagadoSeDescuentaYElExcesoEsSaldoAFavor(): void
    {
        $r = Revocacion::importe(Decimal::of('100000'), Decimal::of('0.73'), '2026-11-06', '2026-11-06', Decimal::of('0.21'), Decimal::of('30000'));
        $this->assertSame(0, $r['dias']);
        $this->assertSame('70000.00', $r['restituir']->toFixed(2));
        $s = Revocacion::importe(Decimal::of('100000'), Decimal::of('0.73'), '2026-11-06', '2026-11-11', Decimal::of('0'), Decimal::of('101500'));
        // 5 días: 100.000 × 0,73 × 5 / 365 = 1.000; total 101.000; pagó 101.500
        $this->assertSame('0.00', $s['restituir']->toFixed(2));
        $this->assertSame('500.00', $s['saldo_a_favor']->toFixed(2));
    }
}
