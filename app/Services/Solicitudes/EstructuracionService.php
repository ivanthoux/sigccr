<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Finanzas\CalendarioHabil;
use App\Libraries\Finanzas\CapacidadPago;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Solicitudes\Estructurador;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\Simulador;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Estructuración automática (SS-04): CU-87 Generar Alternativas de Financiación, CU-64 Consultar Alternativas y
 * CU-79 Confirmar Alternativa de Financiación. RN-15, RN-16, RN-18, RN-23, RN-41, RN-48, RN-49, RN-63, RN-71.
 *
 * Cada generación registra una estructuración (ingresos, obligaciones, capacidad de pago y monto máximo) con sus
 * alternativas, viables (Vigentes por 7 días corridos) o descartadas con su motivo. Una nueva generación descarta las
 * alternativas vigentes de la anterior, que se conservan como antecedente.
 */
class EstructuracionService extends BaseService
{
    // ── CU-87 ──────────────────────────────────────────────────────────────────

    /**
     * CU-87 pasos 1 a 7 para una solicitud. Debe ejecutarse dentro de la transacción del caso de uso que lo incluye
     * (CU-86 paso 10, CU-64 A1, CU-79 A2 y A3), con la solicitud bloqueada.
     *
     * @param array<string, mixed> $s fila de la solicitud
     * @return array{estructuracion_id: int, viables: int, motivo: ?string, monto_maximo: string, capacidad: string}
     */
    public function generar(array $s, ?int $usuarioId = null, ?string $proceso = null): array
    {
        $version = $this->version((int) $s['version_condiciones_id']);
        $parametros = (new ParametroService($this->db))->vigente('Estructuracion');
        $cliente = $this->db->table('clientes')->select('id, ingresos_declarados, fecha_alta')->where('id', (int) $s['cliente_id'])->get()->getRowArray();
        $internos = (new EvaluacionService($this->db))->antecedentesInternos((array) $cliente, (int) $s['id']);
        $hoy = $this->hoyNegocio();
        $r = Estructurador::generar(
            $version,
            $parametros['valores'],
            Decimal::of($internos['ingresos']),
            Decimal::of($internos['obligaciones']),
            Decimal::of((string) $s['importe'])->toFixed(2),
            (int) $s['cantidad_cuotas'],
            new DateTimeImmutable($hoy),
        );

        $this->db->query(
            "UPDATE a SET a.estado = 'Descartada', a.motivo_descarte = 'Reemplazada por una nueva generación de alternativas.' FROM dbo.alternativas_financiacion a "
            . "JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE e.solicitud_id = ? AND a.estado = 'Vigente'",
            [(int) $s['id']],
        );
        $ahora = $this->ahoraUtc();
        $this->db->table('estructuraciones')->insert([
            'solicitud_id' => (int) $s['id'], 'version_parametros_id' => $parametros['id'], 'fecha' => $ahora,
            'ingresos_considerados' => $internos['ingresos'], 'obligaciones_vigentes' => $internos['obligaciones'],
            'capacidad_pago' => $r['capacidad']->toFixed(2), 'monto_maximo_financiable' => $r['monto_maximo']->toFixed(2),
        ]);
        $id = (int) $this->db->insertID();
        $dias = (int) ($parametros['valores']['vigencia_alternativas_dias'] ?? Simulador::VIGENCIA_DIAS);
        $vence = (new DateTimeImmutable($ahora))->modify("+{$dias} days")->format('Y-m-d H:i:s.vP');
        $viables = 0;
        foreach ($r['alternativas'] as $a) {
            $viables += $a['viable'] ? 1 : 0;
            $this->db->table('alternativas_financiacion')->insert([
                'estructuracion_id' => $id, 'importe' => $a['importe']->toFixed(2), 'cantidad_cuotas' => $a['cuotas'],
                'valor_cuota' => $a['primera_cuota']->toFixed(2), 'tea' => $a['tea']->toFixed(12), 'cftea' => $a['cftea']->toFixed(12),
                'total_a_pagar' => $a['total_a_pagar']->toFixed(2), 'vence' => $vence,
                'estado' => $a['viable'] ? 'Vigente' : 'Descartada', 'motivo_descarte' => $a['viable'] ? null : mb_substr((string) $a['motivo'], 0, 300),
            ]);
        }
        (new AuditoriaService($this->db))->registrar('alternativas.generar', 'solicitudes_credito', (int) $s['id'], $viables > 0 ? 'Exitoso' : 'Rechazado', $r['motivo'], [
            'estructuracion' => $id, 'version_parametros' => $parametros['numero'], 'capacidad' => $r['capacidad']->toFixed(2),
            'monto_maximo' => $r['monto_maximo']->toFixed(2), 'viables' => $viables, 'descartadas' => count($r['alternativas']) - $viables,
        ], $proceso);

        return ['estructuracion_id' => $id, 'viables' => $viables, 'motivo' => $r['motivo'], 'monto_maximo' => $r['monto_maximo']->toFixed(2), 'capacidad' => $r['capacidad']->toFixed(2)];
    }

