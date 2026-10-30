<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

/**
 * Ciclo de vida de la solicitud de crédito (4.2.3, 3.5.3). Los servicios solo cambian el estado por una transición
 * de esta tabla; los estados finales no se reabren y la cancelación se admite desde cualquier estado en trámite
 * (RN-27).
 */
final class EstadosSolicitud
{
    public const FINALES = ['Liquidada', 'Rechazada', 'Cancelada', 'Vencida'];

    /** Estado de origen => estados de destino admitidos (sin la cancelación, que vale para todo estado en trámite). */
    public const TRANSICIONES = [
        'PendienteVerificacion' => ['PendienteDocumentacion', 'PendienteEvaluacion', 'Rechazada'],
        'PendienteDocumentacion' => ['PendienteVerificacion', 'Vencida'],
        'PendienteEvaluacion' => ['EnEvaluacion'],
        // EnEvaluacion → PendienteEvaluacion: error interno o ejecución interrumpida; se reintenta en la próxima (CU-86 A7)
        'EnEvaluacion' => ['PendienteSeleccionPlan', 'PendienteResolucion', 'Rechazada', 'PendienteContingencia', 'PendienteVerificacion', 'PendienteEvaluacion'],
        'PendienteContingencia' => ['PendienteResolucion', 'PendienteVerificacion'],
        'PendienteSeleccionPlan' => ['AprobadaPendienteLiquidacion', 'PendienteResolucion', 'PendienteEvaluacion', 'PendienteVerificacion'],
        'PendienteResolucion' => ['AprobadaPendienteLiquidacion', 'Rechazada', 'DevueltaRevision', 'PendienteEvaluacion', 'PendienteVerificacion'],
        'DevueltaRevision' => ['PendienteVerificacion', 'PendienteEvaluacion', 'PendienteSeleccionPlan', 'PendienteResolucion', 'Vencida'],
        'AprobadaPendienteLiquidacion' => ['Liquidada', 'Vencida', 'PendienteEvaluacion', 'PendienteVerificacion'],
    ];

    /** Etapa indicada por el Gerente en una devolución (CU-26) => estado que retoma la solicitud (CU-84 paso 7). */
    public const ETAPAS_RETORNO = [
        'Verificacion' => 'PendienteVerificacion',
        'Evaluacion' => 'PendienteEvaluacion',
        'SeleccionPlan' => 'PendienteSeleccionPlan',
        'Resolucion' => 'PendienteResolucion',
    ];

    /** Estados en los que la solicitud admite correcciones (CU-84). */
    public const CORREGIBLES = ['PendienteDocumentacion', 'DevueltaRevision'];

    public const ETIQUETAS = [
        'PendienteVerificacion' => 'Pendiente de verificación',
        'PendienteDocumentacion' => 'Pendiente de documentación',
        'PendienteEvaluacion' => 'Pendiente de evaluación',
        'EnEvaluacion' => 'En evaluación',
        'PendienteContingencia' => 'Pendiente de evaluación de contingencia',
        'PendienteSeleccionPlan' => 'Pendiente de selección de plan',
        'PendienteResolucion' => 'Pendiente de resolución',
        'DevueltaRevision' => 'Devuelta para revisión',
        'AprobadaPendienteLiquidacion' => 'Aprobada, pendiente de liquidación',
        'Liquidada' => 'Liquidada',
        'Rechazada' => 'Rechazada',
        'Cancelada' => 'Cancelada',
        'Vencida' => 'Vencida',
    ];

    public static function esFinal(string $estado): bool
    {
        return in_array($estado, self::FINALES, true);
    }

    public static function puede(string $origen, string $destino): bool
    {
        if (self::esFinal($origen)) {
            return false;
        }

        return $destino === 'Cancelada' || in_array($destino, self::TRANSICIONES[$origen] ?? [], true);
    }

    /**
     * CU-84 paso 7: estado que corresponde después de una corrección, o null si la solicitud conserva su estado
     * (A1: faltantes no atendidos en una solicitud observada).
     */
    public static function trasCorreccion(string $estado, bool $huboDocumentos, bool $faltantesAtendidos, ?string $etapaRetorno): ?string
    {
        if ($estado === 'PendienteDocumentacion') {
            return $huboDocumentos && $faltantesAtendidos ? 'PendienteVerificacion' : null;
        }
        if ($estado === 'DevueltaRevision') {
            if ($huboDocumentos) {
                return 'PendienteVerificacion';
            }

            return self::ETAPAS_RETORNO[$etapaRetorno ?? 'Verificacion'] ?? 'PendienteVerificacion';
        }

        return null;
    }

    public static function etiqueta(string $estado): string
    {
        return self::ETIQUETAS[$estado] ?? $estado;
    }
}
