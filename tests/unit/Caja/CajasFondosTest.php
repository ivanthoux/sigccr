<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\ReglasCaja;
use PHPUnit\Framework\TestCase;

/** D05 y D-30: cajas (CU-80, CU-72), jornadas sin efectivo (CU-31, CU-36, RN-28, RN-32) y movimientos de fondos (CU-85). */
final class CajasFondosTest extends TestCase
{
    private const HOY = '2026-11-04';

    public function testAltaDeCaja(): void
    {
        $r = ReglasCaja::validarCaja(['codigo' => 'caja-01', 'denominacion' => 'Caja 1', 'ubicacion' => '', 'observaciones' => '']);
        $this->assertSame([], $r['errores']);
        $this->assertSame('CAJA-01', $r['datos']['codigo']);
        $this->assertNull($r['datos']['ubicacion']);
        $e = ReglasCaja::validarCaja(['codigo' => 'CAJA 0001 X', 'denominacion' => ''])['errores'];
        $this->assertSame(['codigo', 'denominacion'], array_keys($e));
    }

    public function testVigenciaDeLaAsignacion(): void
    {
        $r = ReglasCaja::validarVigencia('', '', self::HOY);
        $this->assertSame([], $r['errores']);
        $this->assertSame(self::HOY, $r['desde']);
        $this->assertNull($r['hasta']);
        $this->assertArrayHasKey('desde', ReglasCaja::validarVigencia('01/11/2026', '', self::HOY)['errores']);
        $this->assertArrayHasKey('hasta', ReglasCaja::validarVigencia('10/11/2026', '05/11/2026', self::HOY)['errores']);
        $this->assertTrue(ReglasCaja::asignacionVigente('2026-11-01', null, self::HOY));
        $this->assertTrue(ReglasCaja::asignacionVigente('2026-11-01', '2026-11-04', self::HOY));
        $this->assertFalse(ReglasCaja::asignacionVigente('2026-11-01', '2026-11-03', self::HOY));
        $this->assertFalse(ReglasCaja::asignacionVigente('2026-11-05', null, self::HOY));
    }

    /** RN-32, D-30: total registrado por medio; los movimientos anteriores a D-30 (fondo, efectivo) no se concilian. */
    public function testTotalesPorMedio(): void
    {
        $r = ReglasCaja::totalesPorMedio([
            ['tipo' => 'FondoInicial', 'medio' => 'Efectivo', 'importe' => '50000.00'],
            ['tipo' => 'Cobro', 'medio' => 'Transferencia', 'importe' => '12000.50'],
            ['tipo' => 'Cobro', 'medio' => 'Transferencia', 'importe' => '8000.00'],
            ['tipo' => 'Cobro', 'medio' => 'TarjetaDebito', 'importe' => '21263.79'],
            ['tipo' => 'Cobro', 'medio' => 'Efectivo', 'importe' => '3000.00'],
        ]);
        $this->assertSame(['Transferencia' => '20000.50', 'TarjetaDebito' => '21263.79', 'TarjetaCredito' => '0.00'], $r['por_medio']);
        $this->assertSame(3, $r['cantidad']);
        $this->assertSame('41264.29', $r['total']);
        $this->assertSame('0.00', ReglasCaja::totalesPorMedio([])['total']);
    }

    /** CU-36 (A2, A3): conciliación de cada medio; una diferencia exige explicación. */
    public function testConciliacionDelCierre(): void
    {
        $registrado = ['Transferencia' => '20000.50', 'TarjetaDebito' => '21263.79', 'TarjetaCredito' => '0.00'];
        $ok = ReglasCaja::validarConciliacion($registrado, ['Transferencia' => '20.000,50', 'TarjetaDebito' => '21.263,79', 'TarjetaCredito' => ''], []);
        $this->assertSame([], $ok['errores']);
        $this->assertCount(2, $ok['conciliaciones']);
        $this->assertSame('0.00', $ok['conciliaciones'][0]['diferencia']);

        $dif = ReglasCaja::validarConciliacion($registrado, ['Transferencia' => '19.000,50', 'TarjetaDebito' => '21.263,79'], []);
        $this->assertSame(['explicacion_Transferencia'], array_keys($dif['errores']));
        $this->assertSame([], $dif['conciliaciones']);
        $con = ReglasCaja::validarConciliacion($registrado, ['Transferencia' => '19.000,50', 'TarjetaDebito' => '21.263,79'], ['Transferencia' => 'Transferencia T-55 acreditada el lunes.']);
        $this->assertSame([], $con['errores']);
        $this->assertSame('-1000.00', $con['conciliaciones'][0]['diferencia']);

        // A3: el acreditado es obligatorio si hubo cobros con el medio
        $falta = ReglasCaja::validarConciliacion($registrado, ['Transferencia' => '', 'TarjetaDebito' => '-1'], []);
        $this->assertSame(['acreditado_Transferencia', 'acreditado_TarjetaDebito'], array_keys($falta['errores']));

        // Un medio sin cobros con un acreditado informado también se concilia
        $extra = ReglasCaja::validarConciliacion(['Transferencia' => '0.00'], ['TarjetaCredito' => '500'], ['TarjetaCredito' => 'Contracargo de un cobro anterior.']);
        $this->assertSame([], $extra['errores']);
        $this->assertSame([['medio' => 'TarjetaCredito', 'total_registrado' => '0.00', 'total_acreditado' => '500.00', 'diferencia' => '500.00', 'explicacion' => 'Contracargo de un cobro anterior.']], $extra['conciliaciones']);
    }

    public function testMovimientoDeFondosRealizadoOProgramado(): void
    {
        $base = ['tipo' => 'AporteCapital', 'caracter' => 'Ingreso', 'importe' => '20.000.000,00', 'fecha' => '04/11/2026', 'concepto' => 'Aporte de los socios', 'referencia' => 'Acta 12'];
        $r = ReglasCaja::validarMovimiento($base, self::HOY);
        $this->assertSame([], $r['errores']);
        $this->assertSame('Realizado', $r['datos']['estado']);
        $this->assertSame('20000000.00', $r['datos']['importe']);
        $this->assertArrayHasKey('fecha', ReglasCaja::validarMovimiento(['fecha' => '10/11/2026'] + $base, self::HOY)['errores']);
        $p = ReglasCaja::validarMovimiento(['programado' => '1', 'fecha' => '10/11/2026'] + $base, self::HOY);
        $this->assertSame([], $p['errores']);
        $this->assertSame('Programado', $p['datos']['estado']);
        $this->assertArrayHasKey('fecha', ReglasCaja::validarMovimiento(['programado' => '1'] + $base, self::HOY)['errores']);
        $e = ReglasCaja::validarMovimiento(['tipo' => 'X', 'caracter' => '', 'importe' => '0', 'concepto' => ''] + $base, self::HOY)['errores'];
        $this->assertSame(['tipo', 'caracter', 'importe', 'concepto'], array_keys($e));
    }
}