    // ── CU-64 ──────────────────────────────────────────────────────────────────

    /**
     * CU-64 pasos 4 a 6: última estructuración de la solicitud con sus alternativas, la evaluación vigente y el
     * porcentaje de afectación aplicado. Null si la solicitud todavía no tiene estructuración.
     *
     * @return ?array<string, mixed>
     */
    public function deSolicitud(int $solicitudId): ?array
    {
        $e = $this->db->query(
            'SELECT TOP (1) e.*, p.numero AS version_parametros, p.valores AS parametros FROM dbo.estructuraciones e '
            . 'JOIN dbo.versiones_parametros p ON p.id = e.version_parametros_id WHERE e.solicitud_id = ? ORDER BY e.fecha DESC, e.id DESC',
            [$solicitudId],
        )->getRowArray();
        if ($e === null) {
            return null;
        }
        $e['parametros'] = json_decode((string) $e['parametros'], true);
        $e['alternativas'] = $this->db->query(
            "SELECT a.*, CASE WHEN a.estado = 'Vigente' AND a.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente "
            . 'FROM dbo.alternativas_financiacion a WHERE a.estructuracion_id = ? '
            . "ORDER BY CASE a.estado WHEN 'Confirmada' THEN 0 WHEN 'Vigente' THEN 1 ELSE 2 END, a.importe DESC, a.cantidad_cuotas",
            [(int) $e['id']],
        )->getResultArray();

        return $e;
    }

    /**
     * CU-64 paso 8 y CU-79 paso 5: alternativa con su plan preliminar, calculado con la versión de la solicitud, la
     * alícuota de IVA de la estructuración y su fecha.
     *
     * @return array<string, mixed>
     */
    public function alternativa(int $solicitudId, int $alternativaId): array
    {
        $a = $this->db->query(
            "SELECT a.*, CASE WHEN a.estado = 'Vigente' AND a.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente, "
            . 'e.fecha AS fecha_estructuracion, e.solicitud_id, p.valores AS parametros FROM dbo.alternativas_financiacion a '
            . 'JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id JOIN dbo.versiones_parametros p ON p.id = e.version_parametros_id '
            . 'WHERE a.id = ? AND e.solicitud_id = ?',
            [$alternativaId, $solicitudId],
        )->getRowArray();
        if ($a === null) {
            throw new ReglaNegocioException('La alternativa no existe o no corresponde a la solicitud.');
        }
        $s = $this->db->table('solicitudes_credito')->select('version_condiciones_id')->where('id', $solicitudId)->get()->getRowArray();
        $iva = (string) (json_decode((string) $a['parametros'], true)['alicuota_iva'] ?? '0.21');
        $fecha = (new DateTimeImmutable((string) $a['fecha_estructuracion']))->setTimezone(new DateTimeZone(config('Sigccr')->zonaNegocio));
        $a['calculo'] = Simulador::calcular($this->version((int) $s['version_condiciones_id']), Decimal::of((string) $a['importe'])->toFixed(2), (int) $a['cantidad_cuotas'], $iva, new DateTimeImmutable($fecha->format('Y-m-d'))) + ['alicuota_iva' => $iva];

        return $a;
    }

