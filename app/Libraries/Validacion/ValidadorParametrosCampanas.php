<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Finanzas\Decimal;

/**
 * CU-51 Configurar Parámetros de Campañas Crediticias (RN-39, RN-40, RN-43, RN-64, RN-66; D-43).
 *
 * Forma guardada en versiones_parametros (tipo Campanas):
 *   activación: umbral_disponibilidad '5000000.00', periodo_proyeccion_dias 30, factor_cobrabilidad '0.90',
 *               reserva_minima '1000000.00', linea_id (línea habilitada para campañas o null);
 *   límites: capital_maximo '3000000.00', cantidad_maxima_ofertas 200;
 *   elegibilidad: categorias_admitidas [códigos], antiguedad_minima_meses 0, morosidad_maxima_dias 0,
 *                 admite_obligaciones_vigentes true;
 *   comunicación: canales ['Correo'], mensaje (texto opcional que se agrega a la oferta);
 *   vigencia: duracion_dias 15, cierre_por_disponibilidad true.
 * El acceso al portal habilitado y el consentimiento vigente para comunicaciones comerciales se exigen siempre y no
 * se configuran (RN-64).
 */
final class ValidadorParametrosCampanas
{
    /** Canales disponibles: el servicio de comunicaciones solo envía correo electrónico (RNF-09). */
    public const CANALES = ['Correo' => 'Correo electrónico'];

    public const SUGERIDOS = [
        'umbral_disponibilidad' => '5000000.00',
        'periodo_proyeccion_dias' => 30,
        'factor_cobrabilidad' => '0.90',
        'reserva_minima' => '1000000.00',
        'linea_id' => null,
        'capital_maximo' => '3000000.00',
        'cantidad_maxima_ofertas' => 200,
        'categorias_admitidas' => ['RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto'],
        'antiguedad_minima_meses' => 0,
        'morosidad_maxima_dias' => 0,
        'admite_obligaciones_vigentes' => true,
        'canales' => ['Correo'],
        'mensaje' => '',
        'duracion_dias' => 15,
        'cierre_por_disponibilidad' => true,
    ];

    /** @return array<string, string> categorías de ingresos de personas humanas y jurídicas */
    public static function categorias(): array
    {
        $c = [];
        foreach (RequisitosDocumentales::CATEGORIAS as $tipo) {
            $c += $tipo;
        }

        return $c;
    }

    /**
     * Valores vigentes con los sugeridos para las claves que la versión guardada no tiene (la carga inicial solo
     * define los de la proyección).
     *
     * @param array<string, mixed> $v
     * @return array<string, mixed>
     */
    public static function completar(array $v): array
    {
        return $v + self::SUGERIDOS;
    }

    /**
     * @param array<string, mixed> $d formulario: importes con coma decimal, porcentajes como números (90 = 90 %)
     * @return array<string, mixed>
     */
    public static function normalizar(array $d): array
    {
        $entero = static fn (mixed $v): ?int => ctype_digit($t = trim((string) ($v ?? ''))) && strlen($t) <= 5 ? (int) $t : null;
        $categorias = array_values(array_intersect(array_keys(self::categorias()), Campos::lista($d['categorias_admitidas'] ?? [])));
        $canales = array_values(array_intersect(array_keys(self::CANALES), Campos::lista($d['canales'] ?? [])));
        $factor = Campos::importe($d['factor_cobrabilidad'] ?? '');

        return [
            'umbral_disponibilidad' => Campos::importe($d['umbral_disponibilidad'] ?? ''),
            'periodo_proyeccion_dias' => $entero($d['periodo_proyeccion_dias'] ?? ''),
            'factor_cobrabilidad' => $factor === null ? null : Decimal::of($factor)->div(100)->round(4)->toFixed(4),
            'reserva_minima' => Campos::importe($d['reserva_minima'] ?? ''),
            'linea_id' => $entero($d['linea_id'] ?? ''),
            'capital_maximo' => Campos::importe($d['capital_maximo'] ?? ''),
            'cantidad_maxima_ofertas' => $entero($d['cantidad_maxima_ofertas'] ?? ''),
            'categorias_admitidas' => $categorias,
            'antiguedad_minima_meses' => $entero($d['antiguedad_minima_meses'] ?? ''),
            'morosidad_maxima_dias' => $entero($d['morosidad_maxima_dias'] ?? ''),
            'admite_obligaciones_vigentes' => !empty($d['admite_obligaciones_vigentes']),
            'canales' => $canales,
            'mensaje' => Campos::texto($d['mensaje'] ?? ''),
            'duracion_dias' => $entero($d['duracion_dias'] ?? ''),
            'cierre_por_disponibilidad' => !empty($d['cierre_por_disponibilidad']),
        ];
    }

