<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Adapters\Fabrica;
use App\Adapters\InformacionCrediticia\InformacionCrediticiaInterface;
use App\Adapters\ServicioNoDisponibleException;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Evaluacion\AntecedentesBcra;
use App\Libraries\Evaluacion\Contingencia;
use App\Libraries\Evaluacion\DimensionesEvaluacion;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\ModeloEvaluacion;
use App\Libraries\Lineas\Elegibilidad;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use Throwable;

/**
 * Evaluación crediticia (SS-04): CU-86 Ejecutar Evaluación Crediticia Automática, CU-25 Registrar Evaluación de
 * Contingencia y CU-24 Consultar Evaluación Crediticia. RN-20, RN-21, RN-61, RN-69, RNF-08, RNF-10.
 *
 * La evaluación automática se ejecuta en dos transacciones para no retener bloqueos mientras se espera al servicio
 * externo (hasta 10 s por petición y dos reintentos):
 *   1. registra el intento En proceso con la versión de parámetros vigente y pasa la solicitud a En evaluación;
 *   2. con la respuesta, calcula los indicadores de RN-69 y, si la solicitud sigue En evaluación, aplica el resultado.
 * Si la solicitud cambió entre las dos (RN-61, cancelación), el resultado se conserva como antecedente invalidado
 * (A4, A5). Un error interno en la segunda deja el intento No completado y devuelve la solicitud a Pendiente de
 * evaluación para la próxima ejecución (A7); lo mismo ocurre con un intento que quedó En proceso porque la ejecución
 * se interrumpió (recuperarInterrumpidas).
 */
class EvaluacionService extends BaseService
{
    public const PROCESO = 'evaluacion:ejecutar';

    /** Un intento En proceso más antiguo que esto se considera interrumpido (la consulta externa dura menos de 1 minuto). */
    public const MINUTOS_INTERRUPCION = 15;

    public const FUENTE_BCRA = 'BCRA-CENDEU';
    public const FUENTE_MANUAL = 'Manual';

    private ?InformacionCrediticiaInterface $adaptador;

    /** @param BaseConnection<mixed, mixed>|null $db */
    public function __construct(?BaseConnection $db = null, ?InformacionCrediticiaInterface $adaptador = null)
    {
        parent::__construct($db);
        $this->adaptador = $adaptador;
    }

    // ── CU-86 ──────────────────────────────────────────────────────────────────

    /**
     * CU-86 paso 2: solicitudes Pendientes de evaluación, de la más antigua a la más reciente.
     *
     * @return list<int>
     */
    public function pendientes(): array
    {
        return array_map(static fn ($f) => (int) $f['id'], $this->db->query(
            "SELECT id FROM dbo.solicitudes_credito WHERE estado = 'PendienteEvaluacion' ORDER BY ultimo_movimiento, id",
        )->getResultArray());
    }

    /**
     * Intentos que quedaron En proceso porque una ejecución se interrumpió: quedan No completados y, si la solicitud
     * sigue En evaluación, vuelve a Pendiente de evaluación (A7, RNF-10).
     *
     * @return int intentos recuperados
     */
    public function recuperarInterrumpidas(string $proceso = self::PROCESO): int
    {
        $ids = array_map(static fn ($f) => (int) $f['id'], $this->db->query(
            "SELECT id FROM dbo.evaluaciones_crediticias WHERE modalidad = 'Automatica' AND procesamiento = 'EnProceso' AND fecha_inicio < DATEADD(minute, ?, SYSDATETIMEOFFSET())",
            [-self::MINUTOS_INTERRUPCION],
        )->getResultArray());
        foreach ($ids as $id) {
            $this->transaccion(function () use ($id, $proceso): void {
                [$s, $e] = $this->bloquearIntento($id);
                if ($e['procesamiento'] === 'EnProceso') {
                    $this->revertirIntento($s, $e, 'La ejecución del proceso se interrumpió antes de registrar el resultado.', $proceso);
                }
            });
        }

        return count($ids);
    }

