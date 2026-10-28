<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\ModeloEvaluacion;

/**
 * Parámetros del motor de evaluación crediticia (CU-58, RN-69, D-13).
 *
 * El formulario trabaja en porcentajes (pesos y relación cuota/ingreso) y los guarda en tanto por uno, con la misma
 * forma que ModeloEvaluacion::PARAMETROS_INICIALES, que son los valores sugeridos y se pueden restablecer.
 */
final class ValidadorParametrosEvaluacion
{
    public const INDICADORES = ['bcra' => 'Situación BCRA', 'comportamiento' => 'Comportamiento de pago', 'endeudamiento' => 'Endeudamiento', 'antiguedad' => 'Antigüedad'];
    public const RESULTADOS = ['Favorable' => 'Favorable', 'RequiereAnalisisGerencial' => 'Requiere análisis gerencial', 'Desfavorable' => 'Desfavorable'];
    public const CATEGORIAS = ['A', 'B', 'C', 'D', 'E'];
    public const VIGENCIA_SUGERIDA = 30;

    /**
     * Valores sugeridos (RN-69) con la vigencia de 3.5.1.
     *
     * @return array<string, mixed>
     */
    public static function sugeridos(): array
    {
        return ModeloEvaluacion::PARAMETROS_INICIALES + ['vigencia_dias' => self::VIGENCIA_SUGERIDA];
    }

    /**
     * Convierte los datos del formulario (porcentajes, filas de tramos) a la forma guardada.
     *
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    public static function normalizar(array $d): array
    {
        $num = static fn (mixed $v): ?string => self::numero($v);
        $entero = static fn (mixed $v): ?int => ($n = self::numero($v)) !== null && preg_match('/^\d+$/', $n) === 1 ? (int) $n : null;
        $pct = static fn (mixed $v): ?string => ($n = self::numero($v)) === null ? null : Decimal::of($n)->div(100)->round(4)->toFixed(4);

        $pesos = [];
        foreach (array_keys(self::INDICADORES) as $k) {
            $pesos[$k] = $pct($d['pesos'][$k] ?? '');
        }
        $bcra = [];
        foreach (['1', '2', '3', '4', '5', '6', 'sin_deudas'] as $k) {
            $bcra[$k] = $entero($d['bcra'][$k] ?? '');
        }
        $tramos = static function (array $filas, callable $limite) use ($entero): array {
            $r = [];
            foreach ($filas as $f) {
                if (trim((string) ($f['limite'] ?? '')) === '' && trim((string) ($f['valor'] ?? '')) === '') {
                    continue;
                }
                $r[] = [$limite($f['limite'] ?? ''), $entero($f['valor'] ?? '')];
            }

            return $r;
        };
        $categorias = [];
        $resultados = [];
        foreach (self::CATEGORIAS as $c) {
            $categorias[$c] = $entero($d['categorias'][$c] ?? '');
            $resultados[$c] = (string) ($d['resultados'][$c] ?? '');
        }
        $excluyentes = array_values(array_map('intval', array_filter((array) ($d['situaciones_excluyentes'] ?? []), static fn ($v) => preg_match('/^[1-6]$/', (string) $v) === 1)));
        sort($excluyentes);

        return [
            'pesos' => $pesos,
            'bcra' => $bcra,
            'comportamiento' => [
                'tramos' => $tramos((array) ($d['comportamiento']['tramos'] ?? []), $entero),
                'resto' => $entero($d['comportamiento']['resto'] ?? ''),
                'sin_antecedentes' => $entero($d['comportamiento']['sin_antecedentes'] ?? ''),
            ],
            'endeudamiento' => [
                'tramos' => $tramos((array) ($d['endeudamiento']['tramos'] ?? []), $pct),
                'resto' => $entero($d['endeudamiento']['resto'] ?? ''),
            ],
            'antiguedad' => [
                'tramos' => $tramos((array) ($d['antiguedad']['tramos'] ?? []), $entero),
                'resto' => $entero($d['antiguedad']['resto'] ?? ''),
            ],
            'categorias' => $categorias,
            'resultados' => $resultados,
            'situaciones_excluyentes' => $excluyentes,
            'vigencia_dias' => $entero($d['vigencia_dias'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $p parámetros normalizados
     * @return array<string, string> campo => mensaje
     */
    public static function validar(array $p): array
    {
        $e = [];
        $valor = static fn (?int $v): bool => $v !== null && $v >= 0 && $v <= 100;

        $suma = Decimal::zero();
        foreach (self::INDICADORES as $k => $nombre) {
            $w = $p['pesos'][$k] ?? null;
            if ($w === null || Decimal::of($w)->compare(0) < 0 || Decimal::of($w)->compare(1) > 0) {
                $e["pesos.{$k}"] = "El peso de {$nombre} debe estar entre 0 % y 100 %.";
                continue;
            }
            $suma = $suma->add($w);
        }
        if ($e === [] && !$suma->equals(Decimal::one())) {
            $e['pesos'] = 'Los pesos deben sumar 100 % (suman ' . $suma->mul(100)->round(2)->toFixed(2) . ' %).';
        }

        foreach ($p['bcra'] as $sit => $v) {
            if (!$valor($v)) {
                $e["bcra.{$sit}"] = 'Cada valor de la situación BCRA debe ser un entero entre 0 y 100.';
            }
        }

        self::validarTramos($e, 'comportamiento', $p['comportamiento']['tramos'], true, 'Los días de atraso deben crecer de un tramo al siguiente.');
        self::validarTramos($e, 'endeudamiento', array_map(static fn ($t) => [$t[0] === null ? null : Decimal::of($t[0])->mul(10000)->round(0)->toFixed(0), $t[1]], $p['endeudamiento']['tramos']), true, 'La relación cuota/ingreso debe crecer de un tramo al siguiente.');
        self::validarTramos($e, 'antiguedad', $p['antiguedad']['tramos'], false, 'Los meses de antigüedad deben ir de mayor a menor.');
        foreach (['comportamiento.resto' => $p['comportamiento']['resto'], 'comportamiento.sin_antecedentes' => $p['comportamiento']['sin_antecedentes'], 'endeudamiento.resto' => $p['endeudamiento']['resto'], 'antiguedad.resto' => $p['antiguedad']['resto']] as $campo => $v) {
            if (!$valor($v)) {
                $e[$campo] = 'Ingrese un valor entero entre 0 y 100.';
            }
        }

        $anterior = 101;
        foreach (self::CATEGORIAS as $c) {
            $v = $p['categorias'][$c];
            if (!$valor($v)) {
                $e["categorias.{$c}"] = "El puntaje mínimo de la categoría {$c} debe estar entre 0 y 100.";
            } elseif ($v >= $anterior) {
                $e["categorias.{$c}"] = "El puntaje mínimo de la categoría {$c} debe ser menor que el de la categoría anterior.";
            }
            $anterior = $v ?? $anterior;
            if (!isset(self::RESULTADOS[$p['resultados'][$c]])) {
                $e["resultados.{$c}"] = "Seleccione el resultado de la categoría {$c}.";
            }
        }
        if (!isset($e['categorias.E']) && $p['categorias']['E'] !== 0) {
            $e['categorias.E'] = 'La categoría E debe comenzar en 0 para que todo puntaje tenga categoría.';
        }
        if (($p['resultados']['A'] ?? '') === 'Desfavorable' || ($p['resultados']['E'] ?? '') === 'Favorable') {
            $e['resultados'] = 'Los resultados deben ser coherentes con el riesgo: A no puede ser Desfavorable ni E Favorable.';
        }

        $vig = $p['vigencia_dias'];
        if ($vig === null || $vig < 1 || $vig > 365) {
            $e['vigencia_dias'] = 'La vigencia de la evaluación debe estar entre 1 y 365 días.';
        }

        return $e;
    }

