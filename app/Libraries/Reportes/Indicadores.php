<?php

declare(strict_types=1);

namespace App\Libraries\Reportes;

use App\Libraries\Finanzas\Decimal;

/**
 * Indicadores de gestión (RN-74) y clasificación de deudores del régimen informativo (RN-84).
 */
final class Indicadores
{
    /** RN-74: tramos de atraso de los reportes. */
    public const TRAMOS = ['1-30' => '1 a 30 días', '31-60' => '31 a 60 días', '61-90' => '61 a 90 días', '90+' => 'Más de 90 días'];

    /** RN-84: situación de cada deudor según su obligación más atrasada (cartera de consumo del BCRA). */
    public const SITUACIONES = [
        1 => 'Situación normal (hasta 31 días)',
        2 => 'Riesgo bajo (hasta 90 días)',
        3 => 'Riesgo medio (hasta 180 días)',
        4 => 'Riesgo alto (hasta 365 días)',
        5 => 'Irrecuperable (más de 365 días)',
    ];

    /**
     * Cociente en porcentaje con dos decimales; 0 si el denominador es cero. Lo usan el índice de morosidad (a), la
     * efectividad de cobranza (b), la conversión de campañas (d) y las tasas de aprobación (e).
     */
    public static function porcentaje(string|int $numerador, string|int $denominador): string
    {
        $den = Decimal::of((string) $denominador);
        if (!$den->isPositive()) {
            return '0.00';
        }

        return Decimal::of((string) $numerador)->mul(100)->div($den)->round(2)->toFixed(2);
    }

    public static function tramo(int $dias): ?string
    {
        return match (true) {
            $dias <= 0 => null,
            $dias <= 30 => '1-30',
            $dias <= 60 => '31-60',
            $dias <= 90 => '61-90',
            default => '90+',
        };
    }

    public static function situacion(int $dias): int
    {
        return match (true) {
            $dias <= 31 => 1,
            $dias <= 90 => 2,
            $dias <= 180 => 3,
            $dias <= 365 => 4,
            default => 5,
        };
    }
}