    /**
     * CU-86 pasos 3 a 11 para una solicitud.
     *
     * @return ?string resultado aplicado (Favorable, Desfavorable, RequiereAnalisisGerencial), NoCompletada,
     *                 SinEfecto (el resultado no se aplicó: A4, A5) o null si la solicitud ya no estaba pendiente
     */
    public function evaluar(int $solicitudId, string $proceso = self::PROCESO): ?string
    {
        // Pasos 3: intento En proceso y solicitud En evaluación
        $ctx = $this->transaccion(function () use ($solicitudId, $proceso): ?array {
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            if ($s === null || $s['estado'] !== 'PendienteEvaluacion') {
                return null;
            }
            $parametros = (new ParametroService($this->db))->vigente('Evaluacion');
            $this->db->table('evaluaciones_crediticias')->insert([
                'solicitud_id' => $solicitudId, 'version_parametros_id' => $parametros['id'], 'modalidad' => 'Automatica',
                'procesamiento' => 'EnProceso', 'fecha_inicio' => $this->ahoraUtc(),
            ]);
            $evaluacionId = (int) $this->db->insertID();
            (new SolicitudService($this->db))->cambiarEstado($s, 'EnEvaluacion', null, null, null, [], $proceso);

            return ['evaluacion_id' => $evaluacionId, 'parametros' => $parametros, 'cliente' => $this->cliente((int) $s['cliente_id'])];
        });
        if ($ctx === null) {
            return null;
        }

        // Pasos 4 y 5: consulta a la Central de Deudores, fuera de toda transacción
        $deudas = null;
        $motivo = null;
        $adaptador = null;
        $cuit = (string) ($ctx['cliente']['cuit_cuil'] ?? '');
        try {
            if (preg_match('/^\d{11}$/', $cuit) !== 1) {
                $motivo = 'El cliente no tiene CUIT/CUIL informado: no se puede consultar la Central de Deudores.';
            } else {
                $adaptador = $this->adaptador ?? Fabrica::informacionCrediticia();
                $deudas = $adaptador->consultar($cuit);
            }
        } catch (ServicioNoDisponibleException $e) {
            $motivo = $e->getMessage();
        } catch (Throwable $e) {
            $this->revertirPorError($ctx['evaluacion_id'], $e, $proceso);
            throw $e;
        }

        // Pasos 6 a 11 (A1 a A6)
        try {
            return $this->transaccion(function () use ($ctx, $deudas, $motivo, $adaptador, $proceso): string {
                $this->registrarIntercambios($ctx['evaluacion_id'], $adaptador?->intercambios() ?? []);

                return $motivo !== null
                    ? $this->noCompletada($ctx['evaluacion_id'], $motivo, $proceso)
                    : $this->finalizar($ctx, $deudas ?? [], $proceso);
            });
        } catch (Throwable $e) {
            $this->revertirPorError($ctx['evaluacion_id'], $e, $proceso);
            throw $e;
        }
    }

    /**
     * CU-86 paso 1: inicio inmediato al verificarse un legajo (CU-23). Si el proceso programado está en curso, no
     * hace nada: esa ejecución o la siguiente toman la solicitud. Los errores no se propagan a la pantalla de
     * verificación, que ya registró su resultado; quedan en el registro técnico.
     *
     * @return ?string resultado de evaluar(), o null si no se ejecutó
     */
    public function iniciarInmediata(int $solicitudId): ?string
    {
        $recurso = 'sigccr:' . self::PROCESO;
        $r = $this->db->query(
            "DECLARE @r INT; EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = 0; SELECT @r AS r;",
            [$recurso],
        )->getRowArray();
        if ((int) ($r['r'] ?? -1) < 0) {
            return null;
        }
        try {
            return $this->evaluar($solicitudId);
        } catch (Throwable $e) {
            log_message('error', "Evaluación inmediata de la solicitud {$solicitudId}: {$e->getMessage()}");

            return null;
        } finally {
            $this->db->query("EXEC sp_releaseapplock @Resource = ?, @LockOwner = 'Session';", [$recurso]);
        }
    }

