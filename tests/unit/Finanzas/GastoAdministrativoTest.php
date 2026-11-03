<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\CapacidadPago;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\GastoAdministrativo;
use App\Libraries\Solicitudes\Simulador;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Gasto administrativo fijo o porcentual del capital en cada cuota (RN-68, D-28). */
final class GastoAdministrativoTest extends TestCase
{
    public function testImporteFijoYPorcentajeDelCapital(): void
    {
        $capital = Decimal::of('800000');
        $this->assertSame('1500.00', GastoAdministrativo::fijo('1500')->porCuota($capital)->toFixed(2));
        $this->assertSame('4000.00', GastoAdministrativo::porcentual('0.005')->porCuota($capital)->toFixed(2));
        // Las versiones anteriores a D-28 no tienen tipo: son de importe fijo
        $this->assertSame('Fijo', GastoAdministrativo::deVersion(['gasto_administrativo_cuota' => '500.00'])->tipo);
        $p = GastoAdministrativo::deVersion(['tipo_gasto_administrativo' => 'Porcentual', 'gasto_administrativo_pct' => '0.010000', 'gasto_administrativo_cuota' => '0.00']);
        $this->assertSame('8000.00', $p->porCuota($capital)->toFixed(2));
    }

    public function testElPorcentajeDebeSerMenorQueElCien(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GastoAdministrativo::porcentual('1');
    }

    /** Ejemplo de la presentación: 0,5 % de $ 800.000 son $ 4.000 más IVA en cada una de las 12 cuotas. */
    public function testSimulacionConGastoPorcentual(): void
    {
        $version = ['tna' => '0.72', 'gasto_otorgamiento_pct' => '0.02', 'gasto_administrativo_cuota' => '0.00', 'tipo_gasto_administrativo' => 'Porcentual', 'gasto_administrativo_pct' => '0.005'];
        $c = Simulador::calcular($version, '800000.00', 12, '0.21', new DateTimeImmutable('2026-11-04'));
        foreach ($c['plan']->cuotas as $q) {
            $this->assertSame('4000.00', $q->gastos->toFixed(2));
            $this->assertSame($q->interes->add(4000)->mul('0.21')->round(2)->toFixed(2), $q->iva->toFixed(2));
        }
        // CP-F28: cuota ordinaria 95.421,62 + gasto 4.000,00 + IVA 10.920,00
        $this->assertSame('48000.00', $c['plan']->cuotas[0]->interes->toFixed(2));
        $this->assertSame('110341.62', $c['primera_cuota']->toFixed(2));
        // el gasto porcentual encarece el crédito respecto de uno sin gasto administrativo
        $sin = Simulador::calcular(['gasto_administrativo_cuota' => '0.00', 'tipo_gasto_administrativo' => 'Fijo', 'gasto_administrativo_pct' => null] + $version, '800000.00', 12, '0.21', new DateTimeImmutable('2026-11-04'));
        $this->assertSame(1, $c['cftea']->compare($sin['cftea']));
        $this->assertSame($sin['primera_cuota']->add('4840.00')->toFixed(2), $c['primera_cuota']->toFixed(2));
    }

    /** El capital máximo con gasto porcentual entra en la capacidad y un escalón más la supera. */
    public function testCapitalMaximoConGastoPorcentual(): void
    {
        $k = Decimal::of('100000');
        $tna = Decimal::of('0.60');
        $iva = Decimal::of('0.21');
        $gasto = GastoAdministrativo::porcentual('0.005');
        $c = CapacidadPago::capitalMaximo($k, $tna, 12, $gasto, $iva, Decimal::of('1000'));
        // CP-F29: 772.000 (1.ª cuota 99.877,82); 773.000 supera la capacidad (100.007,19)
        $this->assertSame('772000', (string) $c);
        $fecha = new DateTimeImmutable('2026-01-10');
        $cuota = static fn (Decimal $capital): Decimal => AmortizacionFrancesa::plan($capital, $tna, 12, $fecha, $gasto->porCuota($capital), $iva)->cuotaTotalMaxima();
        $this->assertSame('99877.82', $cuota($c)->toFixed(2));
        $this->assertSame('100007.19', $cuota($c->add(1000))->toFixed(2));
        // con un gasto porcentual el máximo es menor que sin gasto
        $this->assertSame(-1, $c->compare(CapacidadPago::capitalMaximo($k, $tna, 12, Decimal::zero(), $iva, Decimal::of('1000'))));
    }
}
