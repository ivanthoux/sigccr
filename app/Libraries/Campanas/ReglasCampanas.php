<?php

declare(strict_types=1);

namespace App\Libraries\Campanas;

use App\Exceptions\ValidacionException;
use App\Libraries\Finanzas\Decimal;
use DateTimeImmutable;

/**
 * Reglas de las campañas crediticias (SS-08; CU-52, CU-93 y CU-94; RN-18, RN-40 a RN-43, RN-64, RN-66; D-43), sin
 * acceso a la base: elegibilidad de cada cliente, condiciones de la oferta, elección del cliente al aceptarla y
 * causas de cierre.
 */
final class ReglasCampanas
{
    /** Causas de cierre (CK_campanas_causa_cierre). */
    public const CAUSAS = ['Vigencia' => 'Vencimiento de la vigencia', 'Cupo' => 'Capital asignado alcanzado', 'Disponibilidad' => 'Disponibilidad insuficiente'];

    public const ESTADOS_OFERTA = ['Vigente' => 'Vigente', 'Aceptada' => 'Aceptada', 'Rechazada' => 'Rechazada', 'Vencida' => 'Vencida'];

    /**
     * CU-93 paso 2: el capital asignado es el menor entre el capital máximo configurado y el excedente proyectado.
     */
    public static function capitalAsignado(string $capitalMaximo, string $proyectado): Decimal
    {
        return Decimal::min(Decimal::of($capitalMaximo), Decimal::of($proyectado))->round(2);
    }

    /**
     * CU-93 paso 3 y CU-51: motivo por el que un cliente no es elegible, o null. Las condiciones de la línea (tipo de
     * cliente, edad, categoría) las controla Elegibilidad::motivoNoAdmitida().
     *
     * @param array{categoria_ingresos: string, fecha_alta: string, portal_habilitado: bool, consentimiento: bool,
     *     max_dias_atraso: int, creditos_vigentes: int, solicitud_en_tramite: bool} $c
     * @param array<string, mixed> $p parámetros de campañas vigentes
     */
    public static function motivoNoElegible(array $c, array $p, string $hoy): ?string
    {
        if (!$c['portal_habilitado']) {
            return 'Sin acceso al portal habilitado.';
        }
        if (!$c['consentimiento']) {
            return 'Sin consentimiento vigente para comunicaciones comerciales (RN-64).';
        }
        if (!in_array($c['categoria_ingresos'], (array) $p['categorias_admitidas'], true)) {
            return 'Categoría de ingresos no admitida.';
        }
        $meses = self::mesesEntre(substr($c['fecha_alta'], 0, 10), $hoy);
        if ($meses < (int) $p['antiguedad_minima_meses']) {
            return 'Antigüedad como cliente menor a la mínima.';
        }
        if ($c['max_dias_atraso'] > (int) $p['morosidad_maxima_dias']) {
            return 'Atraso superior a la morosidad máxima admitida.';
        }
        if (!$p['admite_obligaciones_vigentes'] && $c['creditos_vigentes'] > 0) {
            return 'Tiene créditos vigentes y la campaña no los admite.';
        }
        if ($c['solicitud_en_tramite']) {
            return 'Tiene una solicitud no finalizada en la línea (RN-18).';
        }

        return null;
    }

    /**
     * CU-93 paso 4 (CU-87): la oferta es la alternativa viable de mayor importe y, entre las de igual importe, la de
     * menos cuotas. Null si ninguna es viable.
     *
     * @param list<array{importe: Decimal, cuotas: int, viable: bool, primera_cuota: Decimal, tea: Decimal, cftea: Decimal}> $alternativas
     * @return array{importe: Decimal, cuotas: int, primera_cuota: Decimal, tea: Decimal, cftea: Decimal}|null
     */
    public static function condicionesOferta(array $alternativas): ?array
    {
        $mejor = null;
        foreach ($alternativas as $a) {
            if (!$a['viable']) {
                continue;
            }
            if ($mejor === null || $a['importe']->compare($mejor['importe']) > 0 || ($a['importe']->compare($mejor['importe']) === 0 && $a['cuotas'] < $mejor['cuotas'])) {
                $mejor = $a;
            }
        }

        return $mejor === null ? null : ['importe' => $mejor['importe'], 'cuotas' => $mejor['cuotas'], 'primera_cuota' => $mejor['primera_cuota'], 'tea' => $mejor['tea'], 'cftea' => $mejor['cftea']];
    }

