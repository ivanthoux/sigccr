<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Validacion\Campos;

/**
 * CU-26 Resolver Solicitud de Crédito Convencional y CU-28 Cancelar Solicitud: validación de las decisiones
 * (RN-22, RN-27). La aprobación identifica el plan: la alternativa confirmada por el cliente, una alternativa vigente
 * o un importe y una cantidad de cuotas definidos por el Gerente (A8), cuya conformidad se registra al liquidar.
 */
final class ResolucionGerencial
{
    public const DECISIONES = [
        'Aprobada' => 'Aprobar',
        'Rechazada' => 'Rechazar',
        'DevueltaRevision' => 'Devolver para revisión',
    ];

    /** Etapas que el Gerente puede indicar en una devolución (4.2.3). */
    public const ETAPAS = [
        'Verificacion' => 'Verificación del legajo',
        'Evaluacion' => 'Evaluación crediticia',
        'SeleccionPlan' => 'Selección del plan',
    ];

    public const PLANES = ['confirmada', 'alternativa', 'definida'];

    public const MINIMO_FUNDAMENTO = 10;

    /**
     * @param array<string, mixed> $d decision, fundamento, etapa_retorno, plan, alternativa_id, importe, cantidad_cuotas
     * @return array{errores: array<string, string>, datos: array{decision: string, fundamento: string, etapa: ?string, plan: ?string, alternativa_id: ?int, importe: ?string, cuotas: ?int}}
     */
    public static function validar(array $d, bool $hayConfirmada): array
    {
        $e = [];
        // H-15: cada valor recortado; uno enviado como lista llega vacío. H-16: largos en unidades de NVARCHAR
        $decision = Campos::texto($d['decision'] ?? '');
        if (!isset(self::DECISIONES[$decision])) {
            $e['decision'] = 'Seleccione la decisión: aprobar, rechazar o devolver para revisión.';
        }
        $fundamento = Campos::texto($d['fundamento'] ?? '');
        if (Campos::largo($fundamento) < self::MINIMO_FUNDAMENTO) {
            $e['fundamento'] = $decision === 'Rechazada' ? 'Indique el motivo del rechazo (al menos 10 caracteres).' : 'Indique el fundamento de la decisión (al menos 10 caracteres).';
        } elseif (Campos::largo($fundamento) > 2000) {
            $e['fundamento'] = 'El fundamento admite hasta 2000 caracteres.';
        }
        $etapa = null;
        if ($decision === 'DevueltaRevision') {
            $etapa = Campos::texto($d['etapa_retorno'] ?? '');
            if (!isset(self::ETAPAS[$etapa])) {
                $e['etapa_retorno'] = 'Indique la etapa que debe retomarse.';
            }
        }
        $plan = null;
        $alternativa = null;
        $importe = null;
        $cuotas = null;
        if ($decision === 'Aprobada') {
            $plan = $hayConfirmada ? 'confirmada' : Campos::texto($d['plan'] ?? '');
            if (!in_array($plan, self::PLANES, true) || ($plan === 'confirmada' && !$hayConfirmada)) {
                $e['plan'] = 'Seleccione el plan que se aprueba: una alternativa vigente o un importe y cuotas definidos.';
            } elseif ($plan === 'alternativa') {
                $alternativa = ($n = Campos::identificador($d['alternativa_id'] ?? '')) > 0 ? $n : null;
                if ($alternativa === null) {
                    $e['alternativa_id'] = 'Seleccione la alternativa que se aprueba.';
                }
            } elseif ($plan === 'definida') {
                $importe = Campos::importe($d['importe'] ?? '');
                if ($importe === null) {
                    $e['importe'] = 'Ingrese el importe que se aprueba, por ejemplo 500.000,00.';
                }
                $texto = Campos::texto($d['cantidad_cuotas'] ?? '');
                $cuotas = ctype_digit($texto) ? (int) $texto : null;
                if ($cuotas === null || $cuotas < 1) {
                    $e['cantidad_cuotas'] = 'Ingrese la cantidad de cuotas.';
                }
            }
        }

        return ['errores' => $e, 'datos' => [
            'decision' => $decision, 'fundamento' => $fundamento, 'etapa' => $etapa, 'plan' => $plan,
            'alternativa_id' => $alternativa, 'importe' => $importe, 'cuotas' => $cuotas,
        ]];
    }
}
