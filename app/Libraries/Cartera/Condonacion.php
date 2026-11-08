<?php

declare(strict_types=1);

namespace App\Libraries\Cartera;

use App\Libraries\Finanzas\Decimal;

/**
 * Condonación de intereses punitorios (RN-38, CU-44, D-36).
 *
 * - Máximo condonable: porcentaje configurado de los punitorios pendientes exigibles de las cuotas elegidas,
 *   redondeado a centavos hacia abajo. Los importes son sin IVA.
 * - El importe se distribuye desde la cuota más antigua. En cada cuota, el IVA del punitorio se reduce en la misma
 *   proporción que el punitorio; si se condona todo el punitorio de la cuota, también todo su IVA.
 * - No modifica el capital ni otros conceptos.
 */
final class Condonacion
{
    /** @param list<array<string, mixed>> $cuotas filas de cuotas */
    public static function maximo(array $cuotas, Decimal $porcentaje): Decimal
    {
        $total = Decimal::sum(array_map(static fn ($c) => Decimal::of((string) $c['punitorio_pendiente'])->round(2), $cuotas));

        return $total->mul($porcentaje)->truncate(2);
    }

    /**
     * @param list<array<string, mixed>> $cuotas filas de cuotas ordenadas por antigüedad
     * @return list<array{cuota: int, importe: Decimal, iva: Decimal, punitorio_pendiente: Decimal, iva_punitorio_pendiente: Decimal}>
     */
    public static function distribuir(Decimal $importe, array $cuotas): array
    {
        $resto = $importe;
        $r = [];
        foreach ($cuotas as $c) {
            if (!$resto->isPositive()) {
                break;
            }
            $pun = Decimal::of((string) $c['punitorio_pendiente']);
            $ivaPun = Decimal::of((string) $c['iva_punitorio_pendiente']);
            $disponible = $pun->round(2);
            if (!$disponible->isPositive()) {
                continue;
            }
            if ($resto->compare($disponible) >= 0) {
                $parte = $pun;
                $iva = $ivaPun;
                $resto = $resto->sub($disponible);
            } else {
                $parte = $resto;
                $iva = $ivaPun->mul($resto)->div($pun)->round(4);
                $resto = Decimal::zero();
            }
            $r[] = [
                'cuota' => (int) $c['id'], 'importe' => $parte->round(4), 'iva' => $iva,
                'punitorio_pendiente' => Decimal::max(Decimal::zero(), $pun->sub($parte))->round(4),
                'iva_punitorio_pendiente' => Decimal::max(Decimal::zero(), $ivaPun->sub($iva))->round(4),
            ];
        }

        return $r;
    }
}