    /** Evaluación Vigente y no vencida de la solicitud (RN-20), o null. */
    public function evaluacionVigente(int $solicitudId): ?array
    {
        return $this->db->query(
            "SELECT TOP (1) id, categoria_riesgo, resultado, modalidad, vence FROM dbo.evaluaciones_crediticias WHERE solicitud_id = ? AND vigencia = 'Vigente' AND vence > SYSDATETIMEOFFSET()",
            [$solicitudId],
        )->getRowArray();
    }

    /** CU-64: la consulta de las alternativas queda en la auditoría. */
    public function registrarConsulta(int $solicitudId): void
    {
        (new AuditoriaService($this->db))->registrar('alternativas.consultar', 'solicitudes_credito', $solicitudId);
    }

    /**
     * CU-64 A1 y CU-79 A2: genera nuevas alternativas con la evaluación vigente. Si tampoco resultan viables, la
     * solicitud pasa a Pendiente de resolución con la causa y se alerta al Gerente.
     *
     * @return array{viables: int, estado: string, motivo: ?string}
     */
    public function regenerar(int $solicitudId, int $versionLeida, int $usuarioId): array
    {
        return $this->transaccion(function () use ($solicitudId, $versionLeida, $usuarioId): array {
            $s = $this->solicitudPendienteDePlan($solicitudId);
            if ($this->evaluacionVigente($solicitudId) === null) {
                throw new ReglaNegocioException('La evaluación crediticia no está vigente: no se pueden generar alternativas (RN-20).');
            }
            $this->actualizarConVersion('solicitudes_credito', $solicitudId, $versionLeida, ['ultimo_movimiento' => $this->ahoraUtc()]);
            $s['version_fila'] = $versionLeida + 1;
            $r = $this->generar($s, $usuarioId);
            if ($r['viables'] > 0) {
                return ['viables' => $r['viables'], 'estado' => 'PendienteSeleccionPlan', 'motivo' => null];
            }
            $this->derivarAResolucion($s, $usuarioId, 'Sin alternativas viables: ' . $r['motivo']);

            return ['viables' => 0, 'estado' => 'PendienteResolucion', 'motivo' => $r['motivo']];
        });
    }

    // ── CU-79 ──────────────────────────────────────────────────────────────────

