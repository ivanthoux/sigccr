<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DomainException;
use InvalidArgumentException;

/**
 * Tasas informadas al cliente (RN-63) e importes de la liquidación (RN-25, RN-68).
 */
final class Tasas
{
    /** Tolerancia de convergencia de la tasa mensual del CFTEA. */
    private const TOLERANCIA = '0.000000000000000001';

    private const MAX_ITERACIONES = 200;

    /** TEA = (1 + TNA / 12)^12 − 1. */
    public static function tea(Decimal $tna): Decimal
    {
        return Decimal::one()->add($tna->div(12))->pow(12)->sub(1);
    }

    /**
     * CFTEA (RN-63): tasa mensual r que iguala el importe neto desembolsado con el valor actual de las cuotas totales,
     * expresada como (1 + r)^12 − 1. Se resuelve por bisección, que converge siempre porque el valor actual es
     * estrictamente decreciente en r.
     *
     * @param list<Decimal> $totalesCuotas cuotas totales del plan (capital, interés, gastos e IVA)
     */
    public static function cftea(Decimal $importeNeto, array $totalesCuotas): Decimal
    {
        return Decimal::one()->add(self::tasaInternaMensual($importeNeto, $totalesCuotas))->pow(12)->sub(1);
    }

    /** @param list<Decimal> $flujos */
    public static function tasaInternaMensual(Decimal $importeNeto, array $flujos): Decimal
    {
        if (!$importeNeto->isPositive() || $flujos === []) {
            throw new InvalidArgumentException('Se requiere un importe neto positivo y al menos una cuota.');
        }
        if (Decimal::sum($flujos)->compare($importeNeto) < 0) {
            throw new DomainException('Las cuotas no alcanzan a devolver el importe neto: el costo financiero sería negativo.');
        }
        $bajo = Decimal::zero();
        $alto = Decimal::one();
        while (self::valorActual($flujos, $alto)->compare($importeNeto) > 0) {
            $alto = $alto->mul(2);
        }
        for ($n = 0; $n < self::MAX_ITERACIONES; $n++) {
            $medio = $bajo->add($alto)->div(2);
            if (self::valorActual($flujos, $medio)->compare($importeNeto) > 0) {
                $bajo = $medio;
            } else {
                $alto = $medio;
            }
            if ($alto->sub($bajo)->compare(self::TOLERANCIA) < 0) {
                break;
            }
        }

        return $bajo->add($alto)->div(2);
    }

    /** @param list<Decimal> $flujos flujos al final de los períodos 1..n */
    public static function valorActual(array $flujos, Decimal $tasa): Decimal
    {
        $va = Decimal::zero();
        $descuento = Decimal::one();
        $base = Decimal::one()->add($tasa);
        foreach ($flujos as $f) {
            $descuento = $descuento->div($base);
            $va = $va->add($f->mul($descuento));
        }

        return $va;
    }

    /**
     * Importes de la liquidación (RN-25, RN-68): gasto de otorgamiento = capital · porcentaje, su IVA y el importe neto
     * a desembolsar.
     *
     * @return array{gasto: Decimal, iva: Decimal, neto: Decimal}
     */
    public static function desembolso(Decimal $capital, Decimal $porcentajeOtorgamiento, Decimal $alicuotaIva): array
    {
        $gasto = $capital->mul($porcentajeOtorgamiento)->round(2);
        $iva = $gasto->mul($alicuotaIva)->round(2);
        $neto = $capital->sub($gasto)->sub($iva);
        if (!$neto->isPositive()) {
            throw new DomainException('El importe neto a desembolsar debe ser positivo.');
        }

        return ['gasto' => $gasto, 'iva' => $iva, 'neto' => $neto];
    }
}