    /**
     * CU-51 paso 4 (A1). $lineasActivas son los identificadores de las líneas activas que admiten campañas.
     *
     * @param array<string, mixed> $p
     * @param list<int> $lineasActivas
     * @return array<string, string>
     */
    public static function validar(array $p, array $lineasActivas): array
    {
        $e = [];
        $noNegativo = static fn (?string $v): bool => $v !== null && !Decimal::of($v)->isNegative();
        if (!$noNegativo($p['umbral_disponibilidad'])) {
            $e['umbral_disponibilidad'] = 'Ingrese el umbral de disponibilidad: un importe mayor o igual a cero.';
        }
        if (!is_int($p['periodo_proyeccion_dias']) || $p['periodo_proyeccion_dias'] < 1 || $p['periodo_proyeccion_dias'] > 180) {
            $e['periodo_proyeccion_dias'] = 'El período de proyección debe ser de 1 a 180 días.';
        }
        if ($p['factor_cobrabilidad'] === null || Decimal::of($p['factor_cobrabilidad'])->compare('1') > 0) {
            $e['factor_cobrabilidad'] = 'El factor de cobrabilidad debe estar entre 0 % y 100 %.';
        }
        if (!$noNegativo($p['reserva_minima'])) {
            $e['reserva_minima'] = 'Ingrese la reserva mínima de fondos: un importe mayor o igual a cero.';
        }
        if ($p['linea_id'] === null || !in_array($p['linea_id'], $lineasActivas, true)) {
            $e['linea_id'] = 'Seleccione una línea de crédito activa para las campañas.';
        }
        if ($p['capital_maximo'] === null || !Decimal::of($p['capital_maximo'])->isPositive()) {
            $e['capital_maximo'] = 'El capital máximo de la campaña debe ser mayor que cero.';
        }
        if (!is_int($p['cantidad_maxima_ofertas']) || $p['cantidad_maxima_ofertas'] < 1 || $p['cantidad_maxima_ofertas'] > 10000) {
            $e['cantidad_maxima_ofertas'] = 'La cantidad máxima de ofertas debe estar entre 1 y 10.000.';
        }
        if ($p['categorias_admitidas'] === []) {
            $e['categorias_admitidas'] = 'Seleccione al menos una categoría de ingresos admitida.';
        }
        if (!is_int($p['antiguedad_minima_meses']) || $p['antiguedad_minima_meses'] > 240) {
            $e['antiguedad_minima_meses'] = 'La antigüedad mínima debe ser de 0 a 240 meses.';
        }
        if (!is_int($p['morosidad_maxima_dias']) || $p['morosidad_maxima_dias'] > 365) {
            $e['morosidad_maxima_dias'] = 'La morosidad máxima admitida debe ser de 0 a 365 días de atraso.';
        }
        if ($p['canales'] === []) {
            $e['canales'] = 'Seleccione al menos un canal de comunicación.';
        }
        if (mb_strlen((string) $p['mensaje']) > 500) {
            $e['mensaje'] = 'El mensaje admite hasta 500 caracteres.';
        }
        if (!is_int($p['duracion_dias']) || $p['duracion_dias'] < 1 || $p['duracion_dias'] > 90) {
            $e['duracion_dias'] = 'La duración de la campaña debe ser de 1 a 90 días.';
        }

        return $e;
    }

    /**
     * Valores del formulario a partir de los guardados.
     *
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    public static function aFormulario(array $p): array
    {
        $p = self::completar($p);
        $importe = static fn (mixed $v): string => str_replace('.', ',', Decimal::of((string) $v)->toFixed(2));

        return [
            'umbral_disponibilidad' => $importe($p['umbral_disponibilidad']),
            'periodo_proyeccion_dias' => (string) $p['periodo_proyeccion_dias'],
            'factor_cobrabilidad' => str_replace('.', ',', rtrim(rtrim(Decimal::of((string) $p['factor_cobrabilidad'])->mul(100)->round(2)->toFixed(2), '0'), '.')),
            'reserva_minima' => $importe($p['reserva_minima']),
            'linea_id' => $p['linea_id'] === null ? '' : (string) $p['linea_id'],
            'capital_maximo' => $importe($p['capital_maximo']),
            'cantidad_maxima_ofertas' => (string) $p['cantidad_maxima_ofertas'],
            'categorias_admitidas' => (array) $p['categorias_admitidas'],
            'antiguedad_minima_meses' => (string) $p['antiguedad_minima_meses'],
            'morosidad_maxima_dias' => (string) $p['morosidad_maxima_dias'],
            'admite_obligaciones_vigentes' => (bool) $p['admite_obligaciones_vigentes'],
            'canales' => (array) $p['canales'],
            'mensaje' => (string) $p['mensaje'],
            'duracion_dias' => (string) $p['duracion_dias'],
            'cierre_por_disponibilidad' => (bool) $p['cierre_por_disponibilidad'],
        ];
    }
}