    /**
     * CU-79 pasos 6 a 10 (A1 a A6).
     *
     * @param array<string, mixed> $datos fecha_conformidad, medio_conformidad, observaciones
     * @return array{estado: string, mensaje: string}
     */
    public function confirmar(int $solicitudId, int $alternativaId, int $versionLeida, array $datos, int $usuarioId): array
    {
        helper('sigccr');

        return $this->transaccion(function () use ($solicitudId, $alternativaId, $versionLeida, $datos, $usuarioId): array {
            $s = $this->solicitudPendienteDePlan($solicitudId);
            if ((int) $s['version_fila'] !== $versionLeida) {
                throw new ReglaNegocioException('La solicitud cambió mientras confirmaba: revise la información actualizada.', 'RN-24');
            }
            $solicitudes = new SolicitudService($this->db);
            $auditoria = new AuditoriaService($this->db);

            // Paso 2 / A5: la evaluación debe seguir vigente
            if ($this->evaluacionVigente($solicitudId) === null) {
                $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Vencida', version_fila = version_fila + 1 WHERE solicitud_id = ? AND vigencia = 'Vigente'", [$solicitudId]);
                $solicitudes->cambiarEstado($s, 'PendienteEvaluacion', $usuarioId, 'La evaluación crediticia perdió vigencia antes de confirmar la alternativa (RN-20).', null);
                $auditoria->registrar('alternativas.confirmar', 'solicitudes_credito', $solicitudId, 'Rechazado', 'Evaluación vencida o invalidada (CU-79 A5).');

                return ['estado' => 'PendienteEvaluacion', 'mensaje' => 'La evaluación crediticia ya no está vigente: la solicitud volvió a Pendiente de evaluación y se evaluará nuevamente (RN-20).'];
            }

            $a = $this->db->query(
                'SELECT a.*, e.fecha AS fecha_estructuracion FROM dbo.alternativas_financiacion a WITH (UPDLOCK, ROWLOCK) '
                . 'JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE a.id = ? AND e.solicitud_id = ?',
                [$alternativaId, $solicitudId],
            )->getRowArray();
            if ($a === null || $a['estado'] !== 'Vigente') {
                throw new ReglaNegocioException('La alternativa ya no está disponible para confirmar: elija otra de las vigentes.', 'RN-48');
            }
            $fechaGeneracion = (new DateTimeImmutable((string) $a['fecha_estructuracion']))->setTimezone(new DateTimeZone(config('Sigccr')->zonaNegocio))->format('Y-m-d');
            $c = Estructurador::validarConformidad($datos, $this->hoyNegocio(), $fechaGeneracion);
            if ($c['errores'] !== []) {
                throw new ValidacionException($c['errores'], 'RN-23');
            }

            // Paso 7 / A2: vigencia de la alternativa (RN-48)
            if (new DateTimeImmutable((string) $a['vence']) <= new DateTimeImmutable('now')) {
                $this->db->table('alternativas_financiacion')->where('id', $alternativaId)->update(['estado' => 'Vencida']);
                $auditoria->registrar('alternativas.confirmar', 'solicitudes_credito', $solicitudId, 'Rechazado', 'Alternativa vencida (CU-79 A2).', ['alternativa' => $alternativaId]);

                return ['estado' => 'PendienteSeleccionPlan', 'mensaje' => 'La alternativa superó su vigencia de 7 días corridos y no se puede confirmar (RN-48). Genere nuevas alternativas.'];
            }

            // Paso 7 / A3: capacidad de pago con las obligaciones actuales del cliente (RN-18)
            $cliente = $this->db->table('clientes')->select('id, ingresos_declarados, fecha_alta')->where('id', (int) $s['cliente_id'])->get()->getRowArray();
            $internos = (new EvaluacionService($this->db))->antecedentesInternos((array) $cliente, $solicitudId);
            $parametros = (new ParametroService($this->db))->vigente('Estructuracion')['valores'];
            $capacidad = CapacidadPago::capacidad(Decimal::of($internos['ingresos']), Decimal::of((string) $parametros['porcentaje_afectacion']), Decimal::of($internos['obligaciones']));
            if (Decimal::of((string) $a['valor_cuota'])->compare($capacidad) > 0) {
                $this->db->table('alternativas_financiacion')->where('id', $alternativaId)->update([
                    'estado' => 'Descartada', 'motivo_descarte' => 'Dejó de entrar en la capacidad de pago del cliente al confirmarla (CU-79 A3).',
                ]);
                $r = $this->generar($s, $usuarioId);
                if ($r['viables'] === 0) {
                    $this->derivarAResolucion($s, $usuarioId, 'La alternativa dejó de ser viable y no hay otras: ' . $r['motivo']);

                    return ['estado' => 'PendienteResolucion', 'mensaje' => 'La alternativa dejó de ser viable por un cambio en la capacidad de pago del cliente y no hay otras viables: la solicitud pasó a resolución del Gerente.'];
                }

                return ['estado' => 'PendienteSeleccionPlan', 'mensaje' => "La alternativa dejó de ser viable por un cambio en la capacidad de pago del cliente. Se generaron {$r['viables']} alternativas nuevas: confirme una de ellas."];
            }

            // Paso 6: la alternativa queda confirmada con la constancia de conformidad
            $medio = Estructurador::MEDIOS_CONFORMIDAD[$c['datos']['medio']];
            // La constancia informa el día; si es hoy se conserva el instante del registro
            $fechaConformidad = $c['datos']['fecha'] === $this->hoyNegocio() ? $this->ahoraUtc()
                : (new DateTimeImmutable($c['datos']['fecha'] . ' 12:00:00', new DateTimeZone(config('Sigccr')->zonaNegocio)))->format('Y-m-d H:i:s.vP');
            $this->db->table('alternativas_financiacion')->where('id', $alternativaId)->update([
                'estado' => 'Confirmada', 'fecha_conformidad' => $fechaConformidad, 'medio_conformidad' => mb_substr($medio, 0, 100),
            ]);
            $detalle = ['alternativa' => $alternativaId, 'importe' => (string) $a['importe'], 'cuotas' => (int) $a['cantidad_cuotas'], 'medio' => $c['datos']['medio'], 'observaciones' => $c['datos']['observaciones']];

            // Pasos 7 y 8: condiciones de la línea (RN-15, RN-16) y fondos (RN-41, RN-71)
            $version = $this->version((int) $s['version_condiciones_id']);
            $calculo = Simulador::calcular($version, Decimal::of((string) $a['importe'])->toFixed(2), (int) $a['cantidad_cuotas'], (string) $parametros['alicuota_iva'], new DateTimeImmutable($this->hoyNegocio()));
            $causa = null;
            if ($version['estado_linea'] !== 'Activa') {
                $causa = 'la línea de crédito está ' . mb_strtolower((string) $version['estado_linea']) . ' (RN-16)';
            } else {
                $reserva = (new FondosService($this->db))->reservarParaSolicitud($solicitudId, $calculo['neto']->toFixed(2));
                if ($reserva === null) {
                    $causa = 'fondos insuficientes para el desembolso de ' . formato_importe($calculo['neto']->toFixed(2)) . ' (RN-71)';
                }
            }
            if ($causa !== null) {
                // A1, A4: la confirmación queda registrada y resuelve el Gerente
                $this->derivarAResolucion($s, $usuarioId, "Alternativa confirmada por el cliente; requiere decisión gerencial: {$causa}.");
                $auditoria->registrar('alternativas.confirmar', 'solicitudes_credito', $solicitudId, 'Exitoso', "Derivada a resolución: {$causa}.", $detalle);

                return ['estado' => 'PendienteResolucion', 'mensaje' => "Alternativa confirmada. No se aprobó automáticamente porque {$causa}: la solicitud pasó a resolución del Gerente."];
            }

            // Paso 9: aprobación automática con su vencimiento (RN-23, RN-49) y la reserva de fondos (RN-41)
            $vence = $this->venceAprobacion();
            $this->db->table('resoluciones')->insert([
                'solicitud_id' => $solicitudId, 'decision' => 'Aprobada', 'automatica' => 1, 'fecha' => $this->ahoraUtc(),
                'fundamento' => mb_substr('Aprobación automática (RN-23): alternativa confirmada por el cliente (' . mb_strtolower($medio) . ', ' . $c['datos']['fecha'] . ')' . ($c['datos']['observaciones'] !== '' ? '. ' . $c['datos']['observaciones'] : '') . '.', 0, 2000),
                'alternativa_id' => $alternativaId, 'importe_aprobado' => (string) $a['importe'], 'cuotas_aprobadas' => (int) $a['cantidad_cuotas'],
                'vence_aprobacion' => $vence, 'vigente' => 1,
            ]);
            $solicitudes->cambiarEstado($s, 'AprobadaPendienteLiquidacion', $usuarioId, 'Aprobación automática de la alternativa confirmada por el cliente (RN-23).', null);
            (new ContratoService($this->db))->generar($solicitudId);
            (new AlertaService($this->db))->resolver('solicitudes_credito', $solicitudId, (string) (session('cuenta')['nombre'] ?? 'Analista'));
            $auditoria->registrar('alternativas.confirmar', 'solicitudes_credito', $solicitudId, 'Exitoso', 'Aprobación automática.', $detalle + ['vence_aprobacion' => $vence, 'reserva' => $calculo['neto']->toFixed(2)]);

            return ['estado' => 'AprobadaPendienteLiquidacion', 'mensaje' => 'Alternativa confirmada y solicitud aprobada automáticamente. La aprobación vence el ' . formato_fecha_hora($vence) . ': antes de esa fecha debe liquidarse (CU-27).'];
        });
    }

