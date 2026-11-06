<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Imputador;
use PHPUnit\Framework\TestCase;

/** CU-33 Registrar Cobro en Ventanilla: deuda por concepto, selección, orden e imputación (RN-29, D-12). */
final class CobroCuotasTest extends TestCase
{
    /** Cuota 1 del plan de referencia (CP-F28): $ 110.341,62 con IVA, vencida con punitorios devengados. */
    private function cuota(array $cambios = []): array
    {
        return array_replace([
            'id' => 10, 'credito_id' => 1, 'numero' => 1, 'vencimiento' => '2026-10-05',
            'capital_pendiente' => '60000.00', 'interes_pendiente' => '40000.00', 'gastos_pendiente' => '1000.00',
            'iva_pendiente' => '8610.00', 'no_capitalizable_pendiente' => '0.00',
            'punitorio_pendiente' => '1234.5678', 'iva_punitorio_pendiente' => '259.2592',
        ], $cambios);
    }

    public function testConceptosConPunitoriosRedondeados(): void
    {
        $c = CobroCuotas::conceptos($this->cuota());
        $this->assertSame(Imputador::PRELACION, array_keys($c));
        $this->assertSame('1234.57', $c['Punitorio']->toFixed(2));
        $this->assertSame('8869.26', $c['IVA']->toFixed(2));
        $this->assertSame('111103.83', CobroCuotas::total($this->cuota())->toFixed(2));
    }

    public function testDiasDeAtraso(): void
    {
        $this->assertSame(32, CobroCuotas::diasAtraso('2026-10-05', '2026-11-06'));
        $this->assertSame(0, CobroCuotas::diasAtraso('2026-11-06', '2026-11-06'));
        $this->assertSame(0, CobroCuotas::diasAtraso('2026-12-05', '2026-11-06'));
    }

    /** A8: la selección comienza por la cuota impaga más antigua de cada crédito y no saltea cuotas. */
    public function testSeleccionConsecutivaPorCredito(): void
    {
        $pendientes = [1 => [3, 4, 5], 2 => [1, 2]];
        $this->assertSame([], CobroCuotas::validarSeleccion($pendientes, [1 => [3, 4], 2 => [1]]));
        $this->assertSame([], CobroCuotas::validarSeleccion($pendientes, [1 => [3], 2 => []]));
        $e = CobroCuotas::validarSeleccion($pendientes, [1 => [4], 2 => [1, 2]], [1 => '1001']);
        $this->assertSame(['Crédito 1001: la selección debe comenzar por la cuota 3 y seguir sin saltear cuotas (RN-29).'], $e);
        $this->assertCount(1, CobroCuotas::validarSeleccion($pendientes, [1 => [3, 5]]));
        $this->assertSame(['Seleccione al menos una cuota.'], CobroCuotas::validarSeleccion($pendientes, []));
        $this->assertCount(1, CobroCuotas::validarSeleccion($pendientes, [9 => [1]]));
    }

    public function testOrdenPorVencimientoEntreCreditos(): void
    {
        $o = CobroCuotas::ordenar([
            ['id' => 'b1', 'credito_id' => 2, 'numero' => 1, 'vencimiento' => '2026-10-20'],
            ['id' => 'a2', 'credito_id' => 1, 'numero' => 2, 'vencimiento' => '2026-11-05'],
            ['id' => 'a1', 'credito_id' => 1, 'numero' => 1, 'vencimiento' => '2026-10-05'],
        ]);
        $this->assertSame(['a1', 'b1', 'a2'], array_column($o, 'id'));
    }

    /** D-12: el importe puede ser menor que el total, nunca mayor; vacío es el total. */
    public function testImporteACobrar(): void
    {
        $total = Decimal::of('111103.83');
        $this->assertSame('111103.83', CobroCuotas::importeACobrar('', $total)['importe']->toFixed(2));
        $this->assertSame('50000.00', CobroCuotas::importeACobrar('50.000', $total)['importe']->toFixed(2));
        $this->assertNotNull(CobroCuotas::importeACobrar('0', $total)['error']);
        $this->assertStringContainsString('RN-29', CobroCuotas::importeACobrar('120.000', $total)['error']);
    }

