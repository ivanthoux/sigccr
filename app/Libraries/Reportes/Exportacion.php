<?php

declare(strict_types=1);

namespace App\Libraries\Reportes;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Convierte un reporte (estructura común de ReporteService, D-44) en las hojas del archivo Excel: «Resumen» con los
 * criterios y los indicadores, y una hoja por sección. Los importes, cantidades y porcentajes se exportan como
 * números; las fechas, como texto dd/mm/aaaa en la zona de negocio.
 */
final class Exportacion
{
    /**
     * @param array<string, mixed> $r reporte
     * @return array<string, list<list<string|int|float|null>>>
     */
    public static function hojas(array $r, string $zona): array
    {
        $resumen = [
            ['Reporte', (string) $r['titulo']],
            ['Caso de uso', (string) ($r['cu'] ?? '')],
            ['Período', (string) $r['periodo']['etiqueta']],
            ['Generado', self::valor((string) $r['referencia'], 'fechahora', $zona)],
        ];
        foreach ($r['filtros'] ?? [] as $f) {
            $resumen[] = ['Criterio', (string) $f];
        }
        if (($r['aviso'] ?? null) !== null) {
            $resumen[] = ['Aviso', (string) $r['aviso']];
        }
        $resumen[] = [null, null];
        $resumen[] = ['Indicador', 'Valor'];
        foreach ($r['kpis'] as [$etiqueta, $valor, $tipo]) {
            $resumen[] = [$etiqueta . ($tipo === 'porcentaje' ? ' (%)' : ''), self::valor($valor, $tipo, $zona)];
        }
        $hojas = ['Resumen' => $resumen];
        foreach ($r['secciones'] as $s) {
            $filas = [array_map(static fn ($c) => $c[0] . ($c[1] === 'porcentaje' ? ' (%)' : ''), $s['columnas'])];
            foreach ($s['filas'] as $fila) {
                $filas[] = array_map(static fn ($v, $c) => self::valor($v, $c[1], $zona), array_values($fila), $s['columnas']);
            }
            if (($s['nota'] ?? null) !== null) {
                $filas[] = [];
                $filas[] = [(string) $s['nota']];
            }
            $hojas[(string) $s['titulo']] = $filas;
        }

        return $hojas;
    }

    /** Valor de una celda según el tipo de la columna. */
    public static function valor(mixed $v, string $tipo, string $zona): string|int|float|null
    {
        if ($v === null || $v === '') {
            return null;
        }

        return match ($tipo) {
            'importe', 'porcentaje', 'decimal' => (float) $v,
            'entero' => (int) $v,
            'fecha' => self::fecha((string) $v, $zona, false),
            'fechahora' => self::fecha((string) $v, $zona, true),
            default => (string) $v,
        };
    }

    private static function fecha(string $v, string $zona, bool $hora): string
    {
        if (strlen($v) <= 10) {
            return (new DateTimeImmutable($v))->format('d/m/Y');
        }
        $d = (new DateTimeImmutable($v))->setTimezone(new DateTimeZone($zona));

        return $d->format($hora ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
