<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\ReglasCaja;
use PHPUnit\Framework\TestCase;

/** D05: cajas (CU-80, CU-72), jornadas (CU-31, CU-36, RN-28, RN-32) y movimientos de fondos (CU-85). */
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

    public function testArqueoSoloConElEfectivo(): void
    {
        $r = ReglasCaja::arqueo('50000.00', [
            ['tipo' => 'FondoInicial', 'medio' => 'Efectivo', 'importe' => '50000.00'],
            ['tipo' => 'Cobro', 'medio' => 'Efectivo', 'importe' => '30000.00'],
            ['tipo' => 'Cobro', 'medio' => 'Transferencia', 'importe' => '12000.50'],
            ['tipo' => 'Desembolso', 'medio' => 'Efectivo', 'importe' => '45000.00'],
        ]);
        $this->assertSame('35000.00', $r['esperado']);
        $this->assertSame('30000.00', $r['cobros_efectivo']);
        $this->assertSame(['Efectivo' => '30000.00', 'Transferencia' => '12000.50'], $r['cobros_por_medio']);
        $this->assertSame(3, $r['cantidad']);
        $this->assertSame('50000.00', ReglasCaja::arqueo('50000.00', [])['esperado']);
    }

    public function testCierreConDiferenciaExigeExplicacion(): void
    {
        $ok = ReglasCaja::validarCierre('35.000,00', '', '35000.00');
        $this->assertSame([], $ok['errores']);
        $this->assertSame('0.00', $ok['diferencia']);
        $r = ReglasCaja::validarCierre('34.900,00', '', '35000.00');
        $this->assertSame('-100.00', $r['diferencia']);
        $this->assertArrayHasKey('explicacion', $r['errores']);
        $this->assertSame([], ReglasCaja::validarCierre('34.900,00', 'Faltante por un error de vuelto.', '35000.00')['errores']);
        $this->assertArrayHasKey('efectivo_declarado', ReglasCaja::validarCierre('-5', '', '35000.00')['errores']);
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