    public function testCobroTotalCancelaLaCuota(): void
    {
        $c = $this->cuota();
        $r = Imputador::imputar(CobroCuotas::total($c), [['id' => 10, 'conceptos' => CobroCuotas::conceptos($c)]]);
        $s = CobroCuotas::saldosDespues($c, $r['imputaciones']);
        $this->assertTrue($s['pagada']);
        $this->assertSame('0.0000', $s['columnas']['punitorio_pendiente']);
        $this->assertSame('0.0000', $s['columnas']['iva_punitorio_pendiente']);
        $this->assertSame('0.00', $s['columnas']['capital_pendiente']);
    }

    /** D-12: un pago parcial cubre punitorios, interés, gastos e IVA y deja saldo de capital. */
    public function testCobroParcialDejaSaldoDeCapital(): void
    {
        $c = $this->cuota();
        $r = Imputador::imputar(Decimal::of('60000'), [['id' => 10, 'conceptos' => CobroCuotas::conceptos($c)]]);
        $s = CobroCuotas::saldosDespues($c, $r['imputaciones']);
        $this->assertFalse($s['pagada']);
        $this->assertSame('0.00', $s['columnas']['interes_pendiente']);
        $this->assertSame('0.00', $s['columnas']['iva_pendiente']);
        $this->assertSame('0.0000', $s['columnas']['iva_punitorio_pendiente']);
        // 60.000 − 1.234,57 − 40.000 − 1.000 − 8.869,26 = 8.896,17 a capital
        $this->assertSame('51103.83', $s['columnas']['capital_pendiente']);
    }

    /** El IVA se aplica primero al de los conceptos ordinarios y después al de los punitorios. */
    public function testIvaParcialPrimeroOrdinario(): void
    {
        $c = $this->cuota();
        $r = Imputador::imputar(Decimal::of('46234.57'), [['id' => 10, 'conceptos' => CobroCuotas::conceptos($c)]]);
        $s = CobroCuotas::saldosDespues($c, $r['imputaciones']);
        // 46.234,57 − 1.234,57 − 40.000 − 1.000 = 4.000 de IVA
        $this->assertSame('4610.00', $s['columnas']['iva_pendiente']);
        $this->assertSame('259.2592', $s['columnas']['iva_punitorio_pendiente']);
    }

    /** CU-37: anular el cobro devuelve a la cuota lo imputado, concepto por concepto, y su estado según el vencimiento. */
    public function testAnulacionRestauraLaCuota(): void
    {
        $c = $this->cuota(['iva' => '8610.00']);
        $r = Imputador::imputar(CobroCuotas::total($c), [['id' => 10, 'conceptos' => CobroCuotas::conceptos($c)]]);
        $pagada = array_replace($c, CobroCuotas::saldosDespues($c, $r['imputaciones'])['columnas']);
        $imputaciones = array_map(static fn ($i) => ['concepto' => $i['concepto'], 'importe' => $i['importe']->toFixed(2)], $r['imputaciones']);
        $x = CobroCuotas::restaurar($pagada, $imputaciones, '2026-11-06');
        $this->assertSame('Vencida', $x['estado']);
        $this->assertSame('60000.00', $x['columnas']['capital_pendiente']);
        $this->assertSame('8610.00', $x['columnas']['iva_pendiente']);
        $this->assertSame('259.2600', $x['columnas']['iva_punitorio_pendiente']);
        $this->assertSame('1234.5700', $x['columnas']['punitorio_pendiente']);
        $this->assertSame('Pendiente', CobroCuotas::restaurar($pagada, $imputaciones, '2026-10-01')['estado']);
    }
}

