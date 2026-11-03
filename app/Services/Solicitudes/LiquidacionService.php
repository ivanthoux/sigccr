<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Solicitudes\Desembolso;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\Simulador;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use DateTimeImmutable;

/**
 * CU-27 Liquidar Crédito Aprobado (RN-22, RN-25, RN-26, RN-41, RN-49, RN-50 a RN-52, RN-68, RN-71).
 *
 * En una sola transacción: registra el crédito con su plan definitivo de cuotas (primer vencimiento a los 30 días de
 * la liquidación), la liquidación con el neto desembolsado (siempre por transferencia a un CBU del cliente, D-27), consume la
 * reserva y deja la solicitud Liquidada. La clave única de liquidaciones.solicitud_id impide un segundo desembolso.
 */
class LiquidacionService extends BaseService
{
    /**
     * CU-27 pasos 1 y 2: solicitudes aprobadas pendientes de liquidación.
     *
     * @return list<array<string, mixed>>
     */
    public function pendientes(string $texto = ''): array
    {
        $b = $this->db->table('solicitudes_credito s')
            ->select('s.id, s.numero, c.denominacion, c.cuit_cuil, l.nombre AS linea, r.importe_aprobado, r.cuotas_aprobadas, r.vence_aprobacion')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('versiones_condiciones v', 'v.id = s.version_condiciones_id')
            ->join('lineas_credito l', 'l.id = v.linea_credito_id')
            ->join('resoluciones r', "r.solicitud_id = s.id AND r.decision = 'Aprobada' AND r.vigente = 1")
            ->where('s.estado', 'AprobadaPendienteLiquidacion');
        foreach (preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
            ctype_digit($p) ? $b->groupStart()->where('s.numero', (int) $p)->orLike('c.cuit_cuil', $p)->groupEnd() : $b->like('c.denominacion', $p);
        }

        return $b->orderBy('r.vence_aprobacion')->get(200)->getResultArray();
    }

    /**
     * CU-27 paso 3: datos de la liquidación (cliente, aprobación, plan, neto y reserva).
     *
     * @return array<string, mixed>
     */
    public function datos(int $solicitudId): array
    {
        $s = $this->db->query(
            'SELECT s.*, c.denominacion, c.tipo_cliente, c.cuit_cuil, ph.tipo_documento, ph.nro_documento, pj.representante_nombre, pj.representante_documento, '
            . 'l.nombre AS linea, v.numero AS version, v.tna, v.gasto_otorgamiento_pct, v.gasto_administrativo_cuota, '
            . 'r.id AS resolucion_id, r.importe_aprobado, r.cuotas_aprobadas, r.vence_aprobacion, r.automatica, r.alternativa_id, a.estado AS estado_alternativa '
            . 'FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id '
            . 'LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id '
            . 'JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . "LEFT JOIN dbo.resoluciones r ON r.solicitud_id = s.id AND r.decision = 'Aprobada' AND r.vigente = 1 "
            . 'LEFT JOIN dbo.alternativas_financiacion a ON a.id = r.alternativa_id WHERE s.id = ?',
            [$solicitudId],
        )->getRowArray();
        if ($s === null) {
            throw new ReglaNegocioException('La solicitud no existe.');
        }
        $s['liquidacion'] = $this->db->table('liquidaciones')->where('solicitud_id', $solicitudId)->get()->getRowArray();
        if ($s['importe_aprobado'] !== null) {
            $iva = (string) (new ParametroService($this->db))->vigente('Estructuracion')['valores']['alicuota_iva'];
            $s['calculo'] = Simulador::calcular($s, Decimal::of((string) $s['importe_aprobado'])->toFixed(2), (int) $s['cuotas_aprobadas'], $iva, new DateTimeImmutable($this->hoyNegocio())) + ['alicuota_iva' => $iva];
        }
        $s['requiere_conformidad'] = $s['alternativa_id'] !== null && $s['estado_alternativa'] !== 'Confirmada';
        $s['reserva'] = $this->db->query("SELECT TOP (1) importe FROM dbo.afectaciones_fondos WHERE referencia_tipo = 'Solicitud' AND referencia_id = ? AND estado = 'Vigente'", [$solicitudId])->getRowArray()['importe'] ?? null;

        return $s;
    }

