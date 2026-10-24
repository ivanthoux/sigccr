<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Aritmética decimal y redondeo a la mitad hacia arriba (RN-50, RNF-15). */
final class DecimalTest extends TestCase
{
    public function testRedondeoMitadHaciaArriba(): void
    {
        $this->assertSame('2.35', Decimal::of('2.345')->toFixed(2));
        $this->assertSame('2.34', Decimal::of('2.3449999')->toFixed(2));
        $this->assertSame('-2.35', Decimal::of('-2.345')->toFixed(2));
        $this->assertSame('0.01', Decimal::of('0.005')->toFixed(2));
        $this->assertSame('12.6878', Decimal::of('12.68775')->toFixed(4));
    }

    public function testNoHayErroresDePuntoFlotante(): void
    {
        // 0.1 + 0.2 en punto flotante da 0.30000000000000004
        $this->assertTrue(Decimal::of('0.1')->add('0.2')->equals('0.3'));
        $this->assertSame('0.3', (string) Decimal::of('0.1')->add('0.2'));
    }

    public function testDivisionConPrecisionDeTrabajo(): void
    {
        $tercio = Decimal::one()->div(3);
        $this->assertSame('0.' . str_repeat('3', Decimal::ESCALA), (string) $tercio);
        $this->assertSame('1.00', $tercio->mul(3)->toFixed(2));
    }

    public function testPotenciaNegativa(): void
    {
        $this->assertSame('0.74621540', Decimal::of('1.05')->pow(-6)->toFixed(8));
    }

    public function testMultiploInferior(): void
    {
        $this->assertSame('805000', (string) Decimal::of('805957.1826')->floorToMultiple(1000));
    }

    public function testRechazaFormatosInvalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::of('1e5');
    }

    public function testAceptaElFormatoDelControladorSqlsrv(): void
    {
        // sqlsrv devuelve los DECIMAL como texto y omite el cero inicial
        $this->assertTrue(Decimal::of('.000821917808')->equals('0.000821917808'));
        $this->assertTrue(Decimal::of('-.5')->equals('-0.5'));
    }

    public function testPorcentaje(): void
    {
        $this->assertSame('79.59', Decimal::of('0.795856326')->toPercent());
    }
}
