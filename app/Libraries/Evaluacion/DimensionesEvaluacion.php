<?php

declare(strict_types=1);

namespace App\Libraries\Evaluacion;

use DateTimeImmutable;

/**
 * Dimensiones de la evaluación crediticia (3.5.5, 4.2.4): procesamiento, resultado, modalidad y vigencia son
 * independientes. Esta clase concentra sus etiquetas y las consecuencias del resultado sobre la solicitud (CU-86
 * pasos 9 y 10, A1 a A3).
 */
final class DimensionesEvaluacion
{
    public const PROCESAMIENTO = ['Pendiente' => 'Pendiente', 'EnProceso' => 'En proceso', 'Finalizada' => 'Finalizada', 'NoCompletada' => 'No completada'];
    public const RESULTADO = ['Favorable' => 'Favorable', 'Desfavorable' => 'Desfavorable', 'RequiereAnalisisGerencial' => 'Requiere análisis gerencial'];
    public const MODALIDAD = ['Automatica' => 'Automática', 'Contingencia' => 'Contingencia'];
    public const VIGENCIA = ['Vigente' => 'Vigente', 'Vencida' => 'Vencida', 'Invalidada' => 'Invalidada', 'Sustituida' => 'Sustituida'];
    public const INDICADORES = [
        'bcra' => 'Situación en el sistema financiero (BCRA)',
        'comportamiento' => 'Comportamiento de pago interno',
        'endeudamiento' => 'Endeudamiento',
        'antiguedad' => 'Antigüedad como cliente',
    ];

    /** Estado al que pasa la solicitud según el resultado de una evaluación automática finalizada (CU-86 paso 10, A1, A2). */
    public static function estadoSolicitud(string $resultado): string
    {
        return match ($resultado) {
            'Favorable' => 'PendienteSeleccionPlan',
            'RequiereAnalisisGerencial' => 'PendienteResolucion',
            default => 'Rechazada',
        };
    }

    /** Rol que recibe la alerta del resultado (CU-86 paso 10 y A1; A3 para la no completada). */
    public static function rolAlerta(string $estadoSolicitud): ?string
    {
        return match ($estadoSolicitud) {
            'PendienteSeleccionPlan', 'PendienteContingencia' => 'AnalistaCredito',
            'PendienteResolucion' => 'Gerente',
            default => null,
        };
    }

    /**
     * Vigencia que se muestra: una evaluación Vigente cuyo vencimiento ya pasó se informa como Vencida aunque el
     * control de vencimientos (CU-89) todavía no la haya actualizado (RN-20).
     */
    public static function vigenciaEfectiva(?string $vigencia, ?string $vence, DateTimeImmutable $ahora): ?string
    {
        if ($vigencia === 'Vigente' && $vence !== null && new DateTimeImmutable($vence) <= $ahora) {
            return 'Vencida';
        }

        return $vigencia;
    }

    public static function etiqueta(array $catalogo, ?string $valor): string
    {
        return $valor === null ? '—' : ($catalogo[$valor] ?? $valor);
    }
}