    /**
     * CU-86 paso 6 y CU-87 paso 1: antecedentes internos del cliente.
     *
     *  - max_atraso: máximo atraso en días de las cuotas vencidas en los últimos 12 meses (pagadas: hasta la fecha
     *    efectiva del pago; impagas: hasta hoy); null si no tiene cuotas vencidas en ese período (sin antecedentes).
     *  - cuotas_vigentes: próxima cuota impaga de cada crédito Activo o En mora.
     *  - cuotas_comprometidas: primera cuota (la mayor del plan) de las demás solicitudes en trámite del cliente,
     *    incluidas las aprobadas pendientes de liquidación (RN-18).
     *
     * @param array<string, mixed> $cliente fila de clientes
     * @return array{max_atraso: ?int, cuotas_vencidas: int, cuotas_vigentes: string, cuotas_comprometidas: string, obligaciones: string, ingresos: string, antiguedad_meses: int, solicitudes_en_tramite: int}
     */
    public function antecedentesInternos(array $cliente, int $solicitudId): array
    {
        $hoy = $this->hoyNegocio();
        $clienteId = (int) $cliente['id'];
        $a = $this->db->query(
            'SELECT MAX(CASE WHEN x.atraso < 0 THEN 0 ELSE x.atraso END) AS max_atraso, COUNT(*) AS cuotas FROM ('
            . "SELECT DATEDIFF(day, c.vencimiento, CASE WHEN c.estado = 'Pagada' THEN COALESCE(CAST(p.pago AT TIME ZONE 'Argentina Standard Time' AS date), c.vencimiento) ELSE CAST(? AS date) END) AS atraso "
            . 'FROM dbo.cuotas c JOIN dbo.creditos cr ON cr.id = c.credito_id '
            . "OUTER APPLY (SELECT MAX(cb.fecha_efectiva_pago) AS pago FROM dbo.imputaciones i JOIN dbo.cobros cb ON cb.id = i.cobro_id WHERE i.cuota_id = c.id AND cb.estado = 'Confirmado') p "
            . "WHERE cr.cliente_id = ? AND c.estado <> 'Sustituida' AND c.vencimiento < CAST(? AS date) AND c.vencimiento >= DATEADD(month, -12, CAST(? AS date))) x",
            [$hoy, $clienteId, $hoy, $hoy],
        )->getRowArray();
        $vigentes = (string) ($this->db->query(
            'SELECT COALESCE(SUM(x.total), 0) AS total FROM (SELECT c.capital + c.interes + c.gastos + c.iva + c.concepto_no_capitalizable AS total, '
            . 'ROW_NUMBER() OVER (PARTITION BY c.credito_id ORDER BY c.numero) AS n FROM dbo.cuotas c JOIN dbo.creditos cr ON cr.id = c.credito_id '
            . "WHERE cr.cliente_id = ? AND cr.estado IN ('Activo', 'EnMora') AND c.estado IN ('Pendiente', 'Vencida')) x WHERE x.n = 1",
            [$clienteId],
        )->getRowArray()['total'] ?? '0');

        $marcas = implode(', ', array_fill(0, count(EstadosSolicitud::FINALES), '?'));
        $otras = $this->db->query(
            'SELECT s.importe, s.cantidad_cuotas, s.fecha_registro, v.tna, v.gasto_otorgamiento_pct, v.gasto_administrativo_cuota, v.tipo_gasto_administrativo, v.gasto_administrativo_pct '
            . 'FROM dbo.solicitudes_credito s JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id '
            . "WHERE s.cliente_id = ? AND s.id <> ? AND s.estado NOT IN ({$marcas})",
            [$clienteId, $solicitudId, ...EstadosSolicitud::FINALES],
        )->getResultArray();
        $simulaciones = new SimulacionService($this->db);
        $comprometidas = Decimal::sum(array_map(static fn (array $o): Decimal => Decimal::of((string) $simulaciones->calculo($o, (string) $o['fecha_registro'])['primera_cuota']), $otras));
        $vig = Decimal::of($vigentes)->round(2);

        return [
            'max_atraso' => (int) ($a['cuotas'] ?? 0) === 0 ? null : (int) $a['max_atraso'],
            'cuotas_vencidas' => (int) ($a['cuotas'] ?? 0),
            'cuotas_vigentes' => $vig->toFixed(2),
            'cuotas_comprometidas' => $comprometidas->toFixed(2),
            'obligaciones' => $vig->add($comprometidas)->toFixed(2),
            'ingresos' => Decimal::of((string) $cliente['ingresos_declarados'])->toFixed(2),
            'antiguedad_meses' => Elegibilidad::mesesCumplidos((string) $cliente['fecha_alta'], $hoy),
            'solicitudes_en_tramite' => count($otras),
        ];
    }

