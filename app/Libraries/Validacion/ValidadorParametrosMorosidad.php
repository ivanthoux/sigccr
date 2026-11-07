<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Finanzas\Decimal;

/**
 * CU-60 Configurar Parámetros de Morosidad (RN-34, RN-35, RN-38, RN-53, RN-65, RN-70).
 *
 * Forma guardada en versiones_parametros (tipo Morosidad):
 *   tasa_punitoria_diaria '0.0012' (0,12 % diario), tope_sobre_tna '0.50', tolerancia_dias 3,
 *   umbral_morosidad_avanzada_dias 60, limite_condonacion '1.00',
 *   refinanciacion {tasa_minima '0.30', tasa_maxima '0.80', cuotas_maximas 36}.
 */
final class ValidadorParametrosMorosidad
{
    public const SUGERIDOS = [
        'tasa_punitoria_diaria' => '0.0012',
        'tope_sobre_tna' => '0.50',
        'tolerancia_dias' => 3,
        'umbral_morosidad_avanzada_dias' => 60,
        'limite_condonacion' => '1.00',
        'refinanciacion' => ['tasa_minima' => '0.30', 'tasa_maxima' => '0.80', 'cuotas_maximas' => 36],
    ];

    /**
     * @param array<string, mixed> $d formulario: porcentajes como números (0,12 = 0,12 %), días y cuotas enteros
     * @return array<string, mixed>
     */
    public static function normalizar(array $d): array
    {
        $pct = static fn (mixed $v, int $escala): ?string => ($n = self::numero($v)) === null ? null : Decimal::of($n)->div(100)->round($escala)->toFixed($escala);
        $entero = static fn (mixed $v): ?int => ctype_digit($t = trim((string) ($v ?? ''))) && strlen($t) <= 4 ? (int) $t : null;

        return [
            'tasa_punitoria_diaria' => $pct($d['tasa_punitoria_diaria'] ?? '', 6),
            'tope_sobre_tna' => $pct($d['tope_sobre_tna'] ?? '', 4),
            'tolerancia_dias' => $entero($d['tolerancia_dias'] ?? ''),
            'umbral_morosidad_avanzada_dias' => $entero($d['umbral_morosidad_avanzada_dias'] ?? ''),
            'limite_condonacion' => $pct($d['limite_condonacion'] ?? '', 4),
            'refinanciacion' => [
                'tasa_minima' => $pct($d['refinanciacion_tasa_minima'] ?? '', 4),
                'tasa_maxima' => $pct($d['refinanciacion_tasa_maxima'] ?? '', 4),
                'cuotas_maximas' => $entero($d['refinanciacion_cuotas_maximas'] ?? ''),
            ],
        ];
    }

    /**
     * CU-60 paso 4 (A1).
     *
     * @param array<string, mixed> $p
     * @return array<string, string>
     */
    public static function validar(array $p): array
    {
        $e = [];
        $entre = static fn (?string $v, string $min, string $max): bool => $v !== null && Decimal::of($v)->compare($min) >= 0 && Decimal::of($v)->compare($max) <= 0;
        if (!$entre($p['tasa_punitoria_diaria'], '0', '0.01')) {
            $e['tasa_punitoria_diaria'] = 'La tasa punitoria diaria debe estar entre 0 % y 1 % por día.';
        }
        if (!$entre($p['tope_sobre_tna'], '0', '0.5')) {
            $e['tope_sobre_tna'] = 'El tope debe estar entre 0 % y 50 % de la TNA: los punitorios no pueden superar la mitad de la tasa compensatoria (art. 769 del Código Civil y Comercial).';
        }
        if (!is_int($p['tolerancia_dias']) || $p['tolerancia_dias'] > 30) {
            $e['tolerancia_dias'] = 'La tolerancia debe ser un número de días corridos entre 0 y 30.';
        }
        if (!is_int($p['umbral_morosidad_avanzada_dias']) || $p['umbral_morosidad_avanzada_dias'] > 365) {
            $e['umbral_morosidad_avanzada_dias'] = 'El umbral de morosidad avanzada debe ser un número de días entre 1 y 365.';
        } elseif (is_int($p['tolerancia_dias']) && $p['umbral_morosidad_avanzada_dias'] <= $p['tolerancia_dias']) {
            $e['umbral_morosidad_avanzada_dias'] = 'El umbral de morosidad avanzada debe ser mayor que la tolerancia (RN-65).';
        }
        if (!$entre($p['limite_condonacion'], '0', '1')) {
            $e['limite_condonacion'] = 'El límite de condonación debe estar entre 0 % y 100 % de los punitorios pendientes (RN-38).';
        }
        $r = $p['refinanciacion'];
        if (!$entre($r['tasa_minima'], '0', '5')) {
            $e['refinanciacion_tasa_minima'] = 'Ingrese la TNA mínima de refinanciación (entre 0 % y 500 %).';
        }
        if (!$entre($r['tasa_maxima'], '0', '5')) {
            $e['refinanciacion_tasa_maxima'] = 'Ingrese la TNA máxima de refinanciación (entre 0 % y 500 %).';
        } elseif ($r['tasa_minima'] !== null && Decimal::of($r['tasa_maxima'])->compare($r['tasa_minima']) <= 0) {
            $e['refinanciacion_tasa_maxima'] = 'La TNA máxima de refinanciación debe ser mayor que la mínima.';
        }
        if (!is_int($r['cuotas_maximas']) || $r['cuotas_maximas'] < 1 || $r['cuotas_maximas'] > 120) {
            $e['refinanciacion_cuotas_maximas'] = 'La cantidad máxima de cuotas de una refinanciación debe estar entre 1 y 120.';
        }

        return $e;
    }

    /**
     * Valores del formulario a partir de los guardados.
     *
     * @param array<string, mixed> $p
     * @return array<string, string>
     */
    public static function aFormulario(array $p): array
    {
        $p += self::SUGERIDOS;
        $r = (array) $p['refinanciacion'] + self::SUGERIDOS['refinanciacion'];
        $pct = static fn (string|int $v, int $d = 2): string => str_replace('.', ',', rtrim(rtrim(Decimal::of((string) $v)->mul(100)->round($d)->toFixed($d), '0'), '.'));

        return [
            'tasa_punitoria_diaria' => $pct($p['tasa_punitoria_diaria'], 4),
            'tope_sobre_tna' => $pct($p['tope_sobre_tna']),
            'tolerancia_dias' => (string) $p['tolerancia_dias'],
            'umbral_morosidad_avanzada_dias' => (string) $p['umbral_morosidad_avanzada_dias'],
            'limite_condonacion' => $pct($p['limite_condonacion']),
            'refinanciacion_tasa_minima' => $pct($r['tasa_minima']),
            'refinanciacion_tasa_maxima' => $pct($r['tasa_maxima']),
            'refinanciacion_cuotas_maximas' => (string) $r['cuotas_maximas'],
        ];
    }

    private static function numero(mixed $v): ?string
    {
        $t = str_replace(',', '.', str_replace([' ', '%'], '', trim((string) ($v ?? ''))));

        return preg_match('/^\d{1,3}(\.\d{1,4})?$/', $t) === 1 ? $t : null;
    }
}
