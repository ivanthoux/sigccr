<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Intereses punitorios simples por tramos diarios (RN-34, RN-35, RN-53).
 *
 * - Base: capital vencido pendiente; sin intereses, gastos ni capitalización.
 * - Tasa diaria aplicada: la menor entre la tasa configurada y el tope (porcentaje de la TNA de la línea / 365).
 * - Días de atraso: fecha local de pago − fecha de vencimiento. El atraso empieza a las 00:00 del día siguiente
 *   al vencimiento, por lo que un pago el día del vencimiento tiene cero días de atraso.
 * - Si el atraso no supera la tolerancia asignada al crédito, los tramos existen pero no son exigibles; al superarla
 *   son exigibles todos desde el primer día de atraso, cada uno con la tasa vigente en su fecha (RN-35).
 * - Cada tramo se conserva con cuatro decimales (resultado intermedio); el total exigible se redondea a dos.
 */
final class Punitorios
{
    public const DECIMALES_TASA = 12;

    public static function tasaDiaria(Decimal $tasaConfigurada, Decimal $topeSobreTna, Decimal $tna): Decimal
    {
        $tope = $topeSobreTna->mul($tna)->div(365);

        return Decimal::min($tasaConfigurada, $tope)->round(self::DECIMALES_TASA);
    }

    public static function diasAtraso(DateTimeImmutable $vencimiento, DateTimeImmutable $fechaPago): int
    {
        $v = $vencimiento->setTime(0, 0);
        $p = $fechaPago->setTimezone($vencimiento->getTimezone())->setTime(0, 0);
        $dias = (int) $v->diff($p)->format('%r%a');

        return max(0, $dias);
    }

    public static function esExigible(int $diasAtraso, int $tolerancia): bool
    {
        if ($tolerancia < 0) {
            throw new InvalidArgumentException('La tolerancia no puede ser negativa.');
        }

        return $diasAtraso > $tolerancia;
    }

    /** @return array{importe: Decimal, iva: Decimal} tramo de un día, con cuatro decimales */
    public static function tramo(Decimal $capitalBase, Decimal $tasaDiaria, Decimal $alicuotaIva): array
    {
        $importe = $capitalBase->mul($tasaDiaria)->round(4);

        return ['importe' => $importe, 'iva' => $importe->mul($alicuotaIva)->round(4)];
    }

    /**
     * Punitorios de una cuota vencida hasta una fecha de pago.
     *
     * @param callable(DateTimeImmutable): Decimal $tasaDiariaEnFecha tasa diaria aplicable a cada día (ya limitada por el tope)
     *
     * @return array{dias: int, exigible: bool, tramos: list<array{fecha: DateTimeImmutable, tasa: Decimal, base: Decimal, importe: Decimal, iva: Decimal}>, importe: Decimal, iva: Decimal}
     */
    public static function calcular(
        Decimal $capitalVencido,
        DateTimeImmutable $vencimiento,
        DateTimeImmutable $fechaPago,
        int $tolerancia,
        callable $tasaDiariaEnFecha,
        Decimal $alicuotaIva,
    ): array {
        $dias = self::diasAtraso($vencimiento, $fechaPago);
        $tramos = [];
        $total = Decimal::zero();
        $totalIva = Decimal::zero();
        for ($d = 1; $d <= $dias; $d++) {
            $fecha = $vencimiento->setTime(0, 0)->modify("+{$d} days");
            $tasa = $tasaDiariaEnFecha($fecha);
            $t = self::tramo($capitalVencido, $tasa, $alicuotaIva);
            $tramos[] = ['fecha' => $fecha, 'tasa' => $tasa, 'base' => $capitalVencido, 'importe' => $t['importe'], 'iva' => $t['iva']];
            $total = $total->add($t['importe']);
            $totalIva = $totalIva->add($t['iva']);
        }
        $exigible = self::esExigible($dias, $tolerancia);

        return [
            'dias' => $dias,
            'exigible' => $exigible,
            'tramos' => $tramos,
            'importe' => $exigible ? $total->round(2) : Decimal::zero(),
            'iva' => $exigible ? $totalIva->round(2) : Decimal::zero(),
        ];
    }
}
