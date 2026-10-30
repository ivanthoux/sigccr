<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Fondos\FondosService;

/**
 * CU-89 Controlar Vencimientos, pasos 3 a 5 y 8 (iteración 2): simulaciones y alternativas (RN-48), aprobaciones
 * (RN-49), evaluaciones (RN-20, RN-61), inactividad (RN-72) y autorizaciones de acceso (RN-03).
 *
 * Cada solicitud se procesa en su propia transacción y se vuelve a leer bloqueada: si una operación interactiva la
 * cambió después de seleccionarla, se conserva ese estado (A1, RN-24). Las aprobaciones se procesan antes que las
 * evaluaciones: si ambos vencimientos coinciden, prevalece el de la aprobación (RN-49).
 */
class VencimientoService extends BaseService
{
    public const PROCESO = 'vencimientos:controlar';

    public const DIAS_INACTIVIDAD = 30;

    /** Estados que dependen de una evaluación vigente (CU-89 paso 4). */
    private const DEPENDEN_DE_EVALUACION = ['PendienteSeleccionPlan', 'PendienteResolucion', 'AprobadaPendienteLiquidacion'];

    /** Paso 3: simulaciones y alternativas vencidas (operación masiva, sin efectos sobre otros objetos). */
    public function vencerSimulacionesYAlternativas(): int
    {
        $this->db->query("UPDATE dbo.simulaciones SET estado = 'Vencida' WHERE estado = 'Vigente' AND vence <= SYSDATETIMEOFFSET()");
        $n = $this->db->affectedRows();
        $this->db->query("UPDATE dbo.alternativas_financiacion SET estado = 'Vencida' WHERE estado = 'Vigente' AND vence <= SYSDATETIMEOFFSET()");

        return $n + $this->db->affectedRows();
    }

    /** Paso 8: autorizaciones de acceso vencidas (RN-03). */
    public function vencerAutorizaciones(): int
    {
        $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Vencida' WHERE estado = 'Vigente' AND vence <= SYSDATETIMEOFFSET()");

        return $this->db->affectedRows();
    }

    /** @return list<int> paso 5: solicitudes aprobadas cuyo vencimiento se alcanzó sin desembolso */
    public function aprobacionesVencidas(): array
    {
        return $this->ids(
            "SELECT s.id FROM dbo.solicitudes_credito s JOIN dbo.resoluciones r ON r.solicitud_id = s.id AND r.decision = 'Aprobada' AND r.vigente = 1 "
            . "WHERE s.estado = 'AprobadaPendienteLiquidacion' AND r.vence_aprobacion <= SYSDATETIMEOFFSET()",
        );
    }

    /** @return list<int> paso 4: evaluaciones vigentes cuya vigencia se cumplió */
    public function evaluacionesVencidas(): array
    {
        return $this->ids("SELECT id FROM dbo.evaluaciones_crediticias WHERE vigencia = 'Vigente' AND vence <= SYSDATETIMEOFFSET()");
    }

    /** @return list<int> paso 5: solicitudes sin movimientos durante el plazo de inactividad (RN-72) */
    public function solicitudesInactivas(): array
    {
        return $this->ids(
            "SELECT id FROM dbo.solicitudes_credito WHERE estado IN ('PendienteDocumentacion', 'DevueltaRevision') AND ultimo_movimiento <= DATEADD(day, ?, SYSDATETIMEOFFSET())",
            [-self::DIAS_INACTIVIDAD],
        );
    }

    /** Paso 5 (RN-41, RN-49): la aprobación vencida deja la solicitud Vencida y libera la reserva. */
    public function vencerAprobacion(int $solicitudId): bool
    {
        return $this->transaccion(function () use ($solicitudId): bool {
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            $vigente = $s === null ? null : $this->db->query(
                "SELECT id FROM dbo.resoluciones WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1 AND vence_aprobacion <= SYSDATETIMEOFFSET()",
                [$solicitudId],
            )->getRowArray();
            if ($s === null || $s['estado'] !== 'AprobadaPendienteLiquidacion' || $vigente === null) {
                return false;
            }
            $this->db->query('UPDATE dbo.resoluciones SET vigente = 0 WHERE id = ?', [(int) $vigente['id']]);
            $liberadas = (new FondosService($this->db))->liberarDeSolicitud($solicitudId);
            $this->terminar($s, 'Vencida', 'La aprobación venció sin desembolso confirmado (RN-49).', "Solicitud N.º {$s['numero']}: la aprobación venció sin desembolso y se liberó la reserva de fondos.", 'Registrar una nueva solicitud si el cliente sigue interesado.', ['afectaciones_liberadas' => $liberadas]);

            return true;
        });
    }

