<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DomainException;

/**
 * Imputación de pagos y aplicaciones de saldo a favor (RN-29, RN-56, RN-57).
 *
 * Las cuotas se atienden en el orden recibido (de la más antigua a la más reciente) y, dentro de cada cuota, según la
 * prelación de RN-29: punitorios, intereses ordinarios, gastos y cargos, IVA y capital. El concepto no capitalizable
 * de las cuotas refinanciadas se imputa antes del capital (pendiente de validación, ver observaciones de 4.1.1).
 */
final class Imputador
{
    public const PRELACION = ['Punitorio', 'InteresOrdinario', 'GastosCargos', 'IVA', 'NoCapitalizable', 'Capital'];

    /**
     * @param list<array{id: int|string, conceptos: array<string, Decimal>}> $cuotas pendientes exigibles por concepto, con dos decimales
     * @param bool $parcialExcepcional true solo para RN-56 y RN-57; en el cobro ordinario el importe debe cubrir cuotas completas
     *
     * @return array{imputaciones: list<array{cuota: int|string, concepto: string, importe: Decimal, parcial: bool}>, remanente: Decimal, canceladas: list<int|string>}
     */
    public static function imputar(Decimal $importe, array $cuotas, bool $parcialExcepcional = false): array
    {
        if (!$importe->isPositive()) {
            throw new DomainException('El importe a imputar debe ser positivo.');
        }
        $totalDeuda = Decimal::sum(array_map(static fn ($c) => self::totalCuota($c['conceptos']), $cuotas));
        if (!$parcialExcepcional && !$importe->equals($totalDeuda)) {
            throw new DomainException('El cobro ordinario debe coincidir con el total exigible de las cuotas seleccionadas (RN-29).');
        }

        $resto = $importe;
        $imputaciones = [];
        $canceladas = [];
        foreach ($cuotas as $cuota) {
            $completa = true;
            foreach (self::PRELACION as $concepto) {
                $pendiente = $cuota['conceptos'][$concepto] ?? Decimal::zero();
                if (!$pendiente->isPositive()) {
                    continue;
                }
                if (!$resto->isPositive()) {
                    $completa = false;
                    break;
                }
                $aplicado = Decimal::min($resto, $pendiente);
                $parcial = $aplicado->compare($pendiente) < 0;
                $imputaciones[] = ['cuota' => $cuota['id'], 'concepto' => $concepto, 'importe' => $aplicado, 'parcial' => $parcial];
                $resto = $resto->sub($aplicado);
                if ($parcial) {
                    $completa = false;
                    break;
                }
            }
            if ($completa) {
                $canceladas[] = $cuota['id'];
            }
            if (!$resto->isPositive()) {
                break;
            }
        }

        return ['imputaciones' => $imputaciones, 'remanente' => $resto, 'canceladas' => $canceladas];
    }

    /** @param array<string, Decimal> $conceptos */
    public static function totalCuota(array $conceptos): Decimal
    {
        return Decimal::sum(array_values($conceptos));
    }

    /**
     * RN-29: se atiende primero la obligación exigible más antigua y la selección debe ser consecutiva.
     *
     * @param list<int> $pendientesOrdenadas números de las cuotas no pagadas del crédito, en orden ascendente
     * @param list<int> $seleccion           números elegidos por el cajero
     */
    public static function seleccionConsecutiva(array $pendientesOrdenadas, array $seleccion): bool
    {
        if ($seleccion === []) {
            return false;
        }
        sort($seleccion);

        return $seleccion === array_slice($pendientesOrdenadas, 0, count($seleccion));
    }
}
