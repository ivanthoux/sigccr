<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\GastoAdministrativo;
use App\Libraries\Finanzas\Plan;
use App\Libraries\Finanzas\Tasas;
use App\Libraries\Validacion\Campos;
use DateTimeImmutable;

/**
 * Simulación de un crédito con las condiciones de una versión de línea (CU-21 pasos 6 a 8; RN-14, RN-17, RN-48,
 * RN-50 a RN-52, RN-63, RN-68, RN-76).
 *
 * El plan es preliminar: se calcula con la fecha de negocio como fecha de liquidación, por lo que el calendario
 * definitivo se fija al liquidar (CU-27). No genera solicitud, aprobación ni deuda.
 */
final class Simulador
{
    /** Vigencia de una simulación en días corridos desde su generación (RN-48). */
    public const VIGENCIA_DIAS = 7;

    /**
     * Pasos 6 y 7: importe y cantidad de cuotas dentro de los límites de la versión (RN-14, RN-76).
     *
     * @param array{monto_minimo: string, monto_maximo: string, cuotas_minimas: int|string, cuotas_maximas: int|string} $version
     * @return array{importe: ?string, cuotas: ?int, errores: array<string, string>}
     */
    public static function validar(array $version, string $importeTexto, string $cuotasTexto): array
    {
        $errores = [];
        $minimo = Decimal::of($version['monto_minimo']);
        $maximo = Decimal::of($version['monto_maximo']);
        $limites = '$ ' . self::monto($minimo) . ' y $ ' . self::monto($maximo);
        $importe = Campos::texto($importeTexto) === '' ? null : Campos::importe($importeTexto);
        if (Campos::texto($importeTexto) === '') {
            $errores['importe'] = 'Ingrese el importe a financiar.';
        } elseif ($importe === null) {
            $errores['importe'] = 'Ingrese un importe válido, por ejemplo 850.000,00.';
        } elseif (Decimal::of($importe)->compare($minimo) < 0 || Decimal::of($importe)->compare($maximo) > 0) {
            $errores['importe'] = "La línea presta entre {$limites} (RN-76).";
        }
        $cmin = (int) $version['cuotas_minimas'];
        $cmax = (int) $version['cuotas_maximas'];
        $cuotas = preg_match('/^\d{1,3}$/', Campos::texto($cuotasTexto)) === 1 ? (int) Campos::texto($cuotasTexto) : null;
        if (Campos::texto($cuotasTexto) === '') {
            $errores['cantidad_cuotas'] = 'Ingrese la cantidad de cuotas.';
        } elseif ($cuotas === null) {
            $errores['cantidad_cuotas'] = 'La cantidad de cuotas debe ser un número entero.';
        } elseif ($cuotas < $cmin || $cuotas > $cmax) {
            $errores['cantidad_cuotas'] = $cmin === $cmax ? "La línea admite solo {$cmin} cuotas (RN-14)." : "La línea admite entre {$cmin} y {$cmax} cuotas (RN-14).";
        }

        return ['importe' => $errores === [] ? $importe : null, 'cuotas' => $errores === [] ? $cuotas : null, 'errores' => $errores];
    }

    /**
     * Paso 8: plan preliminar y costo financiero.
     *
     * @param array{tna: string, gasto_otorgamiento_pct: string, gasto_administrativo_cuota: string, tipo_gasto_administrativo?: string, gasto_administrativo_pct?: ?string} $version
     * @return array{plan: Plan, tasa_mensual: Decimal, tea: Decimal, cftea: Decimal, gasto_otorgamiento: Decimal,
     *     iva_otorgamiento: Decimal, neto: Decimal, primera_cuota: Decimal, total_intereses: Decimal, total_a_pagar: Decimal}
     */
    public static function calcular(array $version, string $importe, int $cuotas, string $alicuotaIva, DateTimeImmutable $fechaBase): array
    {
        $capital = Decimal::of($importe);
        $tna = Decimal::of($version['tna']);
        $iva = Decimal::of($alicuotaIva);
        $plan = AmortizacionFrancesa::plan($capital, $tna, $cuotas, $fechaBase, GastoAdministrativo::deVersion($version)->porCuota($capital), $iva);
        $desembolso = Tasas::desembolso($capital, Decimal::of($version['gasto_otorgamiento_pct']), $iva);

        return [
            'plan' => $plan,
            'tasa_mensual' => AmortizacionFrancesa::tasaMensual($tna),
            'tea' => Tasas::tea($tna),
            'cftea' => Tasas::cftea($desembolso['neto'], $plan->totales()),
            'gasto_otorgamiento' => $desembolso['gasto'],
            'iva_otorgamiento' => $desembolso['iva'],
            'neto' => $desembolso['neto'],
            'primera_cuota' => $plan->cuotaTotalMaxima(),
            'total_intereses' => $plan->totalIntereses(),
            'total_a_pagar' => $plan->totalAPagar(),
        ];
    }

    /** Importe con separador de miles y coma decimal, sin pasar por float (RN-50). */
    public static function monto(Decimal $d): string
    {
        [$entero, $dec] = explode('.', $d->abs()->toFixed(2));

        return ($d->isNegative() ? '-' : '') . strrev(implode('.', str_split(strrev($entero), 3))) . ',' . $dec;
    }
}
