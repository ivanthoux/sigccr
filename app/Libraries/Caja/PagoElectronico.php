<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reglas de los pagos electrónicos (CU-34, CU-90, CU-91; RN-30, RN-54 a RN-56).
 *
 * El estado que informa la pasarela se conserva sin traducir; aquí se clasifica en aprobado, rechazado o pendiente. Un
 * estado que no se reconoce es pendiente: no se interpreta como pago fallido que habilite repetirlo (RN-30).
 */
final class PagoElectronico
{
    /** Estados de la pasarela de referencia (Mercado Pago) y del simulador. */
    public const APROBADOS = ['approved', 'accredited', 'aprobado'];
    public const RECHAZADOS = ['rejected', 'cancelled', 'refunded', 'charged_back', 'rechazado'];

    /** Horas en conciliación desde las que se alerta al Gerente (CU-91 A2). */
    public const HORAS_ALERTA_CONCILIACION = 48;

    /** Minutos sin confirmación desde los que CU-91 consulta la orden. */
    public const MINUTOS_CONSULTA = 10;

    /** @return 'aprobado'|'rechazado'|'pendiente' */
    public static function clasificar(string $estado): string
    {
        $e = strtolower(trim($estado));

        return match (true) {
            in_array($e, self::APROBADOS, true) => 'aprobado',
            in_array($e, self::RECHAZADOS, true) => 'rechazado',
            default => 'pendiente',
        };
    }

    /** RN-54: la orden vence a las 00:00 locales del día siguiente a su emisión (límite exclusivo). */
    public static function vencimiento(DateTimeImmutable $emision, string $zona): DateTimeImmutable
    {
        return $emision->setTimezone(new DateTimeZone($zona))->setTime(0, 0)->modify('+1 day');
    }

    /**
     * CU-90 paso 6 (A4, A5): incidencias de una transacción aprobada respecto de su orden. Una moneda distinta se
     * trata como importe distinto: no se convierte.
     *
     * @return list<'ImporteDistinto'|'FueraDePlazo'>
     */
    public static function incidencias(string $importeOrden, string $importePago, string $moneda, DateTimeImmutable $vence, DateTimeImmutable $fechaEfectiva): array
    {
        $i = [];
        if (strtoupper($moneda) !== 'ARS' || Decimal::of($importeOrden)->compare($importePago) !== 0) {
            $i[] = 'ImporteDistinto';
        }
        if ($fechaEfectiva >= $vence) {
            $i[] = 'FueraDePlazo';
        }

        return $i;
    }
}
