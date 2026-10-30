<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Finanzas\Decimal;

/**
 * CU-59 Configurar Parámetros de Estructuración Automática (RN-48, RN-68, RN-76, D-22). Parámetros globales del
 * cálculo de la capacidad de pago y de las alternativas; las tasas, gastos, montos y plazos son de cada línea.
 *
 * Forma guardada en versiones_parametros (tipo Estructuracion):
 *   porcentaje_afectacion '0.30', alicuota_iva '0.21', vigencia_alternativas_dias 7, paso_redondeo '1000',
 *   escalones ['1', '0.75', '0.5'] (proporciones del monto máximo, de mayor a menor), tope_global_cliente null o importe.
 */
final class ValidadorParametrosEstructuracion
{
    public const SUGERIDOS = [
        'porcentaje_afectacion' => '0.30',
        'alicuota_iva' => '0.21',
        'vigencia_alternativas_dias' => 7,
        'paso_redondeo' => '1000',
        'escalones' => ['1', '0.75', '0.5'],
        'tope_global_cliente' => null,
    ];

    /**
     * @param array<string, mixed> $d formulario: porcentajes como números (30 = 30 %), escalones separados por comas
     * @return array<string, mixed>
     */
    public static function normalizar(array $d): array
    {
        $pct = static fn (mixed $v): ?string => ($n = self::numero($v)) === null ? null : Decimal::of($n)->div(100)->round(4)->toFixed(4);
        $escalones = [];
        foreach (preg_split('/[;\s]+/', trim(str_replace('%', '', (string) ($d['escalones'] ?? '')))) ?: [] as $e) {
            if ($e !== '') {
                $escalones[] = $pct($e);
            }
        }
        $tope = trim((string) ($d['tope_global_cliente'] ?? ''));
        $dias = trim((string) ($d['vigencia_alternativas_dias'] ?? ''));

        return [
            'porcentaje_afectacion' => $pct($d['porcentaje_afectacion'] ?? ''),
            'alicuota_iva' => $pct($d['alicuota_iva'] ?? ''),
            'vigencia_alternativas_dias' => ctype_digit($dias) ? (int) $dias : null,
            'paso_redondeo' => Campos::importe($d['paso_redondeo'] ?? ''),
            'escalones' => $escalones,
            'tope_global_cliente' => $tope === '' ? null : (Campos::importe($tope) ?? 'invalido'),
        ];
    }

    /**
     * CU-59 paso 4 (A1).
     *
     * @param array<string, mixed> $p
     * @return array<string, string>
     */
    public static function validar(array $p): array
    {
        $e = [];
        $entre = static fn (?string $v, string $min, string $max): bool => $v !== null && Decimal::of($v)->compare($min) >= 0 && Decimal::of($v)->compare($max) <= 0;
        if (!$entre($p['porcentaje_afectacion'], '0.01', '1') || Decimal::of($p['porcentaje_afectacion'] ?? '0')->isZero()) {
            $e['porcentaje_afectacion'] = 'El porcentaje máximo de afectación debe estar entre 1 % y 100 %.';
        }
        if (!$entre($p['alicuota_iva'], '0', '1')) {
            $e['alicuota_iva'] = 'La alícuota de IVA debe estar entre 0 % (operación exenta) y 100 %.';
        }
        if (!is_int($p['vigencia_alternativas_dias']) || $p['vigencia_alternativas_dias'] < 1 || $p['vigencia_alternativas_dias'] > 30) {
            $e['vigencia_alternativas_dias'] = 'La vigencia de las alternativas debe estar entre 1 y 30 días corridos.';
        }
        if ($p['paso_redondeo'] === null || !Decimal::of($p['paso_redondeo'])->isPositive()) {
            $e['paso_redondeo'] = 'Ingrese el múltiplo de redondeo del monto máximo (por ejemplo, 1.000,00).';
        }
        $esc = $p['escalones'];
        if ($esc === [] || in_array(null, $esc, true)) {
            $e['escalones'] = 'Ingrese los escalones como porcentajes separados por espacios (por ejemplo, 100 75 50).';
        } elseif ($esc[0] !== '1.0000' || count(array_unique($esc)) !== count($esc)) {
            $e['escalones'] = 'El primer escalón debe ser 100 % (el monto máximo) y los escalones no pueden repetirse.';
        } else {
            for ($i = 1; $i < count($esc); $i++) {
                if (Decimal::of($esc[$i])->compare($esc[$i - 1]) >= 0 || !Decimal::of($esc[$i])->isPositive()) {
                    $e['escalones'] = 'Los escalones deben ser mayores que 0 % e ir de mayor a menor.';
                    break;
                }
            }
            if (count($esc) > 6) {
                $e['escalones'] = 'Se admiten hasta 6 escalones.';
            }
        }
        if ($p['tope_global_cliente'] === 'invalido' || ($p['tope_global_cliente'] !== null && !Decimal::of($p['tope_global_cliente'])->isPositive())) {
            $e['tope_global_cliente'] = 'Ingrese un importe mayor que cero o déjelo vacío para no aplicar tope.';
        }

        return $e;
    }

    /**
     * Valores del formulario a partir de los guardados (porcentajes enteros o con decimales).
     *
     * @param array<string, mixed> $p
     * @return array<string, string>
     */
    public static function aFormulario(array $p): array
    {
        $p += self::SUGERIDOS;
        $pct = static fn (string|int $v): string => str_replace('.', ',', rtrim(rtrim(Decimal::of((string) $v)->mul(100)->round(2)->toFixed(2), '0'), '.'));
        $importe = static fn (?string $v): string => $v === null ? '' : str_replace('.', ',', Decimal::of($v)->toFixed(2));

        return [
            'porcentaje_afectacion' => $pct($p['porcentaje_afectacion']),
            'alicuota_iva' => $pct($p['alicuota_iva']),
            'vigencia_alternativas_dias' => (string) $p['vigencia_alternativas_dias'],
            'paso_redondeo' => $importe((string) $p['paso_redondeo']),
            'escalones' => implode(' ', array_map($pct, (array) $p['escalones'])),
            'tope_global_cliente' => $importe($p['tope_global_cliente'] === null ? null : (string) $p['tope_global_cliente']),
        ];
    }

    private static function numero(mixed $v): ?string
    {
        $t = str_replace(',', '.', str_replace([' ', '%'], '', trim((string) ($v ?? ''))));

        return preg_match('/^\d{1,3}(\.\d{1,4})?$/', $t) === 1 ? $t : null;
    }
}
