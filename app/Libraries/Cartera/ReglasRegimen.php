<?php

declare(strict_types=1);

namespace App\Libraries\Cartera;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Reportes\Indicadores;
use App\Libraries\Finanzas\CalendarioHabil;
use DateTimeImmutable;

/**
 * CU-105 Generar Régimen Informativo de Deudores (RN-84, D-44): período a generar, clasificación de cada deudor y
 * archivo. El archivo sigue un formato propio, separado por punto y coma, con los datos que exige la Central de
 * Deudores (identificación, situación y saldo). La adaptación al diseño de registro vigente del BCRA queda fuera del
 * alcance (D-44).
 */
final class ReglasRegimen
{
    public const COLUMNAS = ['cuit_cuil', 'denominacion', 'situacion', 'saldo', 'dias_atraso', 'creditos'];

    /**
     * Mes cerrado que corresponde generar: el anterior, a partir del primer día hábil del mes en curso (precondición).
     *
     * @param list<string> $diasNoHabiles fechas Y-m-d
     */
    public static function periodoACerrar(string $hoy, array $diasNoHabiles): ?string
    {
        $dia = new DateTimeImmutable(substr($hoy, 0, 7) . '-01');
        while (!CalendarioHabil::esHabil($dia, $diasNoHabiles)) {
            $dia = $dia->modify('+1 day');
        }

        return $hoy >= $dia->format('Y-m-d') ? (new DateTimeImmutable(substr($hoy, 0, 7) . '-01'))->modify('-1 month')->format('Y-m') : null;
    }

    /** Último día del período aaaa-mm. */
    public static function cierre(string $periodo): string
    {
        return (new DateTimeImmutable($periodo . '-01'))->modify('last day of this month')->format('Y-m-d');
    }

    /** Un período es válido si tiene el formato aaaa-mm y ya cerró. */
    public static function periodoCerrado(string $periodo, string $hoy): bool
    {
        // H-30: desde 1900, como las fechas del sistema; el año 0000 no es una fecha válida en SQL Server
        return preg_match('/^(19|[2-9]\d)\d{2}-(0[1-9]|1[0-2])$/', $periodo) === 1 && $periodo < substr($hoy, 0, 7);
    }

    /**
     * Pasos 2 y 3: agrupa las cuotas impagas de las financiaciones vigentes por deudor; los días de atraso son los de
     * su obligación más atrasada al cierre y la situación sale de RN-84.
     *
     * @param list<array{cliente_id: int|string, cuit_cuil: string, denominacion: string, credito_id: int|string, vencimiento: string, pendiente: string}> $cuotas
     * @return list<array{cliente_id: int, cuit_cuil: string, denominacion: string, dias_atraso: int, situacion: int, saldo: string, creditos: int}>
     */
    public static function deudores(array $cuotas, string $cierre): array
    {
        $fin = new DateTimeImmutable($cierre);
        $g = [];
        foreach ($cuotas as $q) {
            $id = (int) $q['cliente_id'];
            $d = $g[$id] ?? ['cliente_id' => $id, 'cuit_cuil' => (string) $q['cuit_cuil'], 'denominacion' => (string) $q['denominacion'], 'dias_atraso' => 0, 'saldo' => Decimal::zero(), 'creditos' => []];
            $venc = new DateTimeImmutable(substr((string) $q['vencimiento'], 0, 10));
            if ($venc < $fin) {
                $d['dias_atraso'] = max($d['dias_atraso'], (int) $venc->diff($fin)->days);
            }
            $d['saldo'] = $d['saldo']->add((string) $q['pendiente']);
            $d['creditos'][(int) $q['credito_id']] = true;
            $g[$id] = $d;
        }
        $r = array_map(static fn (array $d): array => [
            'cliente_id' => $d['cliente_id'], 'cuit_cuil' => $d['cuit_cuil'], 'denominacion' => $d['denominacion'], 'dias_atraso' => $d['dias_atraso'],
            'situacion' => Indicadores::situacion($d['dias_atraso']), 'saldo' => $d['saldo']->toFixed(2), 'creditos' => count($d['creditos']),
        ], array_values($g));
        usort($r, static fn ($a, $b) => [$b['situacion'], $a['denominacion']] <=> [$a['situacion'], $b['denominacion']]);

        return $r;
    }

    /**
     * Paso 4: contenido del archivo. Primera línea de cabecera; luego una línea por deudor.
     *
     * @param list<array<string, mixed>> $deudores
     */
    public static function archivo(string $periodo, int $version, string $cierre, array $deudores): string
    {
        $limpiar = static fn (string $t): string => trim(str_replace([';', "\r", "\n"], [',', ' ', ' '], $t));
        $saldo = Decimal::sum(array_map(static fn ($d) => Decimal::of((string) $d['saldo']), $deudores))->toFixed(2);
        $lineas = ["CAB;{$periodo};{$version};{$cierre};" . count($deudores) . ";{$saldo}"];
        foreach ($deudores as $d) {
            $lineas[] = implode(';', ['DEU', $d['cuit_cuil'], $limpiar((string) $d['denominacion']), $d['situacion'], $d['saldo'], $d['dias_atraso'], $d['creditos']]);
        }

        return implode("\r\n", $lineas) . "\r\n";
    }

    /** Distribución de los deudores por situación (1 a 5): cantidad y saldo. @return array<int, array{0: int, 1: string}> */
    public static function porSituacion(array $deudores): array
    {
        $r = [];
        foreach (array_keys(Indicadores::SITUACIONES) as $s) {
            $g = array_filter($deudores, static fn ($d) => (int) $d['situacion'] === $s);
            $r[$s] = [count($g), Decimal::sum(array_map(static fn ($d) => Decimal::of((string) $d['saldo']), $g))->toFixed(2)];
        }

        return $r;
    }
}
