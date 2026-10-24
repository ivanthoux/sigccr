<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calendario de vencimientos de un plan (RN-52).
 *
 * - Primer vencimiento: 30 días corridos después de la fecha local de liquidación (o de aplicación de una refinanciación, RN-70).
 * - Día ancla: el día del mes del primer vencimiento. Los siguientes son mensuales; si el día ancla no existe en un mes,
 *   se usa el último día de ese mes, conservando el ancla para los meses posteriores.
 * - Los vencimientos no se trasladan por fines de semana ni feriados.
 */
final class CalendarioCuotas
{
    public const DIAS_PRIMER_VENCIMIENTO = 30;

    /**
     * @return list<DateTimeImmutable> fechas locales (hora 00:00), una por cuota
     */
    public static function vencimientos(DateTimeImmutable $fechaBase, int $cantidad): array
    {
        if ($cantidad < 1) {
            throw new InvalidArgumentException('La cantidad de cuotas debe ser al menos 1.');
        }
        $primero = $fechaBase->setTime(0, 0)->modify('+' . self::DIAS_PRIMER_VENCIMIENTO . ' days');
        $ancla = (int) $primero->format('j');
        $anio = (int) $primero->format('Y');
        $mes = (int) $primero->format('n');

        $fechas = [];
        for ($k = 0; $k < $cantidad; $k++) {
            $m = $mes + $k;
            $a = $anio + intdiv($m - 1, 12);
            $m = (($m - 1) % 12) + 1;
            $ultimo = self::diasDelMes($a, $m);
            $fechas[] = $primero->setDate($a, $m, min($ancla, $ultimo));
        }

        return $fechas;
    }

    /** Días del mes sin depender de la extensión calendar. */
    public static function diasDelMes(int $anio, int $mes): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes)))->format('t');
    }
}
