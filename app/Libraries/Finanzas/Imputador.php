<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DomainException;

/**
 * Imputación de pagos y aplicaciones de saldo a favor (RN-29, RN-56, RN-57).
 *
 * Las cuotas se atienden en el orden recibido (de la más antigua a la más reciente) y, dentro de cada cuota, según la
 * prelación de RN-29: punitorios, intereses ordinarios, gastos y cargos, IVA, concepto no capitalizable (D-04) y
 * capital.
 *
 * Pago parcial (D-12): el cobro ordinario admite cualquier importe positivo que no supere el total exigible de las
 * cuotas seleccionadas. Si no alcanza para cancelar la última cuota, esa cuota recibe una imputación parcial y sigue
 * Pendiente o Vencida por su saldo. Solo las imputaciones excepcionales (RN-56, RN-57) pueden recibir un importe mayor
 * al total: el excedente vuelve como remanente para registrarse como saldo a favor.
 */
final class Imputador
{
    public const PRELACION = ['Punitorio', 'InteresOrdinario', 'GastosCargos', 'IVA', 'NoCapitalizable', 'Capital'];

    /**
     * @param list<array{id: int|string, conceptos: array<string, Decimal>}> $cuotas pendientes exigibles por concepto, con dos decimales
     * @param bool $excepcional true solo para RN-56 y RN-57: admite un importe mayor al total y devuelve el remanente
     *
     * @return array{imputaciones: list<array{cuota: int|string, concepto: string, importe: Decimal, parcial: bool}>, remanente: Decimal, canceladas: list<int|string>}
     */
    public static function imputar(Decimal $importe, array $cuotas, bool $excepcional = false): array
    {
        if (!$importe->isPositive()) {
            throw new DomainException('El importe a imputar debe ser positivo.');
        }
        $totalDeuda = Decimal::sum(array_map(static fn ($c) => self::totalCuota($c['conceptos']), $cuotas));
        if (!$excepcional && $importe->compare($totalDeuda) > 0) {
            throw new DomainException('El importe del cobro supera el total exigible de las cuotas seleccionadas (RN-29).');
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

    /**
     * Saldo de una cuota después de imputarle importes por concepto (pago parcial, D-12).
     *
     * @param array<string, Decimal> $pendientes
     * @param list<array{cuota: int|string, concepto: string, importe: Decimal, parcial: bool}> $imputaciones de esa cuota
     * @return array<string, Decimal>
     */
    public static function saldoDespues(array $pendientes, array $imputaciones): array
    {
        foreach ($imputaciones as $i) {
            $pendientes[$i['concepto']] = ($pendientes[$i['concepto']] ?? Decimal::zero())->sub($i['importe']);
        }

        return $pendientes;
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