    /**
     * CU-27 pasos 4 a 9 (A1 a A7).
     *
     * @param array<string, mixed> $datos formulario (Desembolso::validar)
     * @return array{estado: string, mensaje: string, credito_id: ?int}
     */
    public function liquidar(int $solicitudId, int $versionLeida, array $datos, int $usuarioId): array
    {
        helper('sigccr');

        return $this->transaccion(function () use ($solicitudId, $versionLeida, $datos, $usuarioId): array {
            $fila = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            if ($fila === null) {
                throw new ReglaNegocioException('La solicitud no existe.');
            }
            // A5: una sola liquidación por solicitud (RN-26)
            $anterior = $this->db->table('liquidaciones')->select('id, credito_id, fecha')->where('solicitud_id', $solicitudId)->get()->getRowArray();
            if ($anterior !== null) {
                throw new ReglaNegocioException('La solicitud ya fue liquidada el ' . formato_fecha_hora($anterior['fecha']) . ' (liquidación N.º ' . $anterior['id'] . '): no se repite el desembolso (RN-26).', 'RN-26');
            }
            if ($fila['estado'] !== 'AprobadaPendienteLiquidacion') {
                throw new ReglaNegocioException('La solicitud no está aprobada pendiente de liquidación (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $fila['estado'])) . ').', 'RN-25');
            }
            if ((int) $fila['version_fila'] !== $versionLeida) {
                throw new ReglaNegocioException('La solicitud cambió mientras la liquidaba: revise la información actualizada.', 'RN-24');
            }
            $s = $this->datos($solicitudId);
            $solicitudes = new SolicitudService($this->db);
            $fondos = new FondosService($this->db);
            $auditoria = new AuditoriaService($this->db);

            // A1: aprobación vencida (RN-49)
            if ($s['vence_aprobacion'] === null || new DateTimeImmutable((string) $s['vence_aprobacion']) <= new DateTimeImmutable('now')) {
                $fondos->liberarDeSolicitud($solicitudId);
                $this->db->query("UPDATE dbo.resoluciones SET vigente = 0 WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$solicitudId]);
                $solicitudes->cambiarEstado($fila, 'Vencida', $usuarioId, 'La aprobación venció sin desembolso confirmado (RN-49).', null);
                $auditoria->registrar('creditos.liquidar', 'solicitudes_credito', $solicitudId, 'Rechazado', 'Aprobación vencida (CU-27 A1).');

                return ['estado' => 'Vencida', 'mensaje' => 'La aprobación venció sin desembolso: la solicitud quedó Vencida y se liberó la reserva de fondos (RN-49).', 'credito_id' => null];
            }
            // A2: evaluación vencida o invalidada
            if ((new EstructuracionService($this->db))->evaluacionVigente($solicitudId) === null) {
                $fondos->liberarDeSolicitud($solicitudId);
                $this->db->query("UPDATE dbo.resoluciones SET vigente = 0 WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$solicitudId]);
                $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Vencida', version_fila = version_fila + 1 WHERE solicitud_id = ? AND vigencia = 'Vigente'", [$solicitudId]);
                $solicitudes->cambiarEstado($fila, 'PendienteEvaluacion', $usuarioId, 'La evaluación crediticia perdió vigencia antes del desembolso: la aprobación quedó sin efecto (RN-49).', null);
                $auditoria->registrar('creditos.liquidar', 'solicitudes_credito', $solicitudId, 'Rechazado', 'Evaluación vencida (CU-27 A2).');

                return ['estado' => 'PendienteEvaluacion', 'mensaje' => 'La evaluación crediticia ya no está vigente: la aprobación quedó sin efecto, se liberó la reserva y la solicitud volvió a evaluación (RN-49).', 'credito_id' => null];
            }
            $neto = $s['calculo']['neto']->toFixed(2);
            $v = Desembolso::validar($datos, $s, $neto, $this->hoyNegocio(), (bool) $s['requiere_conformidad']);
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-25');
            }
            // A3: reserva constituida (los fondos ya están reservados por el neto)
            $reserva = $fondos->afectacionVigente($solicitudId);
            if ($reserva === null || Decimal::of((string) $reserva['importe'])->compare($neto) < 0) {
                throw new ReglaNegocioException('La solicitud no tiene una reserva de fondos suficiente para el desembolso: no se puede liquidar. Comuníquelo al Gerente (RN-41).', 'RN-41');
            }
            // Pasos 7 y 8: crédito, plan definitivo, liquidación, egreso y reserva consumida
            $ahora = $this->ahoraUtc();
            $hoy = $this->hoyNegocio();
            $morosidad = (new ParametroService($this->db))->vigente('Morosidad');
            $iva = Decimal::of((string) $s['calculo']['alicuota_iva']);
            $capital = Decimal::of((string) $s['importe_aprobado']);
            $this->db->table('creditos')->insert([
                'cliente_id' => (int) $s['cliente_id'], 'version_condiciones_id' => (int) $s['version_condiciones_id'], 'version_morosidad_id' => $morosidad['id'],
                'origen' => 'Liquidacion', 'capital' => $capital->toFixed(2), 'tna' => (string) $s['tna'], 'cantidad_cuotas' => (int) $s['cuotas_aprobadas'],
                'fecha_liquidacion' => $ahora, 'tolerancia_dias' => (int) ($morosidad['valores']['tolerancia_dias'] ?? 0), 'estado' => 'Activo',
            ]);
            $creditoId = (int) $this->db->insertID();
            $plan = AmortizacionFrancesa::plan($capital, Decimal::of((string) $s['tna']), (int) $s['cuotas_aprobadas'], new DateTimeImmutable($hoy), Decimal::of((string) $s['gasto_administrativo_cuota']), $iva);
            foreach ($plan->cuotas as $q) {
                $this->db->table('cuotas')->insert([
                    'credito_id' => $creditoId, 'numero' => $q->numero, 'vencimiento' => $q->vencimiento->format('Y-m-d'),
                    'capital' => $q->capital->toFixed(2), 'interes' => $q->interes->toFixed(2), 'gastos' => $q->gastos->toFixed(2), 'iva' => $q->iva->toFixed(2),
                    'capital_pendiente' => $q->capital->toFixed(2), 'interes_pendiente' => $q->interes->toFixed(2),
                    'gastos_pendiente' => $q->gastos->toFixed(2), 'iva_pendiente' => $q->iva->toFixed(2), 'estado' => 'Pendiente',
                ]);
            }
            $this->db->table('liquidaciones')->insert([
                'solicitud_id' => $solicitudId, 'credito_id' => $creditoId, 'fecha' => $ahora,
                'capital' => $capital->toFixed(2), 'gasto_otorgamiento' => $s['calculo']['gasto_otorgamiento']->toFixed(2), 'iva_gastos' => $s['calculo']['iva_otorgamiento']->toFixed(2),
                'importe_neto' => $neto, 'medio_desembolso' => $v['datos']['medio'], 'cuenta_destino' => $v['datos']['cbu'], 'comprobante' => $v['datos']['comprobante'],
                'movimiento_caja_id' => null, 'usuario_id' => $usuarioId,
            ]);
            $liquidacionId = (int) $this->db->insertID();
            $fondos->consumir((int) $reserva['id']);
            if ($s['requiere_conformidad']) {
                // RN-22: la conformidad con el plan definido por el Gerente se registra antes de liquidar
                $this->db->table('alternativas_financiacion')->where('id', (int) $s['alternativa_id'])->update([
                    'estado' => 'Confirmada', 'fecha_conformidad' => $ahora, 'medio_conformidad' => 'Presencial, al liquidar',
                ]);
            }
            $solicitudes->cambiarEstado($fila, 'Liquidada', $usuarioId, 'Crédito liquidado: ' . formato_importe($neto) . ' por ' . mb_strtolower(Desembolso::MEDIOS[$v['datos']['medio']]) . '.', null);
            (new AlertaService($this->db))->resolver('solicitudes_credito', $solicitudId, (string) (session('cuenta')['nombre'] ?? 'Cajero'));
            $auditoria->registrar('creditos.liquidar', 'solicitudes_credito', $solicitudId, 'Exitoso', null, [
                'liquidacion' => $liquidacionId, 'credito' => $creditoId, 'neto' => $neto, 'medio' => $v['datos']['medio'],
                'cuenta' => $v['datos']['cbu'] === null ? null : '…' . substr((string) $v['datos']['cbu'], -4), 'evidencia_titularidad' => $v['datos']['evidencia'],
            ]);

            return ['estado' => 'Liquidada', 'mensaje' => 'Crédito liquidado: se desembolsaron ' . formato_importe($neto) . ' y quedó activo con su plan de ' . count($plan->cuotas) . ' cuotas.', 'credito_id' => $creditoId];
        });
    }

    /**
     * Constancia de desembolso (CU-27 paso 9): liquidación con el crédito y su plan definitivo.
     *
     * @return array<string, mixed>
     */
    public function constancia(int $solicitudId): array
    {
        $l = $this->db->query(
            'SELECT l.*, cr.numero AS credito_numero, cr.tna, cr.cantidad_cuotas, cr.estado AS estado_credito, s.numero AS solicitud_numero, '
            . "c.denominacion, c.cuit_cuil, li.nombre AS linea, ui.nombre + ' ' + ui.apellido AS responsable "
            . 'FROM dbo.liquidaciones l JOIN dbo.creditos cr ON cr.id = l.credito_id JOIN dbo.solicitudes_credito s ON s.id = l.solicitud_id '
            . 'JOIN dbo.clientes c ON c.id = cr.cliente_id JOIN dbo.versiones_condiciones v ON v.id = cr.version_condiciones_id '
            . 'JOIN dbo.lineas_credito li ON li.id = v.linea_credito_id JOIN dbo.usuarios_internos ui ON ui.id = l.usuario_id WHERE l.solicitud_id = ?',
            [$solicitudId],
        )->getRowArray();
        if ($l === null) {
            throw new ReglaNegocioException('La solicitud no tiene una liquidación registrada.');
        }
        $l['cuotas'] = $this->db->table('cuotas')->where('credito_id', (int) $l['credito_id'])->orderBy('numero')->get()->getResultArray();

        return $l;
    }
}
