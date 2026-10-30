<?php

declare(strict_types=1);

namespace App\Libraries\Evaluacion;

use DateTimeImmutable;

/**
 * Resumen de la información de la Central de Deudores para el modelo de RN-69 (CU-86 pasos 5 y 7).
 *
 * - Se consideran los períodos desde doce meses antes del mes en curso: como el BCRA informa con uno o dos meses de
 *   demora, así quedan los últimos 12 períodos informados.
 * - Peor situación de 12 meses: la mayor situación informada por cualquier entidad en esos períodos.
 * - Situación vigente: la peor situación del último período informado (para las situaciones excluyentes de RN-69).
 * - Sin deudas informadas en esos períodos, las dos son nulas y el indicador toma el valor «sin deudas».
 */
final class AntecedentesBcra
{
    /**
     * @param list<array{entidad: string, periodo: string, situacion: int, monto: string}> $deudas
     * @return array{peor_12m: ?int, peor_vigente: ?int, ultimo_periodo: ?string, deudas: list<array{entidad: string, periodo: string, situacion: int, monto: string}>}
     */
    public static function resumir(array $deudas, string $hoy): array
    {
        $desde = self::periodoDesde($hoy);
        $consideradas = array_values(array_filter(
            $deudas,
            static fn (array $d): bool => preg_match('/^\d{6}$/', $d['periodo']) === 1 && $d['periodo'] >= $desde && $d['situacion'] >= 1 && $d['situacion'] <= 6,
        ));
        usort($consideradas, static fn (array $a, array $b): int => [$b['periodo'], $a['entidad']] <=> [$a['periodo'], $b['entidad']]);
        if ($consideradas === []) {
            return ['peor_12m' => null, 'peor_vigente' => null, 'ultimo_periodo' => null, 'deudas' => []];
        }
        $ultimo = $consideradas[0]['periodo'];
        $peor = max(array_column($consideradas, 'situacion'));
        $vigente = max(array_column(array_filter($consideradas, static fn (array $d): bool => $d['periodo'] === $ultimo), 'situacion'));

        return ['peor_12m' => (int) $peor, 'peor_vigente' => (int) $vigente, 'ultimo_periodo' => $ultimo, 'deudas' => $consideradas];
    }

    /** Primer período (AAAAMM) que se considera: doce meses antes del mes en curso. */
    public static function periodoDesde(string $hoy): string
    {
        return (new DateTimeImmutable(substr($hoy, 0, 10)))->modify('first day of this month')->modify('-12 months')->format('Ym');
    }
}
