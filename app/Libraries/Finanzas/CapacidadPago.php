<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Capacidad de pago, monto máximo financiable y alternativas de financiación (RF-14, RF-15, CU-87).
 *
 * Capacidad de pago = ingresos declarados × porcentaje máximo de afectación − cuotas vigentes y comprometidas.
 *
 * Capital máximo para n cuotas: en un plan francés con gasto fijo la cuota total más alta es la primera, cuyo total es
 *   cuota + g + IVA · (C · i + g), con cuota = C / a(n, i) y a(n, i) = [1 − (1 + i)^−n] / i.
 * Imponiendo que no supere la capacidad K:
 *   C ≤ (K − g · (1 + IVA)) / (1 / a(n, i) + IVA · i)
 * Con gasto porcentual (D-28), g = C · p en cada cuota y la condición queda
 *   C ≤ (K − g_fijo · (1 + IVA)) / (1 / a(n, i) + IVA · i + p · (1 + IVA))
 * El resultado se redondea hacia abajo al múltiplo configurado y se verifica con el plan real (los redondeos de
 * centavos pueden exceder la capacidad), bajando un escalón cuando hace falta.
 *
 * Nota: CU-87 paso 3 descuenta solo el gasto y su IVA; sin el IVA del interés, la alternativa del monto máximo
 * superaría siempre la capacidad y se descartaría en el paso 6. Esta fórmula incorpora ese IVA (ver observación en
 * la documentación).
 */
final class CapacidadPago
{
    public static function capacidad(Decimal $ingresos, Decimal $porcentajeAfectacion, Decimal $obligaciones): Decimal
    {
        return $ingresos->mul($porcentajeAfectacion)->sub($obligaciones)->round(2);
    }

    public static function capitalMaximo(
        Decimal $capacidad,
        Decimal $tna,
        int $cuotas,
        Decimal|GastoAdministrativo $gastoPorCuota,
        Decimal $alicuotaIva,
        Decimal $paso,
    ): Decimal {
        if ($cuotas < 1) {
            throw new InvalidArgumentException('La cantidad de cuotas debe ser al menos 1.');
        }
        $gasto = GastoAdministrativo::de($gastoPorCuota);
        $conIva = Decimal::one()->add($alicuotaIva);
        $disponible = $capacidad->sub($gasto->parteFija()->mul($conIva));
        $porcentual = $gasto->proporcion()->mul($conIva);
        if (!$disponible->isPositive()) {
            return Decimal::zero();
        }
        $i = AmortizacionFrancesa::tasaMensual($tna);
        if ($i->isZero()) {
            $bruto = $disponible->div(Decimal::one()->div($cuotas)->add($porcentual));
        } else {
            $a = Decimal::one()->sub(Decimal::one()->add($i)->pow(-$cuotas))->div($i);
            $bruto = $disponible->div(Decimal::one()->div($a)->add($alicuotaIva->mul($i))->add($porcentual));
        }
        $capital = $bruto->floorToMultiple($paso);
        $fecha = new DateTimeImmutable('2000-01-01');
        while ($capital->isPositive()) {
            $plan = AmortizacionFrancesa::plan($capital, $tna, $cuotas, $fecha, $gasto->porCuota($capital), $alicuotaIva);
            if ($plan->cuotaTotalMaxima()->compare($capacidad) <= 0) {
                return $capital;
            }
            $capital = $capital->sub($paso);
        }

        return Decimal::zero();
    }

    /**
     * Genera y evalúa las alternativas (CU-87, pasos 3 a 6).
     *
     * @param list<int>    $cuotasAdmitidas
     * @param list<string> $escalones proporciones del monto máximo a ofrecer, por ejemplo ['1', '0.75', '0.5']
     *
     * @return array{monto_maximo: Decimal, alternativas: list<array{importe: Decimal, cuotas: int, viable: bool, motivo: ?string, plan: ?Plan, tea: ?Decimal, cftea: ?Decimal}>}
     */
    public static function alternativas(
        Decimal $capacidad,
        Decimal $tna,
        array $cuotasAdmitidas,
        Decimal|GastoAdministrativo $gastoPorCuota,
        Decimal $gastoOtorgamientoPct,
        Decimal $alicuotaIva,
        Decimal $montoMinimo,
        Decimal $montoMaximo,
        Decimal $paso,
        array $escalones,
        DateTimeImmutable $fechaReferencia,
    ): array {
        $gasto = GastoAdministrativo::de($gastoPorCuota);
        $maximos = [];
        foreach ($cuotasAdmitidas as $n) {
            $c = Decimal::min(self::capitalMaximo($capacidad, $tna, $n, $gasto, $alicuotaIva, $paso), $montoMaximo);
            if ($c->compare($montoMinimo) >= 0) {
                $maximos[$n] = $c;
            }
        }
        $montoMaxFinanciable = $maximos === [] ? Decimal::zero() : array_reduce($maximos, [Decimal::class, 'max'], Decimal::zero());
        $alternativas = [];
        if ($montoMaxFinanciable->isZero()) {
            return ['monto_maximo' => $montoMaxFinanciable, 'alternativas' => []];
        }
        $importes = [];
        foreach ($escalones as $e) {
            $imp = $montoMaxFinanciable->mul($e)->floorToMultiple($paso);
            $importes[(string) $imp] = $imp;
        }
        foreach ($importes as $importe) {
            foreach ($cuotasAdmitidas as $n) {
                $alt = ['importe' => $importe, 'cuotas' => $n, 'viable' => false, 'motivo' => null, 'plan' => null, 'tea' => null, 'cftea' => null];
                if ($importe->compare($montoMinimo) < 0 || $importe->compare($montoMaximo) > 0) {
                    $alt['motivo'] = 'Importe fuera de los límites de la línea.';
                    $alternativas[] = $alt;
                    continue;
                }
                $plan = AmortizacionFrancesa::plan($importe, $tna, $n, $fechaReferencia, $gasto->porCuota($importe), $alicuotaIva);
                $alt['plan'] = $plan;
                if ($plan->cuotaTotalMaxima()->compare($capacidad) > 0) {
                    $alt['motivo'] = 'La cuota total con IVA supera la capacidad de pago.';
                    $alternativas[] = $alt;
                    continue;
                }
                try {
                    $neto = Tasas::desembolso($importe, $gastoOtorgamientoPct, $alicuotaIva)['neto'];
                } catch (\DomainException) {
                    $alt['motivo'] = Tasas::MENSAJE_SIN_NETO;
                    $alternativas[] = $alt;
                    continue;
                }
                $cftea = Tasas::cftea($neto, $plan->totales());
                if (Tasas::excedeRegistro($cftea)) {
                    $alt['motivo'] = Tasas::MENSAJE_FUERA_DE_LIMITE;
                    $alternativas[] = $alt;
                    continue;
                }
                $alt['viable'] = true;
                $alt['tea'] = Tasas::tea($tna);
                $alt['cftea'] = $cftea;
                $alternativas[] = $alt;
            }
        }

        return ['monto_maximo' => $montoMaxFinanciable, 'alternativas' => $alternativas];
    }
}