    // ── CU-26 (plan de la aprobación convencional) ─────────────────────────────

    /**
     * Capacidad de pago actual del cliente de la solicitud, con sus obligaciones vigentes y comprometidas (RN-18).
     *
     * @param array<string, mixed> $s
     */
    public function capacidadActual(array $s): Decimal
    {
        $cliente = $this->db->table('clientes')->select('id, ingresos_declarados, fecha_alta')->where('id', (int) $s['cliente_id'])->get()->getRowArray();
        $internos = (new EvaluacionService($this->db))->antecedentesInternos((array) $cliente, (int) $s['id']);
        $p = (new ParametroService($this->db))->vigente('Estructuracion')['valores'];

        return CapacidadPago::capacidad(Decimal::of($internos['ingresos']), Decimal::of((string) $p['porcentaje_afectacion']), Decimal::of($internos['obligaciones']));
    }

    /**
     * CU-26 paso 5 y A8: alternativa del plan que aprueba el Gerente. Con una alternativa vigente, la valida; con un
     * importe y cuotas definidos, los valida contra la línea (RN-76) y la capacidad, genera una estructuración nueva y
     * agrega esa combinación como alternativa. Dentro de la transacción de la resolución.
     *
     * @param array<string, mixed> $s solicitud bloqueada
     * @return array<string, mixed> alternativa (bloqueada)
     */
    public function alternativaParaAprobar(array $s, string $plan, ?int $alternativaId, ?string $importe, ?int $cuotas): array
    {
        $capacidad = $this->capacidadActual($s);
        if ($plan === 'definida') {
            $version = $this->version((int) $s['version_condiciones_id']);
            $v = Simulador::validar($version, (string) $importe, (string) $cuotas);
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-76');
            }
            $pe = (new ParametroService($this->db))->vigente('Estructuracion')['valores'];
            $iva = (string) $pe['alicuota_iva'];
            if (($pe['tope_global_cliente'] ?? null) !== null && Decimal::of((string) $v['importe'])->compare((string) $pe['tope_global_cliente']) > 0) {
                throw new ValidacionException(['importe' => 'El importe supera el tope global por cliente (' . formato_importe((string) $pe['tope_global_cliente']) . ', CU-59).'], 'RN-76');
            }
            $calculo = Simulador::calcular($version, (string) $v['importe'], (int) $v['cuotas'], $iva, new DateTimeImmutable($this->hoyNegocio()));
            if ($calculo['primera_cuota']->compare($capacidad) > 0) {
                throw new ValidacionException(['importe' => 'La primera cuota (' . formato_importe($calculo['primera_cuota']->toFixed(2)) . ') supera la capacidad de pago del cliente (' . formato_importe($capacidad->toFixed(2)) . ').'], 'RN-22');
            }
            $estructuracion = $this->generar($s);
            $existente = $this->db->query(
                "SELECT id FROM dbo.alternativas_financiacion WHERE estructuracion_id = ? AND importe = ? AND cantidad_cuotas = ? AND estado = 'Vigente'",
                [$estructuracion['estructuracion_id'], (string) $v['importe'], (int) $v['cuotas']],
            )->getRowArray();
            if ($existente !== null) {
                $alternativaId = (int) $existente['id'];
            } else {
                $vence = $this->db->query('SELECT MAX(vence) AS v FROM dbo.alternativas_financiacion WHERE estructuracion_id = ?', [$estructuracion['estructuracion_id']])->getRowArray()['v']
                    ?? (new DateTimeImmutable($this->ahoraUtc()))->modify('+' . Simulador::VIGENCIA_DIAS . ' days')->format('Y-m-d H:i:s.vP');
                $this->db->table('alternativas_financiacion')->insert([
                    'estructuracion_id' => $estructuracion['estructuracion_id'], 'importe' => (string) $v['importe'], 'cantidad_cuotas' => (int) $v['cuotas'],
                    'valor_cuota' => $calculo['primera_cuota']->toFixed(2), 'tea' => $calculo['tea']->toFixed(12), 'cftea' => $calculo['cftea']->toFixed(12),
                    'total_a_pagar' => $calculo['total_a_pagar']->toFixed(2), 'vence' => $vence, 'estado' => 'Vigente',
                ]);
                $alternativaId = (int) $this->db->insertID();
            }
        }
        $a = $this->db->query(
            'SELECT a.* FROM dbo.alternativas_financiacion a WITH (UPDLOCK, ROWLOCK) JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE a.id = ? AND e.solicitud_id = ?',
            [(int) $alternativaId, (int) $s['id']],
        )->getRowArray();
        if ($a === null) {
            throw new ValidacionException(['alternativa_id' => 'La alternativa no corresponde a la solicitud.'], 'RN-22');
        }
        $permitido = $plan === 'confirmada' ? ['Confirmada'] : ['Vigente'];
        if (!in_array($a['estado'], $permitido, true) || ($a['estado'] === 'Vigente' && new DateTimeImmutable((string) $a['vence']) <= new DateTimeImmutable('now'))) {
            throw new ValidacionException(['alternativa_id' => 'La alternativa ya no está vigente (RN-48): elija otra o defina el importe y las cuotas.'], 'RN-48');
        }
        if (Decimal::of((string) $a['valor_cuota'])->compare($capacidad) > 0) {
            throw new ValidacionException([$plan === 'definida' ? 'importe' : 'plan' => 'La primera cuota del plan supera la capacidad de pago actual del cliente (' . formato_importe($capacidad->toFixed(2)) . '): elija otro plan.'], 'RN-22');
        }

