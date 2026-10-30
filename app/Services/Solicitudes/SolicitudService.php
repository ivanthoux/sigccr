<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Lineas\Elegibilidad;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\RevisionLegajo;
use App\Libraries\Solicitudes\Simulador;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Clientes\LegajoService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use Throwable;

/**
 * Solicitudes de crédito (SS-04): CU-22 Registrar Solicitud, CU-23 Verificar Legajo, CU-84 Registrar Corrección y la
 * localización y consulta que usan (CU-29, CU-30). RN-11, RN-13, RN-16 a RN-19, RN-24, RN-44, RN-48, RN-61, RN-72.
 *
 * Cada cambio de estado sigue la tabla de 4.2.3 (EstadosSolicitud) y queda en transiciones_solicitud con su
 * responsable y motivo; ultimo_movimiento registra la actividad que controla la inactividad de RN-72. Cada revisión
 * documental se crea Pendiente de revisión al registrar la solicitud o una corrección, y conserva su resultado final.
 */
class SolicitudService extends BaseService
{
    /** Estados posteriores a la verificación desde los que un documento clave nuevo obliga a verificar de nuevo (RN-61). */
    private const REVERIFICAN = ['EnEvaluacion', 'PendienteContingencia', 'PendienteSeleccionPlan', 'PendienteResolucion', 'AprobadaPendienteLiquidacion'];

