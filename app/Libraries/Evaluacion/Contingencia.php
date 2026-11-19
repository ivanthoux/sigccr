<?php

declare(strict_types=1);

namespace App\Libraries\Evaluacion;

use App\Libraries\Validacion\Campos;

/**
 * CU-25 Registrar Evaluación de Contingencia: validación del formulario (paso 6, A2) y RN-21.
 *
 * Se exige al menos un tipo de antecedente verificable con su descripción, la recomendación del Analista
 * (Favorable o Desfavorable) y su justificación. La consulta manual a la Central de Deudores puede registrar las
 * deudas informadas, que se conservan como antecedentes externos de fuente «Manual»; cada fila debe estar completa.
 * La recomendación no aprueba la solicitud: la evaluación queda con resultado «Requiere análisis gerencial» y la
 * solicitud pasa a resolución del Gerente (RN-21, RN-22).
 */
final class Contingencia
{
    public const ANTECEDENTES = [
        'informacion_economica' => 'Información económica del cliente (ingresos declarados y su respaldo)',
        'antecedentes_internos' => 'Antecedentes internos (comportamiento de pago y obligaciones)',
        'documentacion' => 'Documentación respaldatoria del legajo',
        'consulta_manual_bcra' => 'Consulta manual a la Central de Deudores del BCRA',
    ];

    public const RECOMENDACIONES = ['Favorable' => 'Favorable', 'Desfavorable' => 'Desfavorable'];

    public const MINIMO_TEXTO = 20;

    /**
     * @param array<string, mixed> $d datos del formulario: antecedentes (list), detalle_antecedentes, recomendacion,
     *                                justificacion, deudas (list de entidad, situacion, monto, periodo)
     * @return array{errores: array<string, string>, datos: array{antecedentes: list<string>, detalle_antecedentes: string, recomendacion: string, justificacion: string, deudas: list<array{entidad: string, situacion: int, monto: string, periodo: string}>}}
     */
    public static function validar(array $d, string $hoy): array
    {
        $e = [];
        $antecedentes = array_values(array_unique(array_filter(
            Campos::lista($d['antecedentes'] ?? []),
            static fn (string $a): bool => isset(self::ANTECEDENTES[$a]),
        )));
        if ($antecedentes === []) {
            $e['antecedentes'] = 'Indique qué antecedentes verificables utilizó.';
        }
        $detalle = Campos::texto($d['detalle_antecedentes'] ?? '');
        // H-16: largos en unidades de NVARCHAR; H-15: valores con otra forma se toman como vacíos
        if (Campos::largo($detalle) < self::MINIMO_TEXTO) {
            $e['detalle_antecedentes'] = 'Describa los antecedentes verificados (al menos ' . self::MINIMO_TEXTO . ' caracteres).';
        } elseif (Campos::largo($detalle) > 2000) {
            $e['detalle_antecedentes'] = 'La descripción admite hasta 2000 caracteres.';
        }
        $recomendacion = Campos::texto($d['recomendacion'] ?? '');
        if (!isset(self::RECOMENDACIONES[$recomendacion])) {
            $e['recomendacion'] = 'Seleccione la recomendación: favorable o desfavorable.';
        }
        $justificacion = Campos::texto($d['justificacion'] ?? '');
        if (Campos::largo($justificacion) < self::MINIMO_TEXTO) {
            $e['justificacion'] = 'Justifique la recomendación (al menos ' . self::MINIMO_TEXTO . ' caracteres).';
        } elseif (Campos::largo($justificacion) > 2000) {
            $e['justificacion'] = 'La justificación admite hasta 2000 caracteres.';
        }

        $deudas = [];
        $desde = AntecedentesBcra::periodoDesde($hoy);
        $actual = substr(str_replace('-', '', $hoy), 0, 6);
        foreach (is_array($d['deudas'] ?? null) ? $d['deudas'] : [] as $i => $f) {
            $f = is_array($f) ? $f : [];
            $entidad = Campos::texto($f['entidad'] ?? '');
            $situacion = Campos::texto($f['situacion'] ?? '');
            $montoTexto = Campos::texto($f['monto'] ?? '');
            $periodo = str_replace(['/', '-', ' '], '', Campos::texto($f['periodo'] ?? ''));
            if ($entidad === '' && $situacion === '' && $montoTexto === '' && $periodo === '') {
                continue;
            }
            if (!in_array('consulta_manual_bcra', $antecedentes, true)) {
                $e["deudas.{$i}"] = 'Las deudas informadas corresponden a la consulta manual a la Central de Deudores: marque ese antecedente.';
                continue;
            }
            // Período ingresado como MMAAAA (formato local 03/2026) o AAAAMM
            if (preg_match('/^(0[1-9]|1[0-2])(\d{4})$/', $periodo, $m) === 1) {
                $periodo = $m[2] . $m[1];
            }
            $monto = Campos::importe($montoTexto);
            if ($entidad === '' || Campos::largo($entidad) > 150) {
                $e["deudas.{$i}"] = 'Indique la entidad informante (hasta 150 caracteres).';
            } elseif (preg_match('/^[1-6]$/', $situacion) !== 1) {
                $e["deudas.{$i}"] = 'La situación debe ser un número de 1 a 6.';
            } elseif ($monto === null) {
                $e["deudas.{$i}"] = 'Indique el monto adeudado en pesos.';
            } elseif (preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $periodo) !== 1 || $periodo < $desde || $periodo > $actual) {
                $e["deudas.{$i}"] = 'El período debe tener el formato mm/aaaa y estar dentro de los últimos 12 meses.';
            } else {
                $deudas[] = ['entidad' => $entidad, 'situacion' => (int) $situacion, 'monto' => $monto, 'periodo' => $periodo];
            }
        }

        return ['errores' => $e, 'datos' => [
            'antecedentes' => $antecedentes,
            'detalle_antecedentes' => $detalle,
            'recomendacion' => $recomendacion,
            'justificacion' => $justificacion,
            'deudas' => $deudas,
        ]];
    }
}
