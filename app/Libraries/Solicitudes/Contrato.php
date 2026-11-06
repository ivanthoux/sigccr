<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Punitorios;
use App\Libraries\Validacion\Campos;

/**
 * Contrato de préstamo (RN-78, art. 36 de la Ley 24.240): validación de la constancia firmada y datos derivados que
 * el contrato informa.
 */
final class Contrato
{
    /** Tipo de documento del legajo para la constancia firmada; inactivo para no ofrecerse como exigible. */
    public const TIPO_DOCUMENTO = 'CONTRATO_FIRMADO';

    /** Plazo de revocación en días hábiles (RN-79, art. 1110 del Código Civil y Comercial). */
    public const DIAS_REVOCACION = 10;

    /**
     * CU-103 paso 5 (A1): la fecha de recepción del contrato no puede ser anterior a la aprobación ni futura.
     *
     * @return array{fecha: ?string, error: ?string}
     */
    public static function validarRecepcion(string $texto, string $fechaAprobacion, string $hoy): array
    {
        if (trim($texto) === '') {
            return ['fecha' => null, 'error' => 'Indique la fecha en que el cliente recibió su ejemplar firmado.'];
        }
        $f = Campos::fecha($texto);
        if ($f === null) {
            return ['fecha' => null, 'error' => 'Ingrese una fecha válida (dd/mm/aaaa).'];
        }
        if ($f < substr($fechaAprobacion, 0, 10)) {
            return ['fecha' => null, 'error' => 'La fecha de recepción no puede ser anterior a la aprobación (' . date('d/m/Y', (int) strtotime($fechaAprobacion)) . ').'];
        }
        if ($f > $hoy) {
            return ['fecha' => null, 'error' => 'La fecha de recepción no puede ser futura.'];
        }

        return ['fecha' => $f, 'error' => null];
    }

    /**
     * Tasa punitoria que informa el contrato: la diaria de los parámetros de morosidad, limitada por el tope sobre la
     * TNA (RN-34), y su equivalente nominal anual.
     *
     * @param array<string, mixed> $morosidad valores de la versión de parámetros de morosidad
     * @return array{diaria: Decimal, anual: Decimal, tolerancia: int}
     */
    public static function punitorios(array $morosidad, Decimal $tna): array
    {
        $diaria = Punitorios::tasaDiaria(Decimal::of((string) $morosidad['tasa_punitoria_diaria']), Decimal::of((string) $morosidad['tope_sobre_tna']), $tna);

        return ['diaria' => $diaria, 'anual' => $diaria->mul(365), 'tolerancia' => (int) ($morosidad['tolerancia_dias'] ?? 0)];
    }
}