    /**
     * CU-29 (parcial): solicitudes por número, cliente o estado; sin criterios, las más recientes.
     *
     * @param array{numero?: string, texto?: string, estado?: string, cliente?: string} $c
     * @return list<array<string, mixed>>
     */
    public function buscar(array $c): array
    {
        $b = $this->db->table('solicitudes_credito s')
            ->select('s.id, s.numero, s.fecha_registro, s.importe, s.cantidad_cuotas, s.estado, s.tipo_rechazo, s.ultimo_movimiento, c.id AS cliente_id, c.denominacion, l.nombre AS linea')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('versiones_condiciones v', 'v.id = s.version_condiciones_id')
            ->join('lineas_credito l', 'l.id = v.linea_credito_id');
        if (preg_match('/^\d{1,9}$/', trim($c['numero'] ?? '')) === 1) {
            $b->where('s.numero', (int) trim($c['numero']));
        }
        foreach (preg_split('/\s+/u', Campos::texto($c['texto'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $b->like('c.denominacion', trim($palabra, ','));
        }
        if (ctype_digit((string) ($c['cliente'] ?? ''))) {
            $b->where('s.cliente_id', (int) $c['cliente']);
        }
        if (isset(EstadosSolicitud::ETIQUETAS[$c['estado'] ?? ''])) {
            $b->where('s.estado', $c['estado']);
        }

        return $b->orderBy('s.fecha_registro', 'DESC')->get(200)->getResultArray();
    }

    /**
     * CU-30 (parcial): solicitud con su cliente, línea y versión, plan preliminar, requisitos y documentación del
     * legajo, revisiones documentales, devolución gerencial vigente e historial de estados.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $s = $this->db->query(
            'SELECT s.id, s.numero, s.cliente_id, s.version_condiciones_id, s.simulacion_id, s.origen, s.fecha_registro, s.importe, s.cantidad_cuotas, '
            . 's.estado, s.tipo_rechazo, s.motivo_cancelacion, s.ultimo_movimiento, s.version_fila, s.usuario_id, '
            . "c.denominacion, c.categoria_ingresos, c.tipo_cliente, c.cuit_cuil, ui.nombre + ' ' + ui.apellido AS analista, "
            . 'l.id AS linea_id, l.nombre AS linea, v.numero AS version, v.tna, v.gasto_otorgamiento_pct, v.gasto_administrativo_cuota, '
            . 'v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas '
            . 'FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id '
            . 'JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id LEFT JOIN dbo.usuarios_internos ui ON ui.id = s.usuario_id WHERE s.id = ?',
            [$id],
        )->getRowArray();
        if ($s === null) {
            throw new ReglaNegocioException('La solicitud no existe.');
        }
        $legajo = new LegajoService($this->db);
        $s['calculo'] = (new SimulacionService($this->db))->calculo($s, (string) $s['fecha_registro']);
        $s['exigidos'] = $this->exigidos((int) $s['version_condiciones_id'], (string) $s['categoria_ingresos']);
        $s['documentos'] = $legajo->vigentes((int) $s['cliente_id']);
        $s['faltantes'] = RequisitosDocumentales::faltantes($s['exigidos'], array_column($s['documentos'], 'codigo'));
        $s['revisiones'] = $this->db->query(
            "SELECT r.id, r.fecha, r.estado, r.observaciones, r.motivo_rechazo, ui.nombre + ' ' + ui.apellido AS responsable "
            . 'FROM dbo.revisiones_documentales r LEFT JOIN dbo.usuarios_internos ui ON ui.id = r.usuario_id WHERE r.solicitud_id = ? ORDER BY r.fecha DESC, r.id DESC',
            [$id],
        )->getResultArray();
        $ids = array_map(static fn ($r) => (int) $r['id'], $s['revisiones']);
        $verificaciones = $ids === [] ? [] : $this->db->table('verificaciones_documento')
            ->select('revision_documental_id, documento_legajo_id, resultado, motivo_observacion, observacion')
            ->whereIn('revision_documental_id', $ids)->get()->getResultArray();
        foreach ($s['revisiones'] as &$r) {
            $r['verificaciones'] = array_values(array_filter($verificaciones, static fn ($v) => (int) $v['revision_documental_id'] === (int) $r['id']));
        }
        unset($r);
        $s['devolucion'] = $s['estado'] !== 'DevueltaRevision' ? null : $this->db->query(
            "SELECT TOP (1) fundamento, etapa_retorno, fecha FROM dbo.resoluciones WHERE solicitud_id = ? AND decision = 'DevueltaRevision' ORDER BY fecha DESC, id DESC",
            [$id],
        )->getRowArray();
        $s['aprobacion'] = $this->db->query(
            'SELECT TOP (1) r.fecha, r.importe_aprobado, r.cuotas_aprobadas, r.vence_aprobacion, r.automatica, r.fundamento FROM dbo.resoluciones r '
            . "WHERE r.solicitud_id = ? AND r.decision = 'Aprobada' AND r.vigente = 1",
            [$id],
        )->getRowArray();
        $s['transiciones'] = $this->db->query(
            "SELECT t.estado_origen, t.estado_destino, t.fecha_hora, t.motivo, t.proceso, ui.nombre + ' ' + ui.apellido AS responsable "
            . 'FROM dbo.transiciones_solicitud t LEFT JOIN dbo.usuarios_internos ui ON ui.id = t.usuario_id WHERE t.solicitud_id = ? ORDER BY t.fecha_hora, t.id',
            [$id],
        )->getResultArray();

        return $s;
    }

    /** Estado actual de la solicitud (para los mensajes posteriores a una operación). */
    public function estado(int $id): string
    {
        return (string) ($this->db->table('solicitudes_credito')->select('estado')->where('id', $id)->get()->getRowArray()['estado'] ?? '');
    }

    /** CU-30: la consulta de la solicitud queda en la auditoría. */
    public function registrarConsulta(int $id): void
    {
        (new AuditoriaService($this->db))->registrar('solicitudes.consultar', 'solicitudes_credito', $id);
    }

    /**
     * Documentación que la versión exige para la categoría del cliente (RN-11, D-14).
     *
     * @return list<string> códigos
     */
    public function exigidos(int $versionId, string $categoria): array
    {
        $filas = $this->db->table('requisitos_documentales r')->select('t.codigo, r.categoria_ingresos, r.obligatorio')
            ->join('tipos_documentacion t', 't.id = r.tipo_documentacion_id')->where('r.version_condiciones_id', $versionId)->get()->getResultArray();

        return RequisitosDocumentales::exigidosPorLinea($filas, $categoria);
    }

    /**
     * CU-22 pasos 5 a 10 (A1 a A4, A6).
     *
     * @param list<array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza?: ?int}> $archivos
     * @return array{id: int, numero: int}
     */
    public function registrar(int $simulacionId, array $archivos, int $usuarioId): array
    {
        $legajo = new LegajoService($this->db);
        $errores = [];
        $documentos = $legajo->validarArchivos($archivos, $errores);
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RNF-12');
        }
        $movidos = [];
        try {
            return $this->transaccion(function () use ($simulacionId, $documentos, $usuarioId, $legajo, &$movidos): array {
                $sim = $this->db->query(
                    'SELECT s.id, s.cliente_id, s.version_condiciones_id, s.importe, s.cantidad_cuotas, v.linea_credito_id, '
                    . "CASE WHEN s.estado = 'Vigente' AND s.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente "
                    . 'FROM dbo.simulaciones s WITH (UPDLOCK, ROWLOCK) JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id WHERE s.id = ?',
                    [$simulacionId],
                )->getRowArray();
                if ($sim === null) {
                    throw new ReglaNegocioException('La simulación no existe.');
                }
                if ((int) $sim['vigente'] !== 1) {
                    throw new ReglaNegocioException('La simulación superó su vigencia de 7 días corridos: genere una nueva (RN-48).', 'RN-48');
                }
                $clienteId = (int) $sim['cliente_id'];
                // El bloqueo del cliente serializa dos registros simultáneos para la misma línea (RN-18)
                $cliente = $this->bloquear('clientes', [$clienteId])[0] ?? null;
                $datos = (new SimulacionService($this->db))->cliente($clienteId);
                if ($cliente === null || $datos['estado'] !== 'Activo') {
                    throw new ReglaNegocioException('El cliente está desactivado: no puede registrar solicitudes.');
                }
                if (($datos['cuit_cuil'] ?? null) === null || $datos['cuit_cuil'] === '') {
                    throw new ReglaNegocioException('El cliente no tiene CUIT/CUIL informado: complételo con «Modificar datos» antes de registrar la solicitud.', 'CU-22');
                }
                $lineaId = (int) $sim['linea_credito_id'];
                $this->bloquear('lineas_credito', [$lineaId]);
                $version = $this->versionParaElegibilidad((int) $sim['version_condiciones_id']);
                $motivo = Elegibilidad::motivoNoAdmitida($version, $datos, $this->hoyNegocio());
                if ($motivo !== null) {
                    throw new ReglaNegocioException($motivo . ' No puede registrarse la solicitud con esta simulación.', 'RN-13');
                }
                $limites = Simulador::validar($version, (string) $sim['importe'], (string) $sim['cantidad_cuotas']);
                if ($limites['errores'] !== []) {
                    throw new ReglaNegocioException(implode(' ', $limites['errores']), 'RN-76');
                }
                $existente = $this->db->query(
                    'SELECT TOP (1) s.numero, s.estado FROM dbo.solicitudes_credito s JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id '
                    . 'WHERE s.cliente_id = ? AND v.linea_credito_id = ? AND s.estado NOT IN (' . implode(', ', array_fill(0, count(EstadosSolicitud::FINALES), '?')) . ')',
                    [$clienteId, $lineaId, ...EstadosSolicitud::FINALES],
                )->getRowArray();
                if ($existente !== null) {
                    throw new ReglaNegocioException('El cliente ya tiene la solicitud N.º ' . $existente['numero'] . ' de esta línea en trámite ('
                        . mb_strtolower(EstadosSolicitud::etiqueta((string) $existente['estado'])) . '): no puede registrarse otra (RN-18).', 'RN-18');
                }
                // A4: la documentación que exige la línea debe estar en el legajo o adjuntarse ahora
                $exigidos = $this->exigidos((int) $sim['version_condiciones_id'], (string) $datos['categoria_ingresos']);
                $presentes = array_merge(array_keys($legajo->vigentesPorTipo($clienteId)), array_column($documentos, 'codigo'));
                $faltantes = RequisitosDocumentales::faltantes($exigidos, $presentes);
                if ($faltantes !== []) {
                    $errores = [];
                    foreach ($faltantes as $f) {
                        $errores['doc_' . $f] = 'La línea exige este documento y no está en el legajo: adjúntelo (RN-11).';
                    }
                    throw new ValidacionException($errores, 'RN-11');
                }
                foreach ($documentos as $doc) {
                    $movidos[] = $legajo->guardar($clienteId, $doc, $usuarioId)['ruta'];
                }
                $ahora = $this->ahoraUtc();
                $this->db->table('solicitudes_credito')->insert([
                    'cliente_id' => $clienteId, 'version_condiciones_id' => (int) $sim['version_condiciones_id'], 'simulacion_id' => $simulacionId,
                    'origen' => 'Independiente', 'fecha_registro' => $ahora, 'importe' => $sim['importe'], 'cantidad_cuotas' => (int) $sim['cantidad_cuotas'],
                    'estado' => 'PendienteVerificacion', 'ultimo_movimiento' => $ahora, 'usuario_id' => $usuarioId,
                ]);
                $id = (int) $this->db->insertID();
                $numero = (int) $this->db->table('solicitudes_credito')->select('numero')->where('id', $id)->get()->getRow()->numero;
                $this->registrarTransicion($id, null, 'PendienteVerificacion', $usuarioId, null);
                $this->nuevaRevision($id, (int) $sim['version_condiciones_id']);
                (new AuditoriaService($this->db))->registrar('solicitudes.registrar', 'solicitudes_credito', $id, 'Exitoso', null, [
                    'numero' => $numero, 'cliente' => $clienteId, 'linea' => $lineaId, 'simulacion' => $simulacionId,
                    'importe' => $sim['importe'], 'cuotas' => (int) $sim['cantidad_cuotas'], 'documentos' => count($documentos),
                ]);

                return ['id' => $id, 'numero' => $numero];
            });
        } catch (Throwable $e) {
            LegajoService::descartar($movidos);
            throw $e;
        }
    }

    /**
     * CU-23 A4: documentación incorporada durante la verificación; se asocia a la solicitud a través del legajo y la
     * revisión pendiente la incluye.
     *
     * @param list<array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza?: ?int}> $archivos
     */
    public function incorporarDocumentos(int $id, array $archivos, int $usuarioId): int
    {
        $legajo = new LegajoService($this->db);
        $errores = [];
        $documentos = $legajo->validarArchivos($archivos, $errores);
        if ($documentos === [] && $errores === []) {
            $errores['documentos'] = 'Seleccione al menos un archivo.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RNF-12');
        }
        $movidos = [];
        try {
            $this->transaccion(function () use ($id, $documentos, $usuarioId, $legajo, &$movidos): void {
                $s = $this->bloquear('solicitudes_credito', [$id])[0] ?? null;
                if ($s === null || $s['estado'] !== 'PendienteVerificacion') {
                    throw new ReglaNegocioException('Solo se incorpora documentación durante la verificación del legajo.', 'RN-24');
                }
                foreach ($documentos as $doc) {
                    $movidos[] = $legajo->guardar((int) $s['cliente_id'], $doc, $usuarioId)['ruta'];
                }
                $this->db->table('solicitudes_credito')->where('id', $id)->update(['ultimo_movimiento' => $this->ahoraUtc()]);
                (new AuditoriaService($this->db))->registrar('solicitudes.incorporar_documentos', 'solicitudes_credito', $id, 'Exitoso', null, ['documentos' => array_column($documentos, 'nombre')]);
            });
        } catch (Throwable $e) {
            LegajoService::descartar($movidos);
            throw $e;
        }

        return count($documentos);
    }

    /**
     * CU-23 pasos 5 a 8 (A1, A2): registra la revisión y cambia el estado de la solicitud.
     *
     * @param array<int|string, array{resultado?: string, motivo?: string, observacion?: string}> $verificaciones
     * @return string estado resultante de la solicitud
     */
    public function verificar(int $id, int $versionLeida, string $resultado, array $verificaciones, string $observaciones, string $motivoRechazo, int $usuarioId): string
    {
        return $this->transaccion(function () use ($id, $versionLeida, $resultado, $verificaciones, $observaciones, $motivoRechazo, $usuarioId): string {
            $s = $this->bloquear('solicitudes_credito', [$id])[0] ?? null;
            if ($s === null) {
                throw new ReglaNegocioException('La solicitud no existe.');
            }
            if ($s['estado'] !== 'PendienteVerificacion') {
                throw new ReglaNegocioException('La solicitud ya no está pendiente de verificación (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . ').', 'RN-24');
            }
            $cliente = (new SimulacionService($this->db))->cliente((int) $s['cliente_id']);
            $exigidos = $this->exigidos((int) $s['version_condiciones_id'], (string) $cliente['categoria_ingresos']);
            $presentados = (new LegajoService($this->db))->vigentesPorTipo((int) $s['cliente_id']);
            $r = RevisionLegajo::validar($exigidos, $presentados, $verificaciones, $resultado, $observaciones, $motivoRechazo);
            if ($r['errores'] !== []) {
                throw new ValidacionException($r['errores'], 'RN-19');
            }
            $revision = $this->revisionPendiente($id, (int) $s['version_condiciones_id']);
            foreach ($r['verificaciones'] as $docId => $v) {
                $this->db->table('verificaciones_documento')->insert([
                    'revision_documental_id' => $revision, 'documento_legajo_id' => $docId, 'resultado' => $v['resultado'],
                    'motivo_observacion' => $v['motivo'], 'observacion' => $v['observacion'], 'fecha_verificacion' => $this->ahoraUtc(),
                ]);
            }
            $texto = Campos::texto($observaciones);
            $motivo = Campos::texto($motivoRechazo);
            $this->db->table('revisiones_documentales')->where('id', $revision)->update([
                'estado' => $resultado, 'fecha' => $this->ahoraUtc(), 'usuario_id' => $usuarioId,
                'observaciones' => $texto === '' ? null : $texto, 'motivo_rechazo' => $resultado === 'Rechazada' ? $motivo : null,
            ]);
            $destino = RevisionLegajo::estadoSolicitud($resultado);
            $this->cambiarEstado(
                $s,
                $destino,
                $usuarioId,
                $resultado === 'Rechazada' ? $motivo : ($resultado === 'Observada' ? $texto : null),
                $versionLeida,
                $resultado === 'Rechazada' ? ['tipo_rechazo' => 'Documental'] : []
            );
            (new AuditoriaService($this->db))->registrar('solicitudes.verificar_legajo', 'solicitudes_credito', $id, 'Exitoso', $destino === 'Rechazada' ? $motivo : null, [
                'revision' => $revision, 'resultado' => $resultado, 'faltantes' => $r['faltantes'],
                'documentos' => array_map(static fn ($v) => $v['resultado'], $r['verificaciones']),
            ]);

            // Paso 7: la evaluación crediticia automática (CU-86) toma las solicitudes Pendientes de evaluación; el
            // controlador la inicia inmediatamente después de confirmar esta transacción (EvaluacionService::iniciarInmediata)
            return $destino;
        });
    }

    /**
     * CU-84 pasos 3 a 8 (A1, A3): incorpora documentación o aclaraciones y determina la etapa siguiente.
     *
     * @param list<array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza?: ?int}> $archivos
     * @return array{estado: string, cambio: bool, pendientes: list<string>}
     */
    public function registrarCorreccion(int $id, int $versionLeida, array $archivos, string $aclaracion, int $usuarioId): array
    {
        $legajo = new LegajoService($this->db);
        $errores = [];
        $documentos = $legajo->validarArchivos($archivos, $errores);
        $texto = Campos::texto($aclaracion);
        if ($documentos === [] && $errores === [] && mb_strlen($texto) < 10) {
            $errores['aclaracion'] = 'Adjunte la documentación pedida o registre la aclaración que corresponde (al menos 10 caracteres).';
        } elseif (mb_strlen($texto) > 500) {
            $errores['aclaracion'] = 'La aclaración admite hasta 500 caracteres.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RNF-12');
        }
        $movidos = [];
        try {
            return $this->transaccion(function () use ($id, $versionLeida, $documentos, $texto, $usuarioId, $legajo, &$movidos): array {
                $s = $this->bloquear('solicitudes_credito', [$id])[0] ?? null;
                if ($s === null) {
                    throw new ReglaNegocioException('La solicitud no existe.');
                }
                if (!in_array($s['estado'], EstadosSolicitud::CORREGIBLES, true)) {
                    throw new ReglaNegocioException('La solicitud no admite correcciones en su estado actual ('
                        . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . ').', 'RN-24');
                }
                $clienteId = (int) $s['cliente_id'];
                foreach ($documentos as $doc) {
                    $movidos[] = $legajo->guardar($clienteId, $doc, $usuarioId)['ruta'];
                }
                $pendientes = $this->pendientesDeCorreccion($s);
                $etapa = $s['estado'] !== 'DevueltaRevision' ? null : ($this->db->query(
                    "SELECT TOP (1) etapa_retorno FROM dbo.resoluciones WHERE solicitud_id = ? AND decision = 'DevueltaRevision' ORDER BY fecha DESC, id DESC",
                    [$id],
                )->getRowArray()['etapa_retorno'] ?? null);
                $destino = EstadosSolicitud::trasCorreccion((string) $s['estado'], $documentos !== [], $pendientes === [], $etapa);
                if ($destino === null) {
                    // A1: la corrección parcial se guarda y la solicitud conserva su estado hasta completar los faltantes
                    $this->db->query('UPDATE dbo.solicitudes_credito SET ultimo_movimiento = ?, version_fila = version_fila + 1 WHERE id = ? AND version_fila = ?', [$this->ahoraUtc(), $id, $versionLeida]);
                    if ($this->db->affectedRows() !== 1) {
                        throw new \App\Exceptions\ConflictoConcurrenciaException('solicitudes_credito', $id);
                    }
                } else {
                    $this->cambiarEstado($s, $destino, $usuarioId, $texto === '' ? 'Corrección registrada' : $texto, $versionLeida);
                    if ($destino === 'PendienteVerificacion') {
                        $this->nuevaRevision($id, (int) $s['version_condiciones_id']);
                    }
                }
                (new AuditoriaService($this->db))->registrar('solicitudes.registrar_correccion', 'solicitudes_credito', $id, 'Exitoso', $texto === '' ? null : $texto, [
                    'documentos' => array_column($documentos, 'nombre'), 'estado_anterior' => $s['estado'], 'estado_nuevo' => $destino ?? $s['estado'], 'pendientes' => $pendientes,
                ]);

                return ['estado' => $destino ?? (string) $s['estado'], 'cambio' => $destino !== null, 'pendientes' => $pendientes];
            });
        } catch (Throwable $e) {
            LegajoService::descartar($movidos);
            throw $e;
        }
    }

