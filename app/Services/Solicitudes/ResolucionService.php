<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\ResolucionGerencial;
use App\Libraries\Solicitudes\Simulador;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use DateTimeImmutable;

/**
 * CU-26 Resolver Solicitud de Crédito Convencional y CU-28 Cancelar Solicitud de Crédito. RN-22, RN-24, RN-27, RN-41,
 * RN-49, RN-61, RN-71, RN-76.
 *
 * Cada decisión queda en resoluciones con su fundamento y su responsable; la aprobación conserva el plan aprobado (la
 * alternativa), su vencimiento de 72 horas hábiles y constituye la reserva de fondos por el neto a desembolsar.
 */
class ResolucionService extends BaseService
{
    /**
     * CU-26 pasos 4 a 9 (A1 a A6, A8).
     *
     * @param array<string, mixed> $datos formulario (ResolucionGerencial::validar)
     * @return array{estado: string, mensaje: string}
     */
    public function resolver(int $solicitudId, int $versionLeida, array $datos, int $usuarioId): array
    {
        helper('sigccr');

        return $this->transaccion(function () use ($solicitudId, $versionLeida, $datos, $usuarioId): array {
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            if ($s === null) {
                throw new ReglaNegocioException('La solicitud no existe.');
            }
            if ($s['estado'] !== 'PendienteResolucion') {
                throw new ReglaNegocioException('La solicitud ya no está pendiente de resolución (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . ').', 'RN-24');
            }
            if ((int) $s['version_fila'] !== $versionLeida) {
                throw new ReglaNegocioException('La solicitud cambió mientras la resolvía: revise la información actualizada.', 'RN-24');
            }
            $estructuracion = new EstructuracionService($this->db);
            $confirmada = $this->alternativaConfirmada($solicitudId);
            $v = ResolucionGerencial::validar($datos, $confirmada !== null);
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-22');
            }
            $d = $v['datos'];
            $solicitudes = new SolicitudService($this->db);
            $fondos = new FondosService($this->db);
            $auditoria = new AuditoriaService($this->db);
            $base = ['solicitud_id' => $solicitudId, 'fecha' => $this->ahoraUtc(), 'automatica' => 0, 'usuario_id' => $usuarioId, 'fundamento' => $d['fundamento']];

            if ($d['decision'] === 'Rechazada') {
                // A1: el rechazo libera las afectaciones y conserva el motivo
                $this->db->table('resoluciones')->insert($base + ['decision' => 'Rechazada', 'vigente' => 1]);
                $fondos->liberarDeSolicitud($solicitudId);
                $solicitudes->cambiarEstado($s, 'Rechazada', $usuarioId, $d['fundamento'], null, ['tipo_rechazo' => 'Crediticio']);
                $this->cerrarAlertas($solicitudId);
                $auditoria->registrar('solicitudes.resolver', 'solicitudes_credito', $solicitudId, 'Exitoso', $d['fundamento'], ['decision' => 'Rechazada']);

                return ['estado' => 'Rechazada', 'mensaje' => 'Solicitud rechazada. Se conserva el motivo y se liberaron sus afectaciones de fondos.'];
            }
            if ($d['decision'] === 'DevueltaRevision') {
                // A2: la devolución indica la etapa que se retoma (4.2.3, CU-84)
                $this->db->table('resoluciones')->insert($base + ['decision' => 'DevueltaRevision', 'etapa_retorno' => $d['etapa'], 'vigente' => 1]);
                $fondos->liberarDeSolicitud($solicitudId);
                $solicitudes->cambiarEstado($s, 'DevueltaRevision', $usuarioId, $d['fundamento'], null);
                $this->alertarAnalista($s, "Solicitud N.º {$s['numero']} devuelta para revisión por el Gerente: {$d['fundamento']}", 'Registrar las correcciones (CU-84).');
                $auditoria->registrar('solicitudes.resolver', 'solicitudes_credito', $solicitudId, 'Exitoso', $d['fundamento'], ['decision' => 'DevueltaRevision', 'etapa' => $d['etapa']]);

                return ['estado' => 'DevueltaRevision', 'mensaje' => 'Solicitud devuelta para revisión: el Analista debe retomar ' . mb_strtolower(ResolucionGerencial::ETAPAS[$d['etapa']]) . '.'];
            }

            // Paso 5: validaciones de la aprobación (A3, A5)
            if ($estructuracion->evaluacionVigente($solicitudId) === null) {
                $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Vencida', version_fila = version_fila + 1 WHERE solicitud_id = ? AND vigencia = 'Vigente'", [$solicitudId]);
                $solicitudes->cambiarEstado($s, 'PendienteEvaluacion', $usuarioId, 'La evaluación crediticia perdió vigencia antes de la aprobación (RN-20, RN-61).', null);
                $auditoria->registrar('solicitudes.resolver', 'solicitudes_credito', $solicitudId, 'Rechazado', 'Evaluación vencida o invalidada (CU-26 A3).');

                return ['estado' => 'PendienteEvaluacion', 'mensaje' => 'No se aprobó: la evaluación crediticia ya no está vigente. La solicitud volvió a Pendiente de evaluación (RN-20).'];
            }
            if (!$this->documentacionVerificada($solicitudId)) {
                $solicitudes->cambiarEstado($s, 'PendienteVerificacion', $usuarioId, 'La documentación no se encuentra verificada (CU-26 A5).', null);
                $auditoria->registrar('solicitudes.resolver', 'solicitudes_credito', $solicitudId, 'Rechazado', 'Documentación no verificada (CU-26 A5).');

                return ['estado' => 'PendienteVerificacion', 'mensaje' => 'No se aprobó: la documentación no está verificada. La solicitud volvió a verificación del legajo.'];
            }
            $version = $estructuracion->version((int) $s['version_condiciones_id']);
            if ($version['estado_linea'] !== 'Activa') {
                throw new ReglaNegocioException('La línea de crédito está ' . mb_strtolower((string) $version['estado_linea']) . ': no se pueden registrar nuevas aprobaciones (RN-16). Rechace o devuelva la solicitud.', 'RN-16');
            }
            $a = $estructuracion->alternativaParaAprobar($s, (string) $d['plan'], $d['plan'] === 'confirmada' ? (int) $confirmada['id'] : $d['alternativa_id'], $d['importe'], $d['cuotas']);
            $iva = (string) (new ParametroService($this->db))->vigente('Estructuracion')['valores']['alicuota_iva'];
            $neto = Simulador::calcular($version, (string) $a['importe'], (int) $a['cantidad_cuotas'], $iva, new DateTimeImmutable($this->hoyNegocio()))['neto']->toFixed(2);

            // Paso 8 / A4: la reserva de fondos; si no alcanzan, el Gerente rechaza o devuelve
            $fondos->liberarDeSolicitud($solicitudId);
            if ($fondos->reservarParaSolicitud($solicitudId, $neto) === null) {
                throw new ReglaNegocioException('Fondos insuficientes para el desembolso de ' . formato_importe($neto) . ' (disponible libre: ' . formato_importe($fondos->disponibilidad()['libre']) . '). Rechace la solicitud o devuélvala para revisión (RN-71).', 'RN-71');
            }
            $vence = $estructuracion->vencimientoAprobacion();
            $this->db->table('resoluciones')->insert($base + [
                'decision' => 'Aprobada', 'alternativa_id' => (int) $a['id'], 'importe_aprobado' => (string) $a['importe'],
                'cuotas_aprobadas' => (int) $a['cantidad_cuotas'], 'vence_aprobacion' => $vence, 'vigente' => 1,
            ]);
            $solicitudes->cambiarEstado($s, 'AprobadaPendienteLiquidacion', $usuarioId, 'Aprobación del Gerente: ' . $d['fundamento'], null);
            (new ContratoService($this->db))->generar($solicitudId);
            $this->cerrarAlertas($solicitudId);
            $this->alertarAnalista($s, "Solicitud N.º {$s['numero']} aprobada por el Gerente: " . formato_importe((string) $a['importe']) . " en {$a['cantidad_cuotas']} cuotas.", 'Coordinar la liquidación antes del ' . formato_fecha_hora($vence) . ' (CU-27).');
            $auditoria->registrar('solicitudes.resolver', 'solicitudes_credito', $solicitudId, 'Exitoso', $d['fundamento'], [
                'decision' => 'Aprobada', 'plan' => $d['plan'], 'alternativa' => (int) $a['id'], 'importe' => (string) $a['importe'],
                'cuotas' => (int) $a['cantidad_cuotas'], 'vence_aprobacion' => $vence, 'reserva' => $neto,
            ]);

            return ['estado' => 'AprobadaPendienteLiquidacion', 'mensaje' => 'Solicitud aprobada: ' . formato_importe((string) $a['importe']) . " en {$a['cantidad_cuotas']} cuotas, con una reserva de " . formato_importe($neto) . '. La aprobación vence el ' . formato_fecha_hora($vence) . ($a['estado'] !== 'Confirmada' ? '. Al liquidar se registra la conformidad del cliente con el plan (RN-22).' : '.')];
        });
    }

    /**
     * CU-28 pasos 3 a 7 (A1 a A3).
     */
    public function cancelar(int $solicitudId, int $versionLeida, string $motivo, bool $confirmaReserva, int $usuarioId): void
    {
        $texto = Campos::texto($motivo);
        $error = Campos::motivo($texto);
        if ($error !== null) {
            throw new ValidacionException(['motivo' => $error], 'RN-27');
        }
        $this->transaccion(function () use ($solicitudId, $versionLeida, $texto, $confirmaReserva, $usuarioId): void {
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            if ($s === null) {
                throw new ReglaNegocioException('La solicitud no existe.');
            }
            if (EstadosSolicitud::esFinal((string) $s['estado'])) {
                throw new ReglaNegocioException('La solicitud ya finalizó (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . '): no se puede cancelar.', 'RN-27');
            }
            if ($s['estado'] === 'AprobadaPendienteLiquidacion' && !$confirmaReserva) {
                throw new ValidacionException(['confirma_reserva' => 'La solicitud está aprobada: confirme que la cancelación deja sin efecto la aprobación y libera la reserva de fondos (RN-41).'], 'RN-41');
            }
            if ((int) $s['version_fila'] !== $versionLeida) {
                throw new ReglaNegocioException('La solicitud cambió mientras la cancelaba: revise la información actualizada.', 'RN-24');
            }
            $liberadas = (new FondosService($this->db))->liberarDeSolicitud($solicitudId);
            $this->db->query("UPDATE dbo.resoluciones SET vigente = 0 WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$solicitudId]);
            $this->db->query("UPDATE a SET a.estado = 'Descartada', a.motivo_descarte = 'Solicitud cancelada.' FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE e.solicitud_id = ? AND a.estado = 'Vigente'", [$solicitudId]);
            (new SolicitudService($this->db))->cambiarEstado($s, 'Cancelada', $usuarioId, $texto, null, ['motivo_cancelacion' => mb_substr($texto, 0, 500)]);
            $this->cerrarAlertas($solicitudId);
            (new AuditoriaService($this->db))->registrar('solicitudes.cancelar', 'solicitudes_credito', $solicitudId, 'Exitoso', $texto, ['estado_anterior' => $s['estado'], 'afectaciones_liberadas' => $liberadas]);
        });
    }

    /** CU-26 paso 3: antecedentes para resolver (alternativa confirmada, vigentes, capacidad y fondos). */
    public function antecedentes(int $solicitudId): array
    {
        $estructuracion = new EstructuracionService($this->db);
        $s = $this->db->table('solicitudes_credito')->where('id', $solicitudId)->get()->getRowArray();

        return [
            'estructuracion' => $estructuracion->deSolicitud($solicitudId),
            'confirmada' => $this->alternativaConfirmada($solicitudId),
            'evaluacion' => $estructuracion->evaluacionVigente($solicitudId),
            'capacidad' => $s === null ? null : $estructuracion->capacidadActual($s)->toFixed(2),
            'fondos' => (new FondosService($this->db))->disponibilidad(),
            'documentacion_verificada' => $this->documentacionVerificada($solicitudId),
        ];
    }

    /** @return ?array<string, mixed> alternativa Confirmada de la última estructuración */
    private function alternativaConfirmada(int $solicitudId): ?array
    {
        return $this->db->query(
            "SELECT TOP (1) a.* FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE e.solicitud_id = ? AND a.estado = 'Confirmada' ORDER BY e.fecha DESC",
            [$solicitudId],
        )->getRowArray();
    }

    /** La última revisión documental resuelta está Verificada (RN-19). */
    private function documentacionVerificada(int $solicitudId): bool
    {
        $r = $this->db->query(
            "SELECT TOP (1) estado FROM dbo.revisiones_documentales WHERE solicitud_id = ? AND estado <> 'PendienteRevision' ORDER BY fecha DESC, id DESC",
            [$solicitudId],
        )->getRowArray();

        return ($r['estado'] ?? null) === 'Verificada';
    }

    private function cerrarAlertas(int $solicitudId): void
    {
        (new AlertaService($this->db))->resolver('solicitudes_credito', $solicitudId, (string) (session('cuenta')['nombre'] ?? 'Usuario'));
    }

    /** @param array<string, mixed> $s */
    private function alertarAnalista(array $s, string $causa, string $accion): void
    {
        $this->cerrarAlertas((int) $s['id']);
        (new AlertaService($this->db))->generar('Operativa', mb_substr($causa, 0, 500), $accion, $s['usuario_id'] === null ? 'AnalistaCredito' : null, $s['usuario_id'] === null ? null : (int) $s['usuario_id'], 'solicitudes_credito', (int) $s['id']);
    }
}