    /**
     * CU-52 paso 7: alternativas que el cliente puede elegir al aceptar: las viables hoy con un importe que no supere
     * el máximo ofrecido, de mayor a menor importe.
     *
     * @template T of array{importe: Decimal, cuotas: int, viable: bool}
     * @param list<T> $alternativas
     * @return list<T>
     */
    public static function aceptables(array $alternativas, string $importeMaximo): array
    {
        $r = array_values(array_filter($alternativas, static fn (array $a): bool => $a['viable'] && $a['importe']->compare($importeMaximo) <= 0));
        usort($r, static fn (array $a, array $b): int => [$b['importe']->compare($a['importe']), $a['cuotas']] <=> [0, $b['cuotas']]);

        return $r;
    }

    /**
     * CU-52 paso 7 (A4): la elección del cliente debe ser una de las alternativas aceptables.
     *
     * @template T of array{importe: Decimal, cuotas: int}
     * @param list<T> $aceptables
     * @return T
     */
    public static function eleccion(array $aceptables, string $clave): array
    {
        foreach ($aceptables as $a) {
            if (self::clave($a) === $clave) {
                return $a;
            }
        }
        $opciones = array_map(static fn (array $a): string => '$ ' . number_format((float) $a['importe']->toFixed(2), 2, ',', '.') . ' en ' . $a['cuotas'] . ' cuotas', $aceptables);

        throw new ValidacionException(['alternativa' => $opciones === []
            ? 'Hoy no hay alternativas dentro de su capacidad de pago para esta oferta.'
            : 'Elija una de las alternativas permitidas: ' . implode('; ', $opciones) . '.'], 'RN-42');
    }

    /** @param array{importe: Decimal, cuotas: int} $a */
    public static function clave(array $a): string
    {
        return $a['importe']->toFixed(2) . '|' . $a['cuotas'];
    }

    /**
     * CU-94: causa por la que corresponde cerrar una campaña activa, o null. El cupo se controla al aceptar (CU-52
     * paso 9) y la disponibilidad al proyectar (CU-92 A1); el temporizador controla la vigencia.
     */
    public static function causaCierre(string $vence, DateTimeImmutable $ahora, string $comprometido, string $asignado): ?string
    {
        if (new DateTimeImmutable($vence) <= $ahora) {
            return 'Vigencia';
        }

        return Decimal::of($comprometido)->compare($asignado) >= 0 ? 'Cupo' : null;
    }

    /**
     * CU-94 paso 5 y CU-50 paso 6: resultados de la campaña a partir de sus ofertas.
     *
     * @param list<array{estado: string, solicitud_importe?: string|null, liquidado?: string|null}> $ofertas
     * @return array{emitidas: int, vigentes: int, aceptadas: int, rechazadas: int, vencidas: int, solicitudes: int, capital_solicitado: string, capital_colocado: string, tasa_aceptacion: string}
     */
    public static function resultados(array $ofertas): array
    {
        $cuenta = array_fill_keys(array_keys(self::ESTADOS_OFERTA), 0);
        $solicitado = Decimal::zero();
        $colocado = Decimal::zero();
        $solicitudes = 0;
        foreach ($ofertas as $o) {
            $cuenta[$o['estado']] = ($cuenta[$o['estado']] ?? 0) + 1;
            if (($o['solicitud_importe'] ?? null) !== null) {
                $solicitudes++;
                $solicitado = $solicitado->add((string) $o['solicitud_importe']);
            }
            if (($o['liquidado'] ?? null) !== null) {
                $colocado = $colocado->add((string) $o['liquidado']);
            }
        }
        $emitidas = count($ofertas);

        return [
            'emitidas' => $emitidas, 'vigentes' => $cuenta['Vigente'], 'aceptadas' => $cuenta['Aceptada'], 'rechazadas' => $cuenta['Rechazada'], 'vencidas' => $cuenta['Vencida'],
            'solicitudes' => $solicitudes, 'capital_solicitado' => $solicitado->toFixed(2), 'capital_colocado' => $colocado->toFixed(2),
            'tasa_aceptacion' => $emitidas === 0 ? '0.00' : Decimal::of((string) $cuenta['Aceptada'])->mul(100)->div($emitidas)->round(2)->toFixed(2),
        ];
    }

    /** Meses completos entre dos fechas ISO (antigüedad como cliente). */
    public static function mesesEntre(string $desde, string $hasta): int
    {
        $d = new DateTimeImmutable($desde);
        $h = new DateTimeImmutable($hasta);
        if ($h < $d) {
            return 0;
        }
        $i = $d->diff($h);

        return $i->y * 12 + $i->m;
    }
}
