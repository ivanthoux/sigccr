<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\Devolucion;
use App\Libraries\Caja\SaldoAFavor;
use App\Libraries\Finanzas\Decimal;
use PHPUnit\Framework\TestCase;

/** Saldos a favor y devoluciones (RN-57, RN-73, RN-77, D-37). */
final class DevolucionTest extends TestCase
{
    private const CBU = '0170001540000000123457';
    private const CVU = '0000003110000000000014';

    /** @return array<string, string> */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'importe' => '1.500,00', 'motivo' => 'El cliente solicita el reintegro del excedente', 'fecha_solicitud' => '2026-11-07', 'medio_solicitud' => 'Nota firmada',
            'destino' => 'Transferencia', 'cbu' => self::CBU, 'cuit_titular' => '20-12345678-6', 'entidad' => 'Banco Francés', 'evidencia' => 'Constancia de CBU emitida por el banco',
        ];
    }

    public function testSaldosSeUsanDesdeElMasAntiguo(): void
    {
        $saldos = [['id' => 1, 'importe_disponible' => '300.00'], ['id' => 2, 'importe_disponible' => '0.00'], ['id' => 3, 'importe_disponible' => '500.00']];
        $this->assertSame('800.00', SaldoAFavor::disponible($saldos)->toFixed(2));
        $r = SaldoAFavor::distribuir(Decimal::of('450'), $saldos);
        $this->assertSame([[1, '300.00'], [3, '150.00']], array_map(static fn ($x) => [$x['saldo'], $x['importe']->toFixed(2)], $r));
    }

    public function testTransferenciaValidaACuentaDelCliente(): void
    {
        $r = Devolucion::validarAutorizacion($this->datos(), ['importe_disponible' => '2000.00', 'transaccion_origen_id' => null], '20123456786', '2026-11-07');
        $this->assertSame([], $r['errores']);
        $this->assertSame(['1500.00', self::CBU, '20123456786'], [$r['datos']['importe'], $r['datos']['cbu_cvu'], $r['datos']['cuit_titular']]);
    }

    public function testDestinosNoAdmitidos(): void
    {
        $saldo = ['importe_disponible' => '1000.00', 'transaccion_origen_id' => null];
        $r = Devolucion::validarAutorizacion($this->datos(['cbu' => self::CVU, 'cuit_titular' => '20999999990']), $saldo, '20123456786', '2026-11-07');
        $this->assertArrayHasKey('importe', $r['errores']);
        $this->assertArrayHasKey('cbu', $r['errores']);
        $this->assertArrayHasKey('cuit_titular', $r['errores']);
        $r = Devolucion::validarAutorizacion($this->datos(['destino' => 'MedioOriginal', 'importe' => '100']), $saldo, '20123456786', '2026-11-07');
        $this->assertSame(['destino'], array_keys($r['errores']));
        $r = Devolucion::validarAutorizacion($this->datos(['destino' => 'MedioOriginal', 'importe' => '100']), ['importe_disponible' => '1000.00', 'transaccion_origen_id' => 9], '20123456786', '2026-11-07');
        $this->assertSame([], $r['errores']);
        $this->assertNull($r['datos']['cbu_cvu']);
    }

    public function testComprobanteDeTransferencia(): void
    {
        $aut = ['importe' => '1500.00', 'cbu_cvu' => self::CBU];
        $ok = Devolucion::validarTransferencia(['numero_operacion' => 'TRF-1', 'fecha' => '2026-11-07', 'importe' => '1500', 'cbu' => self::CBU], $aut, '2026-11-07');
        $this->assertSame([], $ok['errores']);
        $mal = Devolucion::validarTransferencia(['numero_operacion' => 'TRF-1', 'fecha' => '2026-11-07', 'importe' => '1400', 'cbu' => self::CBU], $aut, '2026-11-07');
        $this->assertTrue($mal['discrepancia']);
        $this->assertSame(['importe'], array_keys($mal['errores']));
        $this->assertSame('Incierta', Devolucion::resultado('in_process'));
        $this->assertSame('Confirmada', Devolucion::resultado('approved'));
    }
}