        return $a;
    }

    /** RN-49: vencimiento de una aprobación registrada ahora. */
    public function vencimientoAprobacion(): string
    {
        return $this->venceAprobacion();
    }

    // ── Auxiliares ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> solicitud bloqueada en Pendiente de selección de plan */
    private function solicitudPendienteDePlan(int $solicitudId): array
    {
        $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
        if ($s === null) {
            throw new ReglaNegocioException('La solicitud no existe.');
        }
        if ($s['estado'] !== 'PendienteSeleccionPlan') {
            throw new ReglaNegocioException('La solicitud no está pendiente de selección de plan (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . ').', 'RN-24');
        }

        return $s;
    }

    /** @param array<string, mixed> $s solicitud bloqueada */
    private function derivarAResolucion(array $s, ?int $usuarioId, string $causa, ?string $proceso = null): void
    {
        (new SolicitudService($this->db))->cambiarEstado($s, 'PendienteResolucion', $usuarioId, $causa, null, [], $proceso);
        $alertas = new AlertaService($this->db);
        $alertas->resolver('solicitudes_credito', (int) $s['id'], $proceso !== null ? "Proceso {$proceso}" : (string) (session('cuenta')['nombre'] ?? 'Analista'));
        $alertas->generar('Operativa', mb_substr("Solicitud N.º {$s['numero']}: {$causa}", 0, 500), 'Resolver la solicitud por el procedimiento convencional (CU-26).', 'Gerente', null, 'solicitudes_credito', (int) $s['id']);
    }

    /** RN-49: 72 horas hábiles desde ahora, con el calendario operativo. */
    private function venceAprobacion(): string
    {
        $zona = new DateTimeZone(config('Sigccr')->zonaNegocio);
        $dias = array_map(static fn ($f) => substr((string) $f['fecha'], 0, 10), $this->db->query('SELECT fecha FROM dbo.dias_no_habiles WHERE fecha >= CAST(? AS date)', [$this->hoyNegocio()])->getResultArray());

        return CalendarioHabil::venceAprobacion(new DateTimeImmutable('now', $zona), $dias)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.vP');
    }

    /** @return array<string, mixed> condiciones de la versión con el estado de su línea */
    public function version(int $versionId): array
    {
        $v = $this->db->query(
            'SELECT v.id, v.tna, v.gasto_otorgamiento_pct, v.gasto_administrativo_cuota, v.tipo_gasto_administrativo, v.gasto_administrativo_pct, v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas, '
            . 'l.estado AS estado_linea FROM dbo.versiones_condiciones v JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id WHERE v.id = ?',
            [$versionId],
        )->getRowArray();
        if ($v === null) {
            throw new ReglaNegocioException('La versión de condiciones no existe.');
        }

        return $v;
    }
}
