<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use DomainException;

/**
 * Refinanciación de créditos en mora (RN-36, RN-37, RN-70; CU-39, CU-40, CU-74).
 *
 * - Comprende todas las cuotas no pagadas de cada crédito seleccionado.
 * - Capital del nuevo plan: capital pendiente de esas cuotas.
 * - Concepto no capitalizable: intereses ordinarios vencidos, gastos y su IVA, punitorios exigibles y su IVA, y el
 *   concepto no capitalizable pendiente de una refinanciación anterior. No devenga interés y se distribuye en partes
 *   iguales entre las nuevas cuotas.
 * - Los conceptos de las cuotas no vencidas distintos del capital (interés y gasto aún no devengados) no se incluyen.
 * - Al aplicar, se incorporan al concepto no capitalizable los punitorios devengados desde la fecha de corte, sin
 *   modificar el capital, la tasa ni la cantidad de cuotas aprobados.
 */
final class Refinanciacion
{
    /**
     * @param list<array{vencimiento: DateTimeImmutable, conceptos: array<string, Decimal>}> $cuotasNoPagadas
     *
     * @return array{capital: Decimal, no_capitalizable: Decimal}
     */
    public static function composicion(array $cuotasNoPagadas, DateTimeImmutable $fechaCorte): array
    {
        $capital = Decimal::zero();
        $noCap = Decimal::zero();
        $corte = $fechaCorte->setTime(0, 0);
        foreach ($cuotasNoPagadas as $c) {
            $k = $c['conceptos'];
            $capital = $capital->add($k['Capital'] ?? Decimal::zero());
            $noCap = $noCap->add($k['NoCapitalizable'] ?? Decimal::zero());
            if ($c['vencimiento']->setTime(0, 0) < $corte) {
                foreach (['InteresOrdinario', 'GastosCargos', 'IVA', 'Punitorio'] as $concepto) {
                    $noCap = $noCap->add($k[$concepto] ?? Decimal::zero());
                }
            }
        }
        if (!$capital->isPositive()) {
            throw new DomainException('No hay capital pendiente para refinanciar.');
        }

        return ['capital' => $capital, 'no_capitalizable' => $noCap];
    }

    public static function validarCondiciones(Decimal $tasa, int $cuotas, Decimal $tasaMinima, Decimal $tasaMaxima, int $cuotasMaximas): void
    {
        if ($tasa->compare($tasaMinima) < 0 || $tasa->compare($tasaMaxima) > 0) {
            throw new DomainException('La tasa está fuera de los límites configurados (RN-70).');
        }
        if ($cuotas < 1 || $cuotas > $cuotasMaximas) {
            throw new DomainException("La cantidad de cuotas debe estar entre 1 y {$cuotasMaximas} (RN-70).");
        }
    }

    /** Nuevo plan con primer vencimiento a los 30 días de la aplicación (RN-52, RN-70). */
    public static function plan(
        Decimal $capital,
        Decimal $noCapitalizable,
        Decimal $tasa,
        int $cuotas,
        DateTimeImmutable $fechaAplicacion,
        Decimal $alicuotaIva,
        ?Decimal $gastoPorCuota = null,
    ): Plan {
        return AmortizacionFrancesa::plan($capital, $tasa, $cuotas, $fechaAplicacion, $gastoPorCuota, $alicuotaIva, $noCapitalizable);
    }

    /** RN-70: al aplicar, el concepto no capitalizable incorpora los punitorios (con su IVA) devengados desde el corte. */
    public static function incorporarPunitorios(Decimal $noCapitalizable, Decimal $punitoriosDesdeCorte): Decimal
    {
        return $noCapitalizable->add($punitoriosDesdeCorte);
    }
}
