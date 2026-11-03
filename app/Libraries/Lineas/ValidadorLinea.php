<?php

declare(strict_types=1);

namespace App\Libraries\Lineas;

use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;

/**
 * Datos de una línea de crédito y de cada versión de sus condiciones (CU-16 pasos 2 a 4, CU-17 paso 5; RN-11, RN-14,
 * RN-68, RN-75, D-09, D-14). Las tasas y el gasto de otorgamiento se ingresan en porcentaje y se guardan en tanto por uno.
 * El importe mínimo y máximo prestable acota simulaciones, solicitudes, alternativas y ofertas de la línea (RN-76).
 */
final class ValidadorLinea
{
    public const CUOTAS_MAXIMAS = 120;
    public const EDAD_MINIMA_LEGAL = 18;

    /**
     * @param array<string, mixed> $d datos del formulario
     * @return array<string, mixed>
     */
    public static function normalizar(array $d): array
    {
        $t = static fn (string $k): string => Campos::texto($d[$k] ?? '');
        $entero = static fn (string $k): ?int => preg_match('/^\d{1,4}$/', $t($k)) === 1 ? (int) $t($k) : null;
        $humana = !empty($d['admite_persona_humana']);
        $juridica = !empty($d['admite_persona_juridica']);
        // Las categorías de un tipo de cliente no admitido se descartan (el formulario las oculta, pero pueden seguir marcadas)
        $posibles = array_merge(
            $humana ? array_keys(RequisitosDocumentales::CATEGORIAS['PersonaHumana']) : [],
            $juridica ? array_keys(RequisitosDocumentales::CATEGORIAS['PersonaJuridica']) : [],
        );
        $categorias = array_values(array_intersect(array_unique(array_map('strval', (array) ($d['categorias'] ?? []))), $posibles));
        $requisitos = [];
        foreach ((array) ($d['requisitos'] ?? []) as $cat => $codigos) {
            if (in_array((string) $cat, $categorias, true)) {
                $requisitos[(string) $cat] = array_values(array_unique(array_map('strval', (array) $codigos)));
            }
        }

        $porcentual = ($d['tipo_gasto_administrativo'] ?? 'Fijo') === 'Porcentual' ? 'Porcentual' : 'Fijo';

        return [
            'codigo' => strtoupper(str_replace(' ', '-', $t('codigo'))),
            'nombre' => $t('nombre'),
            'descripcion' => $t('descripcion'),
            'tna_texto' => $t('tna'),
            'tna' => self::porcentaje($d['tna'] ?? ''),
            'gasto_otorgamiento_texto' => $t('gasto_otorgamiento_pct'),
            'gasto_otorgamiento_pct' => self::porcentaje($d['gasto_otorgamiento_pct'] ?? ''),
            // D-28: el gasto administrativo es un importe fijo o un porcentaje del capital en cada cuota
            'tipo_gasto_administrativo' => $porcentual,
            'gasto_administrativo_texto' => $porcentual === 'Porcentual' ? '0' : $t('gasto_administrativo_cuota'),
            'gasto_administrativo_cuota' => $porcentual === 'Porcentual' ? '0.00' : Campos::importe($d['gasto_administrativo_cuota'] ?? ''),
            'gasto_administrativo_pct_texto' => $porcentual === 'Porcentual' ? $t('gasto_administrativo_pct') : '',
            'gasto_administrativo_pct' => $porcentual === 'Porcentual' ? self::porcentaje($d['gasto_administrativo_pct'] ?? '') : null,
            'monto_minimo_texto' => $t('monto_minimo'),
            'monto_minimo' => Campos::importe($d['monto_minimo'] ?? ''),
            'monto_maximo_texto' => $t('monto_maximo'),
            'monto_maximo' => Campos::importe($d['monto_maximo'] ?? ''),
            'cuotas_minimas' => $entero('cuotas_minimas'),
            'cuotas_maximas' => $entero('cuotas_maximas'),
            'edad_minima' => $humana ? $entero('edad_minima') : null,
            'edad_maxima' => $humana ? $entero('edad_maxima') : null,
            'edad_minima_texto' => $humana ? $t('edad_minima') : '',
            'edad_maxima_texto' => $humana ? $t('edad_maxima') : '',
            'antiguedad_minima_meses' => $juridica ? $entero('antiguedad_minima_meses') : null,
            'antiguedad_texto' => $juridica ? $t('antiguedad_minima_meses') : '',
            'admite_persona_humana' => $humana,
            'admite_persona_juridica' => $juridica,
            'categorias' => $categorias,
            'requisitos' => $requisitos,
            'vigencia_inicio_texto' => $t('vigencia_inicio'),
            'vigencia_inicio' => Campos::fecha($d['vigencia_inicio'] ?? ''),
            'vigencia_fin_texto' => $t('vigencia_fin'),
            'vigencia_fin' => Campos::fecha($d['vigencia_fin'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $d datos normalizados
     * @param list<string> $codigosDocumentacion códigos de tipos_documentacion activos
     * @param string $hoy fecha de negocio aaaa-mm-dd
     * @param bool $alta en el alta se valida la identificación; en una modificación es fija
     * @return array<string, string> campo => mensaje
     */
    public static function validar(array $d, array $codigosDocumentacion, string $hoy, bool $alta = true): array
    {
        $r = [];
        if ($alta) {
            $r['codigo'] = [
                Campos::obligatorio($d['codigo'], 'la identificación de la línea'),
                $d['codigo'] !== '' && preg_match('/^[A-Z0-9][A-Z0-9_\-]{1,19}$/', $d['codigo']) !== 1 ? 'La identificación admite de 2 a 20 letras, números, guiones o guiones bajos.' : null,
            ];
        }
        $r['nombre'] = [Campos::obligatorio($d['nombre'], 'el nombre'), Campos::largoMaximo($d['nombre'], 100, 'el nombre')];
        $r['descripcion'] = [Campos::largoMaximo($d['descripcion'], 500, 'la descripción')];

        $r['tna'] = [
            Campos::obligatorio($d['tna_texto'], 'la TNA'),
            $d['tna_texto'] !== '' && $d['tna'] === null ? 'Ingrese la TNA en porcentaje, por ejemplo 65 o 65,5.' : null,
            $d['tna'] !== null && Decimal::of($d['tna'])->compare(5) > 0 ? 'La TNA no puede superar el 500 %.' : null,
        ];
        $r['gasto_otorgamiento_pct'] = [
            Campos::obligatorio($d['gasto_otorgamiento_texto'], 'el gasto de otorgamiento (0 si no tiene)'),
            $d['gasto_otorgamiento_texto'] !== '' && $d['gasto_otorgamiento_pct'] === null ? 'Ingrese el gasto de otorgamiento en porcentaje del capital.' : null,
            $d['gasto_otorgamiento_pct'] !== null && Decimal::of($d['gasto_otorgamiento_pct'])->compare(1) >= 0 ? 'El gasto de otorgamiento debe ser menor que el 100 % del capital.' : null,
        ];
        $r['gasto_administrativo_cuota'] = $d['tipo_gasto_administrativo'] === 'Porcentual' ? [] : [
            Campos::obligatorio($d['gasto_administrativo_texto'], 'el gasto administrativo por cuota (0 si no tiene)'),
            $d['gasto_administrativo_texto'] !== '' && $d['gasto_administrativo_cuota'] === null ? 'Ingrese un importe válido, por ejemplo 500,00.' : null,
        ];
        $r['gasto_administrativo_pct'] = $d['tipo_gasto_administrativo'] !== 'Porcentual' ? [] : [
            Campos::obligatorio($d['gasto_administrativo_pct_texto'], 'el porcentaje del capital que se cobra en cada cuota'),
            $d['gasto_administrativo_pct_texto'] !== '' && $d['gasto_administrativo_pct'] === null ? 'Ingrese el porcentaje del capital, por ejemplo 0,5.' : null,
            $d['gasto_administrativo_pct'] !== null && !Decimal::of($d['gasto_administrativo_pct'])->isPositive() ? 'El porcentaje debe ser mayor que cero; si la línea no tiene gasto, elija «Importe fijo por cuota» con 0.' : null,
            $d['gasto_administrativo_pct'] !== null && Decimal::of($d['gasto_administrativo_pct'])->compare('0.1') > 0 ? 'El gasto administrativo no puede superar el 10 % del capital por cuota.' : null,
        ];

        // D-09 / RN-76: importe mínimo y máximo prestable de la línea
        $r['monto_minimo'] = [
            Campos::obligatorio($d['monto_minimo_texto'], 'el importe mínimo prestable'),
            $d['monto_minimo_texto'] !== '' && $d['monto_minimo'] === null ? 'Ingrese un importe válido, por ejemplo 300.000,00.' : null,
            $d['monto_minimo'] !== null && !Decimal::of($d['monto_minimo'])->isPositive() ? 'El importe mínimo debe ser mayor que cero.' : null,
        ];
        $r['monto_maximo'] = [
            Campos::obligatorio($d['monto_maximo_texto'], 'el importe máximo prestable'),
            $d['monto_maximo_texto'] !== '' && $d['monto_maximo'] === null ? 'Ingrese un importe válido, por ejemplo 1.500.000,00.' : null,
            $d['monto_minimo'] !== null && $d['monto_maximo'] !== null && Decimal::of($d['monto_minimo'])->compare($d['monto_maximo']) >= 0
                ? 'El importe máximo debe ser mayor que el mínimo (RN-14, D-23).' : null,
        ];
        $r['cuotas_minimas'] = [$d['cuotas_minimas'] === null || $d['cuotas_minimas'] < 1 ? 'Ingrese la cantidad mínima de cuotas (al menos 1).' : null];
        $r['cuotas_maximas'] = [
            $d['cuotas_maximas'] === null || $d['cuotas_maximas'] < 1 ? 'Ingrese la cantidad máxima de cuotas.' : null,
            $d['cuotas_maximas'] !== null && $d['cuotas_maximas'] > self::CUOTAS_MAXIMAS ? 'La cantidad máxima de cuotas no puede superar ' . self::CUOTAS_MAXIMAS . '.' : null,
            $d['cuotas_minimas'] !== null && $d['cuotas_maximas'] !== null && $d['cuotas_minimas'] >= $d['cuotas_maximas'] ? 'La cantidad máxima de cuotas debe ser mayor que la mínima (RN-14, D-23).' : null,
        ];

        $r['tipos_cliente'] = [!$d['admite_persona_humana'] && !$d['admite_persona_juridica'] ? 'Seleccione al menos un tipo de cliente admitido.' : null];
        if ($d['admite_persona_humana']) {
            $r['edad_minima'] = [
                $d['edad_minima_texto'] !== '' && $d['edad_minima'] === null ? 'Ingrese la edad mínima en años.' : null,
                $d['edad_minima'] !== null && ($d['edad_minima'] < self::EDAD_MINIMA_LEGAL || $d['edad_minima'] > 100) ? 'La edad mínima debe estar entre 18 y 100 años.' : null,
            ];
            $r['edad_maxima'] = [
                $d['edad_maxima_texto'] !== '' && $d['edad_maxima'] === null ? 'Ingrese la edad máxima en años.' : null,
                $d['edad_maxima'] !== null && ($d['edad_maxima'] < self::EDAD_MINIMA_LEGAL || $d['edad_maxima'] > 100) ? 'La edad máxima debe estar entre 18 y 100 años.' : null,
                $d['edad_minima'] !== null && $d['edad_maxima'] !== null && $d['edad_minima'] >= $d['edad_maxima'] ? 'La edad máxima debe ser mayor que la mínima (RN-14, D-23).' : null,
            ];
        }
        if ($d['admite_persona_juridica']) {
            $r['antiguedad_minima_meses'] = [$d['antiguedad_texto'] !== '' && $d['antiguedad_minima_meses'] === null ? 'Ingrese la antigüedad mínima en meses.' : null];
        }

        $admitidas = [];
        if ($d['admite_persona_humana']) {
            $admitidas += RequisitosDocumentales::CATEGORIAS['PersonaHumana'];
        }
        if ($d['admite_persona_juridica']) {
            $admitidas += RequisitosDocumentales::CATEGORIAS['PersonaJuridica'];
        }
        $invalidas = array_diff($d['categorias'], array_keys($admitidas));
        $r['categorias'] = [
            $d['categorias'] === [] ? 'Seleccione al menos una categoría de ingresos admitida.' : null,
            $invalidas !== [] ? 'Hay categorías de ingresos que no corresponden a los tipos de cliente admitidos.' : null,
        ];
        // CU-16 A3: cada categoría admitida debe tener documentación exigida
        $sinDocumentos = [];
        foreach ($d['categorias'] as $cat) {
            $docs = array_intersect($d['requisitos'][$cat] ?? [], $codigosDocumentacion);
            if ($docs === []) {
                $sinDocumentos[] = $admitidas[$cat] ?? $cat;
            }
        }
        // D-14: el documento de identidad es el mínimo de toda línea (en una empresa, el del representante)
        $sinIdentidad = [];
        foreach ($d['categorias'] as $cat) {
            if (!in_array('DOC_IDENTIDAD', $d['requisitos'][$cat] ?? [], true)) {
                $sinIdentidad[] = $admitidas[$cat] ?? $cat;
            }
        }
        $r['requisitos'] = [
            $sinDocumentos !== [] ? 'Indique la documentación exigida para: ' . implode(', ', $sinDocumentos) . '.' : null,
            $sinIdentidad !== [] ? 'El documento de identidad es obligatorio en todas las categorías; falta en: ' . implode(', ', $sinIdentidad) . '.' : null,
        ];

        $r['vigencia_inicio'] = [
            Campos::obligatorio($d['vigencia_inicio_texto'], 'la fecha de inicio de vigencia'),
            $d['vigencia_inicio_texto'] !== '' && $d['vigencia_inicio'] === null ? 'Ingrese una fecha válida (dd/mm/aaaa).' : null,
        ];
        $r['vigencia_fin'] = [
            Campos::obligatorio($d['vigencia_fin_texto'], 'la fecha de fin de vigencia'),
            $d['vigencia_fin_texto'] !== '' && $d['vigencia_fin'] === null ? 'Ingrese una fecha válida (dd/mm/aaaa).' : null,
            $d['vigencia_inicio'] !== null && $d['vigencia_fin'] !== null && $d['vigencia_fin'] < $d['vigencia_inicio'] ? 'La fecha de fin no puede ser anterior a la de inicio (RN-14).' : null,
            $d['vigencia_fin'] !== null && $d['vigencia_fin'] < $hoy ? 'La fecha de fin de vigencia ya pasó.' : null,
        ];

        $errores = [];
        foreach ($r as $campo => $resultados) {
            foreach ($resultados as $m) {
                if ($m !== null) {
                    $errores[$campo] = $m;
                    break;
                }
            }
        }

        return $errores;
    }

    /** Porcentaje ("65", "65,5", "2.25 %") a tanto por uno con hasta 12 decimales; null si no es válido. */
    public static function porcentaje(mixed $valor): ?string
    {
        $t = str_replace([' ', '%'], '', Campos::texto($valor));
        $t = str_replace(',', '.', $t);
        if (preg_match('/^\d{1,3}(\.\d{1,4})?$/', $t) !== 1) {
            return null;
        }

        return Decimal::of($t)->div(100)->round(6)->toFixed(6);
    }

    /** Tanto por uno a porcentaje para mostrar ("0.650000" => "65"). */
    public static function aPorcentaje(string $tantoPorUno): string
    {
        $p = Decimal::of($tantoPorUno)->mul(100)->round(4)->toFixed(4);

        return rtrim(rtrim($p, '0'), '.');
    }

    /**
     * Estado efectivo de la línea: una línea activa o suspendida cuyo período de vigencia terminó está Vencida (4.2.2).
     */
    public static function estadoEfectivo(string $estado, string $vigenciaFin, string $hoy): string
    {
        return $estado !== 'Vencida' && $vigenciaFin < $hoy ? 'Vencida' : $estado;
    }
}
