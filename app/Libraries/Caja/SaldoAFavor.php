<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;

/**
 * Uso de los saldos a favor de un cliente (RN-57, CU-76, D-37): se consumen desde el más antiguo; lo reservado para
 * una devolución autorizada no está disponible (RN-73).
 */
final class SaldoAFavor
{
    /**
     * @param list<array<string, mixed>> $saldos filas de saldos_a_favor ordenadas por antigüedad
     */
    public static function disponible(array $saldos): Decimal
    {
        return Decimal::sum(array_map(static fn ($s) => Decimal::of((string) $s['importe_disponible']), $saldos));
    }

    /**
     * @param list<array<string, mixed>> $saldos filas de saldos_a_favor ordenadas por antigüedad
     * @return list<array{saldo: int, importe: Decimal}> importe a tomar de cada saldo
     */
    public static function distribuir(Decimal $importe, array $saldos): array
    {
        $resto = $importe;
        $r = [];
        foreach ($saldos as $s) {
            if (!$resto->isPositive()) {
                break;
            }
            $disp = Decimal::of((string) $s['importe_disponible']);
            if (!$disp->isPositive()) {
                continue;
            }
            $toma = Decimal::min($resto, $disp);
            $r[] = ['saldo' => (int) $s['id'], 'importe' => $toma];
            $resto = $resto->sub($toma);
        }

        return $r;
    }
}
