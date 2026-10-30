<?php

declare(strict_types=1);

namespace App\Libraries\Lineas;

use DateTimeImmutable;

/**
 * Líneas admitidas para un cliente (CU-21 paso 3, CU-22, CU-99; RN-13, RN-75).
 *
 * Una línea admite al cliente cuando su estado efectivo es Activa, la fecha de negocio está dentro del período de
 * vigencia de la versión, la versión admite su tipo de cliente y su categoría de ingresos, y el cliente cumple la edad
 * (persona humana) o la antigüedad (persona jurídica) exigidas. Devuelve el motivo del primer requisito no cumplido,
 * que se muestra al Analista y se conserva en la simulación comparativa.
 */
final class Elegibilidad
{
    /**
     * @param array{estado: string, vigencia_inicio: string, vigencia_fin: string, admite_persona_humana: bool|int|string,
     *     admite_persona_juridica: bool|int|string, edad_minima: int|string|null, edad_maxima: int|string|null,
     *     antiguedad_minima_meses: int|string|null, categorias: list<string>} $version condiciones vigentes y estado de la línea
     * @param array{tipo_cliente: string, categoria_ingresos: string, estado: string, fecha_nacimiento: ?string,
     *     fecha_constitucion: ?string} $cliente
     * @param string $hoy fecha de negocio aaaa-mm-dd
     *
     * @return string|null motivo por el que no se admite, o null si la línea lo admite
     */
    public static function motivoNoAdmitida(array $version, array $cliente, string $hoy): ?string
    {
        if ($cliente['estado'] !== 'Activo') {
            return 'El cliente está desactivado.';
        }
        $estado = ValidadorLinea::estadoEfectivo($version['estado'], substr($version['vigencia_fin'], 0, 10), $hoy);
        if ($estado === 'Suspendida') {
            return 'La línea está suspendida (RN-16).';
        }
        if ($estado !== 'Activa' || $hoy > substr($version['vigencia_fin'], 0, 10)) {
            return 'La línea superó su período de vigencia (RN-13).';
        }
        if ($hoy < substr($version['vigencia_inicio'], 0, 10)) {
            return 'La línea rige desde el ' . self::fecha($version['vigencia_inicio']) . ' (RN-13).';
        }
        $humana = $cliente['tipo_cliente'] === 'PersonaHumana';
        if ($humana && !(bool) (int) $version['admite_persona_humana']) {
            return 'La línea no admite personas humanas.';
        }
        if (!$humana && !(bool) (int) $version['admite_persona_juridica']) {
            return 'La línea no admite personas jurídicas.';
        }
        if (!in_array($cliente['categoria_ingresos'], $version['categorias'], true)) {
            return 'La línea no admite la categoría de ingresos del cliente.';
        }
        if ($humana) {
            $edad = $cliente['fecha_nacimiento'] === null ? null : self::aniosCumplidos($cliente['fecha_nacimiento'], $hoy);
            if ($version['edad_minima'] !== null && ($edad === null || $edad < (int) $version['edad_minima'])) {
                return 'El cliente no alcanza la edad mínima de la línea (' . (int) $version['edad_minima'] . ' años).';
            }
            if ($version['edad_maxima'] !== null && $edad !== null && $edad > (int) $version['edad_maxima']) {
                return 'El cliente supera la edad máxima de la línea (' . (int) $version['edad_maxima'] . ' años).';
            }
        } elseif ($version['antiguedad_minima_meses'] !== null) {
            $meses = $cliente['fecha_constitucion'] === null ? 0 : self::mesesCumplidos($cliente['fecha_constitucion'], $hoy);
            if ($meses < (int) $version['antiguedad_minima_meses']) {
                return 'La empresa no alcanza la antigüedad mínima de la línea (' . (int) $version['antiguedad_minima_meses'] . ' meses).';
            }
        }

        return null;
    }

    public static function aniosCumplidos(string $desde, string $hoy): int
    {
        return (new DateTimeImmutable(substr($desde, 0, 10)))->diff(new DateTimeImmutable($hoy))->y;
    }

    public static function mesesCumplidos(string $desde, string $hoy): int
    {
        $d = (new DateTimeImmutable(substr($desde, 0, 10)))->diff(new DateTimeImmutable($hoy));

        return $d->invert === 1 ? 0 : $d->y * 12 + $d->m;
    }

    private static function fecha(string $iso): string
    {
        return (new DateTimeImmutable(substr($iso, 0, 10)))->format('d/m/Y');
    }
}