    /**
     * Paso 4 (RN-20, RN-49, RN-61): la evaluación vencida devuelve a evaluación las solicitudes que dependen de ella,
     * deja sin efecto la aprobación y libera la reserva.
     */
    public function vencerEvaluacion(int $evaluacionId): bool
    {
        return $this->transaccion(function () use ($evaluacionId): bool {
            $solicitudId = (int) ($this->db->table('evaluaciones_crediticias')->select('solicitud_id')->where('id', $evaluacionId)->get()->getRowArray()['solicitud_id'] ?? 0);
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            $e = $this->bloquear('evaluaciones_crediticias', [$evaluacionId])[0] ?? null;
            if ($s === null || $e === null || $e['vigencia'] !== 'Vigente' || $this->db->query('SELECT 1 FROM dbo.evaluaciones_crediticias WHERE id = ? AND vence <= SYSDATETIMEOFFSET()', [$evaluacionId])->getRowArray() === null) {
                return false;
            }
            $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Vencida', version_fila = version_fila + 1 WHERE id = ?", [$evaluacionId]);
            if (!in_array($s['estado'], self::DEPENDEN_DE_EVALUACION, true)) {
                (new AuditoriaService($this->db))->registrar('vencimientos.evaluacion', 'solicitudes_credito', $solicitudId, 'Exitoso', 'Evaluación vencida (RN-20).', ['evaluacion' => $evaluacionId, 'estado' => $s['estado']], self::PROCESO);

                return true;
            }
            $this->db->query("UPDATE dbo.resoluciones SET vigente = 0 WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$solicitudId]);
            $liberadas = (new FondosService($this->db))->liberarDeSolicitud($solicitudId);
            $this->db->query("UPDATE a SET a.estado = 'Vencida' FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones x ON x.id = a.estructuracion_id WHERE x.solicitud_id = ? AND a.estado = 'Vigente'", [$solicitudId]);
            $this->terminar($s, 'PendienteEvaluacion', 'La evaluación crediticia venció (30 días corridos, RN-20): la solicitud vuelve a evaluarse.', "Solicitud N.º {$s['numero']}: venció su evaluación crediticia y volvió a evaluación" . ($s['estado'] === 'AprobadaPendienteLiquidacion' ? '; la aprobación quedó sin efecto y se liberó la reserva.' : '.'), 'Seguir la nueva evaluación automática (CU-86).', ['evaluacion' => $evaluacionId, 'afectaciones_liberadas' => $liberadas]);

            return true;
        });
    }

    /** Paso 5 (RN-72): inactividad de 30 días corridos en Pendiente de documentación o Devuelta para revisión. */
    public function vencerPorInactividad(int $solicitudId): bool
    {
        return $this->transaccion(function () use ($solicitudId): bool {
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            if (
                $s === null || !in_array($s['estado'], ['PendienteDocumentacion', 'DevueltaRevision'], true)
                || $this->db->query('SELECT 1 FROM dbo.solicitudes_credito WHERE id = ? AND ultimo_movimiento <= DATEADD(day, ?, SYSDATETIMEOFFSET())', [$solicitudId, -self::DIAS_INACTIVIDAD])->getRowArray() === null
            ) {
                return false;
            }
            $liberadas = (new FondosService($this->db))->liberarDeSolicitud($solicitudId);
            $this->terminar($s, 'Vencida', 'Sin movimientos durante 30 días corridos (RN-72).', "Solicitud N.º {$s['numero']}: venció por 30 días sin movimientos en " . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . '.', 'Registrar una nueva solicitud si el cliente sigue interesado.', ['afectaciones_liberadas' => $liberadas]);

            return true;
        });
    }

    /**
     * Cambio de estado, alerta al Analista que registró la solicitud y auditoría con el nombre del proceso.
     *
     * @param array<string, mixed> $s
     * @param array<string, mixed> $detalle
     */
    private function terminar(array $s, string $destino, string $motivo, string $causa, string $accion, array $detalle): void
    {
        (new SolicitudService($this->db))->cambiarEstado($s, $destino, null, $motivo, null, [], self::PROCESO);
        $alertas = new AlertaService($this->db);
        $alertas->resolver('solicitudes_credito', (int) $s['id'], 'Proceso ' . self::PROCESO);
        $alertas->generar('Vencimiento', mb_substr($causa, 0, 500), $accion, $s['usuario_id'] === null ? 'AnalistaCredito' : null, $s['usuario_id'] === null ? null : (int) $s['usuario_id'], 'solicitudes_credito', (int) $s['id']);
        (new AuditoriaService($this->db))->registrar('vencimientos.solicitud', 'solicitudes_credito', (int) $s['id'], 'Exitoso', $motivo, $detalle + ['anterior' => $s['estado'], 'nuevo' => $destino], self::PROCESO);
    }

    /**
     * @param list<int|string> $params
     * @return list<int>
     */
    private function ids(string $sql, array $params = []): array
    {
        return array_map(static fn ($f) => (int) $f['id'], $this->db->query($sql, $params)->getResultArray());
    }
}