    /**
     * RN-61: un documento clave incorporado al legajo devuelve a Pendiente de verificación las solicitudes del cliente
     * que ya pasaron por ella. Se invalidan las evaluaciones vigentes, queda sin efecto una aprobación vigente, se
     * liberan sus afectaciones de fondos y se alerta al Analista. Debe llamarse dentro de la transacción que incorporó
     * el documento.
     *
     * @return int solicitudes devueltas a verificación
     */
    public function documentoClaveIncorporado(int $clienteId, int $usuarioId): int
    {
        $marcas = implode(', ', array_fill(0, count(self::REVERIFICAN), '?'));
        $ids = array_map(static fn ($f) => (int) $f['id'], $this->db->query(
            "SELECT id FROM dbo.solicitudes_credito WHERE cliente_id = ? AND estado IN ({$marcas})",
            [$clienteId, ...self::REVERIFICAN],
        )->getResultArray());
        $alertas = new AlertaService($this->db);
        foreach ($ids === [] ? [] : $this->bloquear('solicitudes_credito', $ids) as $s) {
            $sid = (int) $s['id'];
            $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Invalidada', version_fila = version_fila + 1 WHERE solicitud_id = ? AND vigencia = 'Vigente'", [$sid]);
            $this->db->query("UPDATE dbo.resoluciones SET vigente = 0 WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$sid]);
            $this->db->query("UPDATE dbo.afectaciones_fondos SET estado = 'Liberada', version_fila = version_fila + 1 WHERE referencia_tipo = 'Solicitud' AND referencia_id = ? AND estado = 'Vigente'", [$sid]);
            $this->cambiarEstado($s, 'PendienteVerificacion', $usuarioId, 'Se incorporó un documento clave al legajo (RN-61).', null);
            $this->nuevaRevision($sid, (int) $s['version_condiciones_id']);
            $alertas->generar(
                'Operativa',
                "Se incorporó un documento clave al legajo de la solicitud N.º {$s['numero']}: volvió a verificación y su evaluación quedó invalidada (RN-61).",
                'Verificar nuevamente el legajo (CU-23).',
                $s['usuario_id'] === null ? 'AnalistaCredito' : null,
                $s['usuario_id'] === null ? null : (int) $s['usuario_id'],
                'solicitudes_credito',
                $sid
            );
        }

        return count($ids);
    }

    // ── Auxiliares ─────────────────────────────────────────────────────────────

    /**
     * Cambia el estado según 4.2.3 y registra la transición. Con $versionLeida (pantalla) controla la concurrencia (RN-24);
     * sin ella, la fila ya está bloqueada por la operación.
     *
     * La usan también la evaluación (CU-86, con el nombre del proceso automático) y la contingencia (CU-25).
     *
     * @param array<string, mixed> $s fila bloqueada de la solicitud
     * @param array<string, mixed> $extra columnas adicionales (tipo_rechazo)
     */
    public function cambiarEstado(array $s, string $destino, ?int $usuarioId, ?string $motivo, ?int $versionLeida, array $extra = [], ?string $proceso = null): void
    {
        if (!EstadosSolicitud::puede((string) $s['estado'], $destino)) {
            throw new ReglaNegocioException('La solicitud no puede pasar de «' . EstadosSolicitud::etiqueta((string) $s['estado']) . '» a «' . EstadosSolicitud::etiqueta($destino) . '».', 'RN-24');
        }
        $datos = ['estado' => $destino, 'ultimo_movimiento' => $this->ahoraUtc()] + $extra;
        if ($versionLeida !== null) {
            $this->actualizarConVersion('solicitudes_credito', (int) $s['id'], $versionLeida, $datos);
        } else {
            $this->db->table('solicitudes_credito')->set($datos)->set('version_fila', 'version_fila + 1', false)->where('id', (int) $s['id'])->update();
        }
        $this->registrarTransicion((int) $s['id'], (string) $s['estado'], $destino, $usuarioId, $motivo, $proceso);
    }

    private function registrarTransicion(int $solicitudId, ?string $origen, string $destino, ?int $usuarioId, ?string $motivo, ?string $proceso = null): void
    {
        $this->db->table('transiciones_solicitud')->insert([
            'solicitud_id' => $solicitudId, 'estado_origen' => $origen, 'estado_destino' => $destino, 'fecha_hora' => $this->ahoraUtc(),
            'usuario_id' => $usuarioId, 'proceso' => $proceso, 'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 500),
        ]);
    }

    private function nuevaRevision(int $solicitudId, int $versionId): int
    {
        $this->db->table('revisiones_documentales')->insert([
            'solicitud_id' => $solicitudId, 'version_condiciones_id' => $versionId, 'fecha' => $this->ahoraUtc(), 'estado' => 'PendienteRevision',
        ]);

        return (int) $this->db->insertID();
    }

    /** Revisión pendiente de la solicitud; se crea si no existe (solicitudes anteriores a esta versión del sistema). */
    private function revisionPendiente(int $solicitudId, int $versionId): int
    {
        $r = $this->db->query(
            "SELECT TOP (1) id FROM dbo.revisiones_documentales WITH (UPDLOCK, ROWLOCK) WHERE solicitud_id = ? AND estado = 'PendienteRevision' ORDER BY fecha DESC, id DESC",
            [$solicitudId],
        )->getRowArray();

        return $r === null ? $this->nuevaRevision($solicitudId, $versionId) : (int) $r['id'];
    }

    /**
     * CU-84 paso 5: lo que falta atender de la última revisión observada: documentos exigidos ausentes y documentos
     * observados o rechazados que todavía no fueron reemplazados.
     *
     * @param array<string, mixed> $s
     * @return list<string> descripciones para mostrar
     */
    private function pendientesDeCorreccion(array $s): array
    {
        $cliente = (new SimulacionService($this->db))->cliente((int) $s['cliente_id']);
        $vigentes = (new LegajoService($this->db))->vigentes((int) $s['cliente_id']);
        $nombres = array_column((new LegajoService($this->db))->tiposDocumentacion(), 'nombre', 'codigo');
        $pendientes = [];
        foreach (RequisitosDocumentales::faltantes($this->exigidos((int) $s['version_condiciones_id'], (string) $cliente['categoria_ingresos']), array_column($vigentes, 'codigo')) as $c) {
            $pendientes[] = 'Falta: ' . ($nombres[$c] ?? $c);
        }
        $observados = $this->db->query(
            "SELECT v.documento_legajo_id, d.nombre_original FROM dbo.verificaciones_documento v JOIN dbo.revisiones_documentales r ON r.id = v.revision_documental_id "
            . 'JOIN dbo.documentos_legajo d ON d.id = v.documento_legajo_id '
            . "WHERE r.id = (SELECT TOP (1) id FROM dbo.revisiones_documentales WHERE solicitud_id = ? AND estado = 'Observada' ORDER BY fecha DESC, id DESC) "
            . "AND v.resultado <> 'Conforme'",
            [(int) $s['id']],
        )->getResultArray();
        $idsVigentes = array_map(static fn ($d) => (int) $d['id'], $vigentes);
        foreach ($observados as $o) {
            if (in_array((int) $o['documento_legajo_id'], $idsVigentes, true)) {
                $pendientes[] = 'Reemplazar: ' . $o['nombre_original'];
            }
        }

        return $pendientes;
    }

    /**
     * Condiciones de una versión con el estado de su línea, en el formato de Elegibilidad.
     *
     * @return array<string, mixed>
     */
    private function versionParaElegibilidad(int $versionId): array
    {
        $v = $this->db->query(
            'SELECT l.estado, v.vigencia_inicio, v.vigencia_fin, v.admite_persona_humana, v.admite_persona_juridica, v.edad_minima, v.edad_maxima, '
            . 'v.antiguedad_minima_meses, v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas '
            . 'FROM dbo.versiones_condiciones v JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id WHERE v.id = ?',
            [$versionId],
        )->getRowArray();
        if ($v === null) {
            throw new ReglaNegocioException('La versión de condiciones no existe.');
        }
        $v['categorias'] = array_column($this->db->table('condiciones_categorias')->select('categoria_ingresos')->where('version_condiciones_id', $versionId)->get()->getResultArray(), 'categoria_ingresos');

        return $v;
    }
}