    /**
     * CU-25 paso 4: antecedentes internos actuales del cliente de la solicitud, para que el Analista los considere.
     *
     * @return array<string, mixed>
     */
    public function antecedentesDeSolicitud(int $solicitudId): array
    {
        $f = $this->db->table('solicitudes_credito')->select('cliente_id')->where('id', $solicitudId)->get()->getRowArray();
        if ($f === null) {
            throw new ReglaNegocioException('La solicitud no existe.');
        }

        return $this->antecedentesInternos($this->cliente((int) $f['cliente_id']), $solicitudId);
    }

    // ── CU-24 ──────────────────────────────────────────────────────────────────

    /**
     * CU-24 pasos 3 a 6: evaluaciones de la solicitud, de la más reciente a la más antigua, con sus antecedentes
     * externos, la versión de parámetros y la contingencia vinculada a cada intento no completado.
     *
     * @return list<array<string, mixed>>
     */
    public function deSolicitud(int $solicitudId): array
    {
        $filas = $this->db->query(
            "SELECT e.*, p.numero AS version_parametros, ui.nombre + ' ' + ui.apellido AS analista "
            . 'FROM dbo.evaluaciones_crediticias e JOIN dbo.versiones_parametros p ON p.id = e.version_parametros_id '
            . 'LEFT JOIN dbo.usuarios_internos ui ON ui.id = e.usuario_id WHERE e.solicitud_id = ? ORDER BY COALESCE(e.fecha_finalizacion, e.fecha_inicio) DESC, e.id DESC',
            [$solicitudId],
        )->getResultArray();
        $ids = array_map(static fn ($e) => (int) $e['id'], $filas);
        $antecedentes = $ids === [] ? [] : $this->db->table('antecedentes_externos')->whereIn('evaluacion_id', $ids)
            ->orderBy('periodo', 'DESC')->orderBy('entidad')->get()->getResultArray();
        $ahora = new DateTimeImmutable('now');
        foreach ($filas as &$e) {
            $e['indicadores'] = $e['indicadores'] === null ? null : json_decode((string) $e['indicadores'], true);
            $e['antecedentes'] = array_values(array_filter($antecedentes, static fn ($a) => (int) $a['evaluacion_id'] === (int) $e['id']));
            $e['vigencia_efectiva'] = DimensionesEvaluacion::vigenciaEfectiva($e['vigencia'], $e['vence'], $ahora);
        }
        unset($e);
        foreach ($filas as &$e) {
            $e['contingencia_id'] = null;
            foreach ($filas as $c) {
                if ((int) ($c['evaluacion_origen_id'] ?? 0) === (int) $e['id']) {
                    $e['contingencia_id'] = (int) $c['id'];
                }
            }
        }
        unset($e);

        return $filas;
    }

    /** CU-24: la consulta queda en la auditoría. */
    public function registrarConsulta(int $solicitudId): void
    {
        (new AuditoriaService($this->db))->registrar('evaluaciones.consultar', 'solicitudes_credito', $solicitudId);
    }

    // ── CU-25 ──────────────────────────────────────────────────────────────────

    /**
     * CU-25 paso 3 (A1, A3): intento automático no completado al que corresponde la contingencia.
     *
     * @param list<array<string, mixed>> $evaluaciones resultado de deSolicitud()
     * @return array<string, mixed>
     */
    public static function intentoParaContingencia(array $evaluaciones): array
    {
        foreach ($evaluaciones as $e) {
            if ($e['modalidad'] === 'Contingencia') {
                throw new ReglaNegocioException('La solicitud ya tiene una evaluación de contingencia registrada; puede consultarla en el detalle de la evaluación.', 'RN-21');
            }
            if ($e['modalidad'] === 'Automatica') {
                if ($e['procesamiento'] !== 'NoCompletada') {
                    break;
                }

                return $e;
            }
        }
        throw new ReglaNegocioException('No se encontró un intento de evaluación automática no completado: la contingencia solo procede en ese caso.', 'RN-21');
    }