    /**
     * @param array<string, string> $e
     * @param list<array{0: mixed, 1: ?int}> $tramos
     */
    private static function validarTramos(array &$e, string $campo, array $tramos, bool $creciente, string $mensajeOrden): void
    {
        if ($tramos === []) {
            $e["{$campo}.tramos"] = 'Defina al menos un tramo.';

            return;
        }
        $previo = null;
        foreach ($tramos as [$limite, $v]) {
            if ($limite === null || $v === null || $v < 0 || $v > 100) {
                $e["{$campo}.tramos"] = 'Complete cada tramo con un límite válido y un valor entero entre 0 y 100.';

                return;
            }
            if ($previo !== null && ($creciente ? (int) $limite <= (int) $previo : (int) $limite >= (int) $previo)) {
                $e["{$campo}.tramos"] = $mensajeOrden;

                return;
            }
            $previo = $limite;
        }
    }

    private static function numero(mixed $v): ?string
    {
        $t = str_replace([' ', '%'], '', trim((string) ($v ?? '')));
        $t = str_replace(',', '.', $t);

        return preg_match('/^\d{1,6}(\.\d{1,4})?$/', $t) === 1 ? $t : null;
    }

    /**
     * Forma del formulario a partir de los parámetros guardados (porcentajes para pesos y endeudamiento).
     *
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    public static function aFormulario(array $p): array
    {
        $pct = static fn (string|int $v): string => rtrim(rtrim(Decimal::of((string) $v)->mul(100)->round(2)->toFixed(2), '0'), '.');
        $f = $p;
        foreach ($p['pesos'] as $k => $v) {
            $f['pesos'][$k] = $pct($v);
        }
        $fila = static fn (array $t): array => ['limite' => (string) $t[0], 'valor' => (string) $t[1]];
        $f['comportamiento']['tramos'] = array_map($fila, $p['comportamiento']['tramos']);
        $f['endeudamiento']['tramos'] = array_map(static fn ($t) => ['limite' => $pct($t[0]), 'valor' => (string) $t[1]], $p['endeudamiento']['tramos']);
        $f['antiguedad']['tramos'] = array_map($fila, $p['antiguedad']['tramos']);
        $f['vigencia_dias'] = $p['vigencia_dias'] ?? self::VIGENCIA_SUGERIDA;

        return $f;
    }
}
