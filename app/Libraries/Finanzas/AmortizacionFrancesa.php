<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Plan de amortización francesa con periodicidad mensual (RN-50 a RN-52, RN-68, RN-70).
 *
 * Criterios de cálculo:
 * - Tasa mensual i = TNA / 12 (TNA en tanto por uno), sin reducirla antes de operar.
 * - Cuota ordinaria = C · i / [1 − (1 + i)^−n], o C / n si i = 0, redondeada a dos decimales (importe exigible).
 * - Interés del período = saldo · i, redondeado a dos decimales a partir del valor exacto (no se redondea dos veces).
 * - Capital del período = cuota ordinaria − interés; en la última cuota, el capital es el saldo remanente (ajuste final, RN-51).
 * - Gasto administrativo fijo por cuota e IVA = alícuota · (interés + gasto) (RN-68).
 * - Concepto no capitalizable (solo refinanciaciones, RN-70): se distribuye en partes iguales, sin interés ni IVA adicional
 *   (ya incluye el IVA de los conceptos que lo forman); la última parte absorbe la diferencia de redondeo.
 */
final class AmortizacionFrancesa
{
    public static function tasaMensual(Decimal $tna): Decimal
    {
        return $tna->div(12);
    }

    public static function cuotaOrdinaria(Decimal $capital, Decimal $tna, int $cuotas): Decimal
    {
        self::validar($capital, $tna, $cuotas);
        $i = self::tasaMensual($tna);
        if ($i->isZero()) {
            return $capital->div($cuotas)->round(2);
        }
        $factor = Decimal::one()->sub(Decimal::one()->add($i)->pow(-$cuotas));

        return $capital->mul($i)->div($factor)->round(2);
    }

    /**
     * @param DateTimeImmutable $fechaBase fecha local de liquidación o de aplicación de la refinanciación
     */
    public static function plan(
        Decimal $capital,
        Decimal $tna,
        int $cuotas,
        DateTimeImmutable $fechaBase,
        ?Decimal $gastoPorCuota = null,
        ?Decimal $alicuotaIva = null,
        ?Decimal $noCapitalizable = null,
    ): Plan {
        $gasto = $gastoPorCuota ?? Decimal::zero();
        $iva = $alicuotaIva ?? Decimal::zero();
        $noCap = $noCapitalizable ?? Decimal::zero();
        if ($gasto->isNegative() || $iva->isNegative() || $noCap->isNegative()) {
            throw new InvalidArgumentException('Gastos, alícuota y concepto no capitalizable no pueden ser negativos.');
        }

        $i = self::tasaMensual($tna);
        $cuota = self::cuotaOrdinaria($capital, $tna, $cuotas);
        $vencimientos = CalendarioCuotas::vencimientos($fechaBase, $cuotas);
        $parteNoCap = $noCap->div($cuotas)->round(2);

        $saldo = $capital;
        $noCapAsignado = Decimal::zero();
        $lista = [];
        for ($k = 1; $k <= $cuotas; $k++) {
            $interes = $saldo->mul($i)->round(2);
            $capitalK = $k < $cuotas ? $cuota->sub($interes) : $saldo;
            if ($capitalK->isNegative() || $capitalK->compare($saldo) > 0) {
                throw new DomainException("El plan genera un capital inválido en la cuota {$k} (RN-51).");
            }
            $saldo = $saldo->sub($capitalK);
            $nc = $k < $cuotas ? $parteNoCap : $noCap->sub($noCapAsignado);
            $noCapAsignado = $noCapAsignado->add($nc);
            if ($nc->isNegative()) {
                throw new DomainException('El concepto no capitalizable no puede distribuirse sin componentes negativos (RN-51).');
            }
            $ivaK = $interes->add($gasto)->mul($iva)->round(2);
            $lista[] = new CuotaPlan($k, $vencimientos[$k - 1], $capitalK, $interes, $gasto, $ivaK, $nc);
        }

        return new Plan($capital, $tna, $cuota, $lista);
    }

    private static function validar(Decimal $capital, Decimal $tna, int $cuotas): void
    {
        if (!$capital->isPositive()) {
            throw new InvalidArgumentException('El capital debe ser positivo.');
        }
        if ($tna->isNegative()) {
            throw new InvalidArgumentException('La tasa no puede ser negativa.');
        }
        if ($cuotas < 1) {
            throw new InvalidArgumentException('La cantidad de cuotas debe ser al menos 1.');
        }
    }
}