    /**
     * CU-25 pasos 5 a 9 (A1 a A3).
     *
     * @param array<string, mixed> $datos formulario (ver Contingencia::validar)
     * @return int id de la evaluación de contingencia
     */
    public function registrarContingencia(int $solicitudId, int $versionLeida, array $datos, int $usuarioId): int
    {
        return $this->transaccion(function () use ($solicitudId, $versionLeida, $datos, $usuarioId): int {
            $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
            if ($s === null) {
                throw new ReglaNegocioException('La solicitud no existe.');
            }
            if ($s['estado'] !== 'PendienteContingencia') {
                throw new ReglaNegocioException('La solicitud ya no está pendiente de evaluación de contingencia (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . ').', 'RN-24');
            }
            $intento = self::intentoParaContingencia($this->deSolicitud($solicitudId));
            $v = Contingencia::validar($datos, $this->hoyNegocio());
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-21');
            }
            $d = $v['datos'];
            $internos = $this->antecedentesInternos($this->cliente((int) $s['cliente_id']), $solicitudId);
            $parametros = $this->valoresParametros((int) $intento['version_parametros_id']);
            $ahora = $this->ahoraUtc();
            $this->sustituirVigente($solicitudId);
            $this->db->table('evaluaciones_crediticias')->insert([
                'solicitud_id' => $solicitudId,
                'version_parametros_id' => (int) $intento['version_parametros_id'],
                'evaluacion_origen_id' => (int) $intento['id'],
                'modalidad' => 'Contingencia',
                'procesamiento' => 'Finalizada',
                // La recomendación del Analista no aprueba: resuelve el Gerente (RN-21, RN-22)
                'resultado' => 'RequiereAnalisisGerencial',
                'indicadores' => json_encode([
                    'antecedentes_utilizados' => $d['antecedentes'],
                    'detalle_antecedentes' => $d['detalle_antecedentes'],
                    'internos' => $internos,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'fecha_inicio' => $ahora,
                'fecha_finalizacion' => $ahora,
                'vence' => $this->vence($ahora, $parametros),
                'vigencia' => 'Vigente',
                'recomendacion' => $d['recomendacion'],
                'justificacion' => $d['justificacion'],
                'usuario_id' => $usuarioId,
            ]);
            $id = (int) $this->db->insertID();
            foreach ($d['deudas'] as $deuda) {
                $this->db->table('antecedentes_externos')->insert(['evaluacion_id' => $id, 'fuente' => self::FUENTE_MANUAL] + $deuda);
            }
            (new SolicitudService($this->db))->cambiarEstado($s, 'PendienteResolucion', $usuarioId, "Evaluación de contingencia registrada con recomendación {$d['recomendacion']}.", $versionLeida);
            $alertas = new AlertaService($this->db);
            $alertas->resolver('solicitudes_credito', $solicitudId, (string) (session('cuenta')['nombre'] ?? 'Analista'));
            $alertas->generar(
                'Operativa',
                "Solicitud N.º {$s['numero']}: evaluación de contingencia registrada con recomendación " . mb_strtolower($d['recomendacion']) . '.',
                'Resolver la solicitud por el procedimiento convencional (CU-26).',
                'Gerente',
                null,
                'solicitudes_credito',
                $solicitudId
            );
            (new AuditoriaService($this->db))->registrar('evaluaciones.registrar_contingencia', 'solicitudes_credito', $solicitudId, 'Exitoso', $d['justificacion'], [
                'evaluacion' => $id, 'intento_no_completado' => (int) $intento['id'], 'recomendacion' => $d['recomendacion'],
                'antecedentes' => $d['antecedentes'], 'deudas_manuales' => count($d['deudas']),
            ]);

            return $id;
        });
    }

    // ── Auxiliares ─────────────────────────────────────────────────────────────

    /**
     * Pasos 6 a 11 con la respuesta del servicio externo. Debe ejecutarse dentro de una transacción.
     *
     * @param array{evaluacion_id: int, parametros: array{id: int, numero: int, valores: array<string, mixed>}, cliente: array<string, mixed>} $ctx
     * @param list<array{entidad: string, periodo: string, situacion: int, monto: string}> $deudas
     */
    private function finalizar(array $ctx, array $deudas, string $proceso): string
    {
        $id = $ctx['evaluacion_id'];
        [$s, $e] = $this->bloquearIntento($id);
        $bcra = AntecedentesBcra::resumir($deudas, $this->hoyNegocio());
        foreach ($bcra['deudas'] as $d) {
            $this->db->table('antecedentes_externos')->insert(['evaluacion_id' => $id, 'fuente' => self::FUENTE_BCRA] + $d);
        }
        $internos = $this->antecedentesInternos($ctx['cliente'], (int) $s['id']);
        $p = $ctx['parametros']['valores'];
        $r = ModeloEvaluacion::evaluar(
            $bcra['peor_12m'],
            $bcra['peor_vigente'],
            $internos['max_atraso'],
            Decimal::of($internos['obligaciones']),
            Decimal::of($internos['ingresos']),
            $internos['antiguedad_meses'],
            $p,
        );

        // Paso 8 (A4, A5): el resultado se aplica solo si la solicitud sigue En evaluación con este intento
        $aplica = $s['estado'] === 'EnEvaluacion' && $e['procesamiento'] === 'EnProceso';
        if ($aplica) {
            $this->sustituirVigente((int) $s['id']);
        }
        $ahora = $this->ahoraUtc();
        $this->db->table('evaluaciones_crediticias')->where('id', $id)->set([
            'procesamiento' => 'Finalizada',
            'resultado' => $r['resultado'],
            'puntaje' => $r['puntaje']->toFixed(2),
            'categoria_riesgo' => $r['categoria'],
            'indicadores' => json_encode([
                'indicadores' => $r['indicadores'],
                'bcra' => ['peor_12m' => $bcra['peor_12m'], 'peor_vigente' => $bcra['peor_vigente'], 'ultimo_periodo' => $bcra['ultimo_periodo']],
                'internos' => $internos,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'fecha_finalizacion' => $ahora,
            'vence' => $this->vence($ahora, $p),
            'vigencia' => $aplica ? 'Vigente' : 'Invalidada',
        ])->set('version_fila', 'version_fila + 1', false)->update();

        $etiqueta = DimensionesEvaluacion::RESULTADO[$r['resultado']];
        $auditoria = new AuditoriaService($this->db);
        if (!$aplica) {
            $auditoria->registrar('evaluaciones.ejecutar', 'solicitudes_credito', (int) $s['id'], 'Rechazado', 'La solicitud cambió durante la evaluación (' . EstadosSolicitud::etiqueta((string) $s['estado']) . '): el resultado se conserva como antecedente sin aplicarse (RN-61).', [
                'evaluacion' => $id, 'resultado' => $r['resultado'], 'categoria' => $r['categoria'], 'version_parametros' => $ctx['parametros']['numero'],
            ], $proceso);

            return 'SinEfecto';
        }
        $destino = DimensionesEvaluacion::estadoSolicitud($r['resultado']);
        $motivo = "Evaluación automática: categoría {$r['categoria']}, {$r['puntaje']->toFixed(2)} puntos, resultado " . mb_strtolower($etiqueta) . '.';
        // Paso 10: con resultado favorable se generan las alternativas (CU-87); sin alternativas viables resuelve el Gerente (A6)
        $sinAlternativas = null;
        if ($destino === 'PendienteSeleccionPlan') {
            $estructuracion = (new EstructuracionService($this->db))->generar($s, null, $proceso);
            if ($estructuracion['viables'] === 0) {
                $destino = 'PendienteResolucion';
                $sinAlternativas = (string) $estructuracion['motivo'];
                $motivo .= ' Sin alternativas viables: ' . $sinAlternativas;
            }
        }
        (new SolicitudService($this->db))->cambiarEstado($s, $destino, null, $motivo, null, $destino === 'Rechazada' ? ['tipo_rechazo' => 'Crediticio'] : [], $proceso);
        $alertas = new AlertaService($this->db);
        $alertas->resolver('solicitudes_credito', (int) $s['id'], "Proceso {$proceso}");
        $this->alertar($s, $destino, match (true) {
            $destino === 'PendienteSeleccionPlan' => ["Solicitud N.º {$s['numero']}: evaluación favorable (categoría {$r['categoria']}); las alternativas de financiación están disponibles.", 'Presentar las alternativas al cliente y confirmar la elegida (CU-64, CU-79).'],
            $sinAlternativas !== null => ["Solicitud N.º {$s['numero']}: evaluación favorable sin alternativas viables. {$sinAlternativas}", 'Resolver la solicitud por el procedimiento convencional (CU-26).'],
            $destino === 'PendienteResolucion' => ["Solicitud N.º {$s['numero']}: la evaluación requiere análisis gerencial (categoría {$r['categoria']}).", 'Resolver la solicitud por el procedimiento convencional (CU-26).'],
            default => null,
        });
        $auditoria->registrar('evaluaciones.ejecutar', 'solicitudes_credito', (int) $s['id'], 'Exitoso', $motivo, [
            'evaluacion' => $id, 'resultado' => $r['resultado'], 'categoria' => $r['categoria'], 'puntaje' => $r['puntaje']->toFixed(2),
            'version_parametros' => $ctx['parametros']['numero'], 'estado' => $destino,
        ], $proceso);

        return $r['resultado'];
    }

    /** CU-86 A3: el servicio externo no respondió; la evaluación no se reintenta automáticamente (RN-21). */
    private function noCompletada(int $id, string $motivo, string $proceso): string
    {
        [$s, $e] = $this->bloquearIntento($id);
        if ($e['procesamiento'] !== 'EnProceso') {
            return 'SinEfecto';
        }
        $this->db->table('evaluaciones_crediticias')->where('id', $id)->set([
            'procesamiento' => 'NoCompletada', 'motivo_no_completada' => mb_substr($motivo, 0, 500), 'fecha_finalizacion' => $this->ahoraUtc(),
        ])->set('version_fila', 'version_fila + 1', false)->update();
        $auditoria = new AuditoriaService($this->db);
        if ($s['estado'] !== 'EnEvaluacion') {
            $auditoria->registrar('evaluaciones.ejecutar', 'solicitudes_credito', (int) $s['id'], 'Error', $motivo, ['evaluacion' => $id, 'estado' => $s['estado']], $proceso);

            return 'SinEfecto';
        }
        (new SolicitudService($this->db))->cambiarEstado($s, 'PendienteContingencia', null, 'La evaluación automática no pudo completarse: ' . $motivo, null, [], $proceso);
        (new AlertaService($this->db))->resolver('solicitudes_credito', (int) $s['id'], "Proceso {$proceso}");
        $this->alertar($s, 'PendienteContingencia', [
            "Solicitud N.º {$s['numero']}: la evaluación automática no pudo completarse ({$motivo})",
            'Registrar la evaluación de contingencia (CU-25).',
        ], 'Integracion');
        $auditoria->registrar('evaluaciones.ejecutar', 'solicitudes_credito', (int) $s['id'], 'Error', $motivo, ['evaluacion' => $id, 'estado' => 'PendienteContingencia'], $proceso);

        return 'NoCompletada';
    }

    /** A7: compensa un error interno posterior al paso 3. */
    private function revertirPorError(int $evaluacionId, Throwable $error, string $proceso): void
    {
        try {
            $this->transaccion(function () use ($evaluacionId, $error, $proceso): void {
                [$s, $e] = $this->bloquearIntento($evaluacionId);
                if ($e['procesamiento'] === 'EnProceso') {
                    $this->revertirIntento($s, $e, 'Error interno al procesar la evaluación: ' . mb_substr($error->getMessage(), 0, 300), $proceso);
                }
            });
        } catch (Throwable $e) {
            log_message('critical', "No se pudo revertir la evaluación {$evaluacionId}: {$e->getMessage()}");
        }
    }

    /**
     * El intento queda No completado y la solicitud vuelve a Pendiente de evaluación, sin alerta: la próxima
     * ejecución la toma de nuevo. Dentro de una transacción, con la solicitud y el intento bloqueados.
     *
     * @param array<string, mixed> $s
     * @param array<string, mixed> $e
     */
    private function revertirIntento(array $s, array $e, string $motivo, string $proceso): void
    {
        $this->db->table('evaluaciones_crediticias')->where('id', (int) $e['id'])->set([
            'procesamiento' => 'NoCompletada', 'motivo_no_completada' => mb_substr($motivo, 0, 500), 'fecha_finalizacion' => $this->ahoraUtc(),
        ])->set('version_fila', 'version_fila + 1', false)->update();
        if ($s['estado'] === 'EnEvaluacion') {
            (new SolicitudService($this->db))->cambiarEstado($s, 'PendienteEvaluacion', null, $motivo . ' Se reintentará en la próxima ejecución.', null, [], $proceso);
        }
        (new AuditoriaService($this->db))->registrar('evaluaciones.ejecutar', 'solicitudes_credito', (int) $e['solicitud_id'], 'Error', $motivo, ['evaluacion' => (int) $e['id']], $proceso);
    }

    /**
     * @param array<string, mixed>            $s
     * @param array{0: string, 1: string}|null $texto causa y acción pendiente
     */
    private function alertar(array $s, string $estado, ?array $texto, string $tipo = 'Operativa'): void
    {
        $rol = DimensionesEvaluacion::rolAlerta($estado);
        if ($texto === null || $rol === null) {
            return;
        }
        // Al Analista que registró la solicitud; al Gerente, por su rol
        $usuario = $rol === 'AnalistaCredito' && $s['usuario_id'] !== null ? (int) $s['usuario_id'] : null;
        (new AlertaService($this->db))->generar($tipo, mb_substr($texto[0], 0, 500), $texto[1], $usuario === null ? $rol : null, $usuario, 'solicitudes_credito', (int) $s['id']);
    }

    /**
     * Bloquea la solicitud y luego el intento, en el mismo orden que las demás operaciones sobre la solicitud
     * (RN-61 invalida evaluaciones con la solicitud ya bloqueada), para no producir bloqueos mutuos.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function bloquearIntento(int $evaluacionId): array
    {
        $solicitudId = (int) ($this->db->table('evaluaciones_crediticias')->select('solicitud_id')->where('id', $evaluacionId)->get()->getRowArray()['solicitud_id'] ?? 0);
        $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
        $e = $this->bloquear('evaluaciones_crediticias', [$evaluacionId])[0] ?? null;
        if ($s === null || $e === null) {
            throw new ReglaNegocioException('La evaluación no existe.');
        }

        return [$s, $e];
    }

    /** Una evaluación nueva sustituye a la vigente de la misma solicitud (4.2.4). */
    private function sustituirVigente(int $solicitudId): void
    {
        $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Sustituida', version_fila = version_fila + 1 WHERE solicitud_id = ? AND vigencia = 'Vigente'", [$solicitudId]);
    }

    /** @param list<array{operacion: string, codigo: string, duracion_ms: int, intento: int}> $intercambios */
    private function registrarIntercambios(int $evaluacionId, array $intercambios): void
    {
        foreach ($intercambios as $i) {
            $this->db->table('intercambios_externos')->insert([
                'servicio' => 'InformacionCrediticia', 'operacion' => mb_substr($i['operacion'], 0, 60), 'referencia' => "evaluacion:{$evaluacionId}",
                'fecha_hora' => $this->ahoraUtc(), 'duracion_ms' => $i['duracion_ms'], 'codigo_respuesta' => mb_substr($i['codigo'], 0, 20),
                'resumen' => "Intento {$i['intento']}",
            ]);
        }
    }

    /** @param array<string, mixed> $parametros valores de la versión de parámetros de evaluación */
    private function vence(string $desdeUtc, array $parametros): string
    {
        $dias = (int) ($parametros['vigencia_dias'] ?? 30);

        return (new DateTimeImmutable($desdeUtc))->modify("+{$dias} days")->format('Y-m-d H:i:s.vP');
    }

    /** @return array<string, mixed> */
    private function valoresParametros(int $versionId): array
    {
        $f = $this->db->table('versiones_parametros')->select('valores')->where('id', $versionId)->get()->getRowArray();

        return $f === null ? [] : (array) json_decode((string) $f['valores'], true);
    }

    /** @return array<string, mixed> */
    private function cliente(int $id): array
    {
        $c = $this->db->table('clientes')->select('id, tipo_cliente, cuit_cuil, ingresos_declarados, fecha_alta, denominacion')->where('id', $id)->get()->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('El cliente no existe.');
        }

        return $c;
    }
}
