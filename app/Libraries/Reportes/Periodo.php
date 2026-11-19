<?php

declare(strict_types=1);

namespace App\Libraries\Reportes;

use App\Exceptions\ValidacionException;
use App\Libraries\Validacion\Campos;
use DateTimeImmutable;

/**
 * Período de referencia del tablero y los reportes (CU-55 paso 2, CU-65 a CU-71 paso 2; RN-47): día, semana, mes,
 * trimestre o rango personalizado, en fechas de negocio. Valida la coherencia (A2) y define la granularidad de la
 * evolución del período.
 */
final class Periodo
{
    public const TIPOS = ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'trimestre' => 'Trimestre', 'rango' => 'Rango personalizado'];

    /** Rango máximo admitido, para acotar las consultas. */
    public const MAXIMO_DIAS = 732;

    /**
     * Última fecha admitida (H-31): el período se consulta hasta el día siguiente a su fin y la semana o la proyección
     * pueden pasar al año siguiente; con fechas del año 9999 el cálculo salía del rango de fechas y respondía error 500.
     */
    public const FECHA_MAXIMA = '2099-12-31';

    /**
     * @param array<string, mixed> $g tipo, fecha (día, semana, mes o trimestre que la contiene), desde y hasta (rango)
     * @param bool $futuro true para la proyección de fondos: el período no puede empezar antes de hoy (CU-70 A2)
     * @return array{tipo: string, desde: string, hasta: string, etiqueta: string, granularidad: string}
     * @throws ValidacionException
     */
    public static function desde(array $g, string $hoy, bool $futuro = false, int $diasPorDefecto = 30): array
    {
        $tipo = isset(self::TIPOS[$g['tipo'] ?? '']) ? (string) $g['tipo'] : ($futuro ? 'rango' : 'mes');
        $base = trim(Campos::crudo($g['fecha'] ?? '')) === '' ? $hoy : self::fecha($g['fecha']);
        if ($base === null) {
            throw new ValidacionException(['fecha' => 'Ingrese una fecha válida.']);
        }
        $d = new DateTimeImmutable($base);
        [$desde, $hasta] = match ($tipo) {
            'dia' => [$base, $base],
            'semana' => [$d->modify('monday this week')->format('Y-m-d'), $d->modify('sunday this week')->format('Y-m-d')],
            'mes' => [$d->format('Y-m-01'), $d->format('Y-m-t')],
            'trimestre' => self::trimestre($d),
            default => self::rango($g, $hoy, $futuro, $diasPorDefecto),
        };
        if ($futuro && $desde < $hoy) {
            throw new ValidacionException(['desde' => 'La proyección no puede empezar antes de hoy: elija un período futuro (A2).']);
        }

        return ['tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'etiqueta' => self::etiqueta($desde, $hasta), 'granularidad' => self::granularidad($desde, $hasta)];
    }

    /** @return array{0: string, 1: string} */
    private static function trimestre(DateTimeImmutable $d): array
    {
        $mes = intdiv((int) $d->format('n') - 1, 3) * 3 + 1;
        $inicio = new DateTimeImmutable($d->format('Y') . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '-01');

        return [$inicio->format('Y-m-d'), $inicio->modify('+2 months')->format('Y-m-t')];
    }

    /**
     * @param array<string, mixed> $g
     * @return array{0: string, 1: string}
     */
    private static function rango(array $g, string $hoy, bool $futuro, int $diasPorDefecto): array
    {
        $desdeTexto = trim(Campos::crudo($g['desde'] ?? ''));
        $hastaTexto = trim(Campos::crudo($g['hasta'] ?? ''));
        $desde = $desdeTexto === '' ? ($futuro ? $hoy : (new DateTimeImmutable($hoy))->format('Y-m-01')) : self::fecha($desdeTexto);
        $hasta = $hastaTexto === '' ? ($futuro ? (new DateTimeImmutable($hoy))->modify("+{$diasPorDefecto} days")->format('Y-m-d') : $hoy) : self::fecha($hastaTexto);
        $errores = [];
        if ($desde === null) {
            $errores['desde'] = 'Ingrese una fecha válida.';
        }
        if ($hasta === null) {
            $errores['hasta'] = 'Ingrese una fecha válida.';
        }
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $errores['hasta'] = 'La fecha final no puede ser anterior a la inicial (A2).';
        }
        if ($errores === [] && (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days > self::MAXIMO_DIAS) {
            $errores['hasta'] = 'El rango no puede superar los dos años.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        return [(string) $desde, (string) $hasta];
    }

    /** Fecha válida entre 1900 y FECHA_MAXIMA (H-31), o null. */
    private static function fecha(mixed $valor): ?string
    {
        $f = Campos::fecha($valor);

        return $f !== null && $f <= self::FECHA_MAXIMA ? $f : null;
    }

    public static function etiqueta(string $desde, string $hasta): string
    {
        $f = static fn (string $x): string => (new DateTimeImmutable($x))->format('d/m/Y');

        return $desde === $hasta ? $f($desde) : 'del ' . $f($desde) . ' al ' . $f($hasta);
    }

    /** Evolución por día hasta 31 días, por semana hasta 6 meses y por mes en los demás. */
    public static function granularidad(string $desde, string $hasta): string
    {
        $dias = (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days + 1;

        return $dias <= 31 ? 'dia' : ($dias <= 186 ? 'semana' : 'mes');
    }

    /**
     * Intervalos de la evolución del período, en orden, con su etiqueta.
     *
     * @return array<string, string> clave => etiqueta
     */
    public static function intervalos(string $desde, string $hasta, string $granularidad): array
    {
        $r = [];
        $d = new DateTimeImmutable($desde);
        $fin = new DateTimeImmutable($hasta);
        while ($d <= $fin) {
            $clave = self::clave($d->format('Y-m-d'), $granularidad);
            $r[$clave] ??= match ($granularidad) {
                'dia' => $d->format('d/m'),
                'semana' => 'Semana del ' . (new DateTimeImmutable($clave))->format('d/m'),
                default => $d->format('m/Y'),
            };
            $d = $d->modify('+1 day');
        }

        return $r;
    }

    /** Intervalo al que pertenece una fecha de negocio (aaaa-mm-dd). */
    public static function clave(string $fecha, string $granularidad): string
    {
        $d = new DateTimeImmutable(substr($fecha, 0, 10));

        return match ($granularidad) {
            'dia' => $d->format('Y-m-d'),
            'semana' => $d->modify('monday this week')->format('Y-m-d'),
            default => $d->format('Y-m'),
        };
    }
}
