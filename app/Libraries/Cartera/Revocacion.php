<?php

declare(strict_types=1);

namespace App\Libraries\Cartera;

use App\Libraries\Finanzas\CalendarioHabil;
use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;

/**
 * Revocación del préstamo (RN-79, arts. 1110 a 1112 del Código Civil y Comercial, D-34): plazo de 10 días hábiles e
 * importe a restituir.
 */
final class Revocacion
{
    public const DIAS_HABILES = 10;

    /**
     * Último día del plazo: el décimo día hábil posterior al inicio, que es el día de la recepción del contrato o del
     * desembolso, lo que ocurra último.
     *
     * @param list<string> $diasNoHabiles fechas Y-m-d
     */
    public static function fechaLimite(string $recepcionContrato, string $desembolso, array $diasNoHabiles): string
    {
        $d = new DateTimeImmutable(max(substr($recepcionContrato, 0, 10), substr($desembolso, 0, 10)));
        $habiles = 0;
        while ($habiles < self::DIAS_HABILES) {
            $d = $d->modify('+1 day');
            if (CalendarioHabil::esHabil($d, $diasNoHabiles)) {
                $habiles++;
            }
        }

        return $d->format('Y-m-d');
    }

    /**
     * CU-102 paso 5: el cliente restituye el neto recibido más el interés compensatorio devengado por los días
     * corridos desde el desembolso hasta la comunicación (interés simple, TNA/365) y su IVA, sin gastos ni comisiones.
     * Lo que haya pagado se descuenta; si pagó más, el exceso queda como saldo a favor.
     *
     * @return array{dias: int, interes: Decimal, iva: Decimal, total: Decimal, restituir: Decimal, saldo_a_favor: Decimal}
     */
    public static function importe(Decimal $neto, Decimal $tna, string $desembolso, string $comunicacion, Decimal $alicuotaIva, Decimal $pagado): array
    {
        $dias = max(0, (int) (new DateTimeImmutable(substr($desembolso, 0, 10)))->diff(new DateTimeImmutable(substr($comunicacion, 0, 10)))->format('%r%a'));
        $interes = $neto->mul($tna)->mul($dias)->div(365)->round(2);
        $iva = $interes->mul($alicuotaIva)->round(2);
        $total = $neto->add($interes)->add($iva);
        $diferencia = $total->sub($pagado);

        return [
            'dias' => $dias, 'interes' => $interes, 'iva' => $iva, 'total' => $total,
            'restituir' => $diferencia->isNegative() ? Decimal::zero() : $diferencia,
            'saldo_a_favor' => $diferencia->isNegative() ? $diferencia->neg() : Decimal::zero(),
        ];
    }
}
