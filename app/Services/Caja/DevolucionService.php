<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Adapters\Fabrica;
use App\Adapters\Pasarela\PasarelaInterface;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\Devolucion;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use Throwable;

/**
 * Devolución de saldos a favor (SS-05): CU-77 Autorizar (Gerente), CU-78 Ejecutar (Cajero, con la pasarela), su
 * conciliación (CU-91) y su vencimiento (CU-89). RN-73, RN-77, D-37.
 *
 * La autorización reserva el importe del saldo. Completada consume la reserva; Fallida, Vencida y Anulada la liberan;
 * En ejecución y En conciliación la conservan. La pasarela se consulta siempre fuera de las transacciones.
 */
class DevolucionService extends BaseService
{
    private const OBJETO = 'autorizaciones_devolucion';
    public const ESTADOS = [
        'PendienteEjecucion' => 'Pendiente de ejecución', 'EnEjecucion' => 'En ejecución', 'Completada' => 'Completada', 'EnConciliacion' => 'En conciliación',
        'Fallida' => 'Fallida', 'Vencida' => 'Vencida', 'Anulada' => 'Anulada',
    ];

    private PasarelaInterface $pasarela;

    public function __construct(?ConnectionInterface $db = null, ?PasarelaInterface $pasarela = null)
    {
        parent::__construct($db);
        $this->pasarela = $pasarela ?? Fabrica::pasarela();
    }

    // ------------------------------------------------------------------ CU-77

    /**
     * CU-77 pasos 3 a 9 (A1 a A4).
     *
     * @param array<string, mixed> $datos
     * @return array{id: int, importe: string, vence: string}
     */
    public function autorizar(int $gerenteId, int $saldoId, array $datos): array
    {
        return $this->transaccion(function () use ($gerenteId, $saldoId, $datos): array {
            $s = $this->bloquear('saldos_a_favor', [$saldoId])[0] ?? null;
            if ($s === null) {
                throw new ReglaNegocioException('El saldo a favor no existe.');
            }
            $activa = $this->db->query("SELECT TOP 1 id FROM dbo.autorizaciones_devolucion WHERE saldo_a_favor_id = ? AND estado IN ('PendienteEjecucion', 'EnEjecucion', 'EnConciliacion')", [$saldoId])->getRowArray();
            if ($activa !== null) {
                throw new ReglaNegocioException("El saldo ya tiene la autorización N.º {$activa['id']} en curso: anúlela antes de registrar otra (A4, A5, RN-73).", 'autorizacion:' . $activa['id']);
            }
            $cliente = $this->db->table('clientes')->select('id, cuit_cuil, denominacion')->where('id', (int) $s['cliente_id'])->get()->getRowArray();
            $v = Devolucion::validarAutorizacion($datos, $s, (string) $cliente['cuit_cuil'], $this->hoyNegocio());
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-73');
            }
            $d = $v['datos'];
            $importe = Decimal::of((string) $d['importe']);
            $this->actualizarConVersion('saldos_a_favor', $saldoId, (int) $s['version_fila'], [
                'importe_disponible' => Decimal::of((string) $s['importe_disponible'])->sub($importe)->toFixed(2),
                'importe_reservado' => Decimal::of((string) $s['importe_reservado'])->add($importe)->toFixed(2),
            ]);
            $vence = Time::now('UTC')->addHours(Devolucion::HORAS_VIGENCIA);
            $this->db->table(self::OBJETO)->insert([
                'saldo_a_favor_id' => $saldoId, 'fecha' => $this->ahoraUtc(), 'importe' => $importe->toFixed(2), 'motivo' => $d['motivo'], 'destino' => $d['destino'],
                'cbu_cvu' => $d['cbu_cvu'], 'cuit_titular' => $d['cuit_titular'], 'vence' => $vence->format('Y-m-d H:i:s.vP'), 'estado' => 'PendienteEjecucion',
                'usuario_id' => $gerenteId, 'fecha_solicitud' => $d['fecha_solicitud'], 'medio_solicitud' => $d['medio_solicitud'],
                'entidad_bancaria' => $d['entidad_bancaria'], 'evidencia_titularidad' => $d['evidencia_titularidad'],
            ]);
            $id = (int) $this->db->insertID();
            (new AlertaService($this->db))->generar('Operativa', "Devolución N.º {$id} de " . formato_importe($importe->toFixed(2)) . " a {$cliente['denominacion']}, autorizada y pendiente de ejecución.", 'Ejecutar la devolución antes de las 72 horas (CU-78).', 'Cajero', null, self::OBJETO, $id);
            (new AuditoriaService($this->db))->registrar('saldos-favor.autorizar-devolucion', self::OBJETO, $id, 'Exitoso', $d['motivo'], [
                'saldo' => $saldoId, 'cliente' => (int) $cliente['id'], 'importe' => $importe->toFixed(2), 'destino' => $d['destino'],
                'cuenta' => $d['cbu_cvu'] === null ? null : '…' . substr((string) $d['cbu_cvu'], -4), 'solicitud' => $d['fecha_solicitud'] . ' · ' . $d['medio_solicitud'],
            ]);

            return ['id' => $id, 'importe' => $importe->toFixed(2), 'vence' => $vence->format('Y-m-d H:i:s')];
        });
    }

    /** CU-77 A5: anulación de una autorización cuya ejecución no se inició; libera la reserva. */
    public function anular(int $gerenteId, int $id, int $version, string $motivoTexto): void
    {
        $motivo = Campos::texto($motivoTexto);
        if (($e = Campos::motivo($motivo)) !== null || mb_strlen($motivo) > 500) {
            throw new ValidacionException(['motivo_anulacion' => $e ?? 'El motivo admite hasta 500 caracteres.'], 'RN-73');
        }
        $this->transaccion(function () use ($gerenteId, $id, $version, $motivo): void {
            $a = $this->autorizacionBloqueada($id, $version);
            if ($a['estado'] !== 'PendienteEjecucion' || $this->db->table('ejecuciones_devolucion')->where('autorizacion_devolucion_id', $id)->countAllResults() > 0) {
                throw new ReglaNegocioException('Solo se anula una autorización pendiente cuya ejecución no se inició (A5, RN-73).');
            }
            $this->actualizarConVersion(self::OBJETO, $id, (int) $a['version_fila'], ['estado' => 'Anulada', 'motivo_anulacion' => $motivo, 'fecha_anulacion' => $this->ahoraUtc(), 'usuario_anulacion_id' => $gerenteId]);
            $this->liberar($a);
            (new AlertaService($this->db))->resolver(self::OBJETO, $id, (string) (session('cuenta')['nombre'] ?? 'Gerente'));
            (new AuditoriaService($this->db))->registrar('saldos-favor.anular-devolucion', self::OBJETO, $id, 'Exitoso', $motivo, ['importe' => (string) $a['importe']]);
        });
    }

    // ------------------------------------------------------------------ consultas

    /**
     * CU-78 pasos 1 y 2: autorizaciones por número, cliente, estado o fecha.
     *
     * @param array<string, mixed> $f numero, texto, estado, fecha, cliente_id
     * @return list<array<string, mixed>>
     */
    public function buscar(array $f): array
    {
        $b = $this->db->table('autorizaciones_devolucion a')
            ->select('a.id, a.fecha, a.importe, a.destino, a.vence, a.estado, a.cbu_cvu, cl.denominacion, cl.cuit_cuil')
            ->join('saldos_a_favor s', 's.id = a.saldo_a_favor_id')->join('clientes cl', 'cl.id = s.cliente_id');
        if ((int) ($f['cliente_id'] ?? 0) > 0) {
            $b->where('s.cliente_id', (int) $f['cliente_id']);
        }
        if (ctype_digit((string) ($f['numero'] ?? ''))) {
            $b->where('a.id', (int) $f['numero']);
        }
        if (($f['texto'] ?? '') !== '') {
            $b->groupStart()->like('cl.denominacion', (string) $f['texto'])->orLike('cl.cuit_cuil', preg_replace('/\D/', '', (string) $f['texto']) ?: (string) $f['texto'])->groupEnd();
        }
        $estado = (string) ($f['estado'] ?? 'activas');
        if ($estado === 'activas') {
            $b->whereIn('a.estado', SaldoFavorService::ACTIVAS);
        } elseif (isset(self::ESTADOS[$estado])) {
            $b->where('a.estado', $estado);
        }
        if (($fecha = Campos::fecha($f['fecha'] ?? '')) !== null) {
            $b->where('a.fecha >=', Time::parse($fecha . ' 00:00:00', config('Sigccr')->zonaNegocio)->setTimezone('UTC')->format('Y-m-d H:i:s'))
                ->where('a.fecha <', Time::parse($fecha . ' 00:00:00', config('Sigccr')->zonaNegocio)->addDays(1)->setTimezone('UTC')->format('Y-m-d H:i:s'));
        }

        return array_map(fn ($a) => $a + ['vigente' => $this->vigente((string) $a['vence'])], $b->orderBy('a.vence')->limit(200)->get()->getResultArray());
    }

    /**
     * CU-78 paso 4 y constancia de devolución (paso 10).
     *
     * @return array<string, mixed>
     */
    public function autorizacion(int $id): array
    {
        $a = $this->db->query(
            "SELECT a.*, s.cliente_id, s.transaccion_origen_id, s.importe_original, s.importe_disponible, s.importe_reservado, cl.denominacion, cl.cuit_cuil, "
            . "g.apellido + ', ' + g.nombre AS gerente, ga.apellido + ', ' + ga.nombre AS anulada_por, t.id_externo, t.importe AS importe_transaccion "
            . 'FROM dbo.autorizaciones_devolucion a JOIN dbo.saldos_a_favor s ON s.id = a.saldo_a_favor_id JOIN dbo.clientes cl ON cl.id = s.cliente_id '
            . 'JOIN dbo.usuarios_internos g ON g.id = a.usuario_id LEFT JOIN dbo.usuarios_internos ga ON ga.id = a.usuario_anulacion_id '
            . 'LEFT JOIN dbo.transacciones_pasarela t ON t.id = s.transaccion_origen_id WHERE a.id = ?',
            [$id],
        )->getRowArray();
        if ($a === null) {
            throw new ReglaNegocioException('La autorización de devolución no existe.');
        }
        $a['vigente'] = $this->vigente((string) $a['vence']);
        $a['ejecucion'] = $this->db->query(
            "SELECT e.*, u.apellido + ', ' + u.nombre AS cajero FROM dbo.ejecuciones_devolucion e JOIN dbo.usuarios_internos u ON u.id = e.usuario_id WHERE e.autorizacion_devolucion_id = ?",
            [$id],
        )->getRowArray();

        return $a;
    }

    // ------------------------------------------------------------------ CU-78

    /**
     * CU-78 pasos 4 a 10 (A1 a A6).
     *
     * @param array<string, mixed> $transferencia numero_operacion, fecha, importe, cbu (solo destino Transferencia)
     * @return array{ok: bool, estado: string, mensaje: string}
     */
    public function ejecutar(int $cajeroId, int $id, int $version, array $transferencia = []): array
    {
        $paso = $this->transaccion(function () use ($cajeroId, $id, $version, $transferencia): array {
            $a = $this->autorizacionBloqueada($id, $version);
            if ($a['estado'] !== 'PendienteEjecucion' || $this->db->table('ejecuciones_devolucion')->where('autorizacion_devolucion_id', $id)->countAllResults() > 0) {
                throw new ReglaNegocioException('La autorización fue anulada o ya tiene una ejecución registrada: no se repite la devolución (A3).');
            }
            if (!$this->vigente((string) $a['vence'])) {
                $this->vencer($a);

                return ['fin' => ['ok' => false, 'estado' => 'Vencida', 'mensaje' => 'La autorización superó las 72 horas sin ejecutarse: quedó Vencida y se liberó el importe. Se requiere una nueva autorización (A2).']];
            }
            $identificador = 'SIGCCR-DEV-' . $id;
            if ($a['destino'] === 'Transferencia') {
                $v = Devolucion::validarTransferencia($transferencia, $a, $this->hoyNegocio());
                if ($v['errores'] !== []) {
                    return ['errores' => $v['errores'], 'discrepancia' => $v['discrepancia'], 'datos' => $v['datos']];
                }
                $this->db->table('ejecuciones_devolucion')->insert([
                    'autorizacion_devolucion_id' => $id, 'inicio' => $this->ahoraUtc(), 'identificador_operacion' => $identificador, 'resultado' => 'Confirmada',
                    'fecha_confirmacion' => $this->ahoraUtc(), 'comprobante' => $v['datos']['numero_operacion'], 'usuario_id' => $cajeroId, 'importe' => (string) $a['importe'],
                    'cbu_destino' => $v['datos']['cbu'], 'fecha_transferencia' => $v['datos']['fecha'], 'detalle' => 'Transferencia registrada por el Cajero (A1).',
                ]);
                $this->completar($a);

                return ['fin' => ['ok' => true, 'estado' => 'Completada', 'mensaje' => 'Devolución completada por transferencia: el comprobante es la confirmación verificable (A1).']];
            }
            // Paso 6: desde el inicio, el vencimiento ya no libera la reserva (RN-73)
            $this->db->table('ejecuciones_devolucion')->insert([
                'autorizacion_devolucion_id' => $id, 'inicio' => $this->ahoraUtc(), 'identificador_operacion' => $identificador, 'resultado' => 'Pendiente',
                'usuario_id' => $cajeroId, 'importe' => (string) $a['importe'],
            ]);
            $this->actualizarConVersion(self::OBJETO, $id, (int) $a['version_fila'], ['estado' => 'EnEjecucion']);
            $tx = $this->db->table('transacciones_pasarela')->select('id_externo')->where('id', (int) $a['transaccion_origen_id'])->get()->getRow();

            return ['pasarela' => ['transaccion' => (string) $tx->id_externo, 'importe' => (string) $a['importe'], 'identificador' => $identificador]];
        });
        if (isset($paso['fin'])) {
            return $paso['fin'];
        }
        if (isset($paso['errores'])) {
            if ($paso['discrepancia']) {
                // A6: la transferencia pudo haberse hecho por otro importe o destino; se informa al Gerente
                (new AlertaService($this->db))->generar('Operativa', "Comprobante de la devolución N.º {$id} con importe o cuenta distintos de los autorizados.", 'Revisar la transferencia realizada y regularizarla (CU-78 A6).', 'Gerente', null, self::OBJETO, $id);
                (new AuditoriaService($this->db))->registrar('saldos-favor.ejecutar-devolucion', self::OBJETO, $id, 'Fallido', 'Comprobante no coincidente con la autorización (A6).', $paso['datos']);
            }
            throw new ValidacionException($paso['errores'], 'RN-73');
        }
        $p = $paso['pasarela'];
        try {
            $r = $this->pasarela->devolver($p['transaccion'], $p['importe'], $p['identificador']);
        } catch (Throwable $e) {
            $r = ['estado' => 'sin_respuesta', 'id_devolucion' => null, 'detalle' => mb_substr('Sin respuesta de la pasarela: ' . $e->getMessage(), 0, 500)];
        }

        return $this->transaccion(fn (): array => $this->registrarResultado($id, $r));
    }

    /**
     * CU-91: consulta las devoluciones En ejecución o En conciliación y registra su resultado definitivo.
     *
     * @return list<int>
     */
    public function aConciliar(): array
    {
        return array_map('intval', array_column($this->db->query("SELECT id FROM dbo.autorizaciones_devolucion WHERE estado IN ('EnEjecucion', 'EnConciliacion') ORDER BY fecha")->getResultArray(), 'id'));
    }

    public function conciliar(int $id): string
    {
        $e = $this->db->query("SELECT e.identificador_operacion, e.inicio, a.estado FROM dbo.ejecuciones_devolucion e JOIN dbo.autorizaciones_devolucion a ON a.id = e.autorizacion_devolucion_id WHERE a.id = ?", [$id])->getRowArray();
        if ($e === null || !in_array($e['estado'], ['EnEjecucion', 'EnConciliacion'], true)) {
            return 'sin cambios';
        }
        $r = $this->pasarela->consultarDevolucion((string) $e['identificador_operacion']) ?? ['estado' => 'sin_registro', 'id_devolucion' => null, 'detalle' => 'La pasarela no registra la devolución.'];
        $estado = $this->transaccion(fn (): array => $this->registrarResultado($id, $r))['estado'];
        $demorada = $estado === 'EnConciliacion' && (new DateTimeImmutable((string) $e['inicio']))->getTimestamp() < time() - 48 * 3600;
        if ($demorada && $this->db->table('alertas')->where('objeto_tipo', self::OBJETO)->where('objeto_id', (string) $id)->where('rol_destino', 'Gerente')->where('estado', 'Pendiente')->countAllResults() === 0) {
            (new AlertaService($this->db))->generar('Operativa', "La devolución N.º {$id} lleva más de 48 horas en conciliación.", 'Verificarla con la pasarela (CU-91).', 'Gerente', null, self::OBJETO, $id);
        }

        return $estado;
    }

    // ------------------------------------------------------------------ CU-89

    /** @return list<int> */
    public function autorizacionesVencidas(): array
    {
        return array_map('intval', array_column($this->db->query("SELECT id FROM dbo.autorizaciones_devolucion WHERE estado = 'PendienteEjecucion' AND vence <= SYSDATETIMEOFFSET() ORDER BY vence")->getResultArray(), 'id'));
    }

    /** CU-89 para una autorización: sin ejecución iniciada, vence y libera la reserva (RN-73). */
    public function vencerAutorizacion(int $id): void
    {
        $a = $this->bloquear(self::OBJETO, [$id])[0] ?? null;
        $vencible = $a !== null && $a['estado'] === 'PendienteEjecucion' && !$this->vigente((string) $a['vence']);
        if ($vencible && $this->db->table('ejecuciones_devolucion')->where('autorizacion_devolucion_id', $id)->countAllResults() === 0) {
            $this->vencer($a, 'vencimientos:controlar');
        }
    }

    // ------------------------------------------------------------------ auxiliares

    /**
     * Pasos 8 y 9 (A4, A5) dentro de una transacción.
     *
     * @param array{estado: string, id_devolucion: ?string, detalle: ?string} $r
     * @return array{ok: bool, estado: string, mensaje: string}
     */
    private function registrarResultado(int $id, array $r): array
    {
        $a = $this->bloquear(self::OBJETO, [$id])[0];
        if (!in_array($a['estado'], ['EnEjecucion', 'EnConciliacion'], true)) {
            return ['ok' => $a['estado'] === 'Completada', 'estado' => $a['estado'], 'mensaje' => 'La devolución ya tenía su resultado registrado.'];
        }
        $resultado = Devolucion::resultado($r['estado']);
        $this->db->table('ejecuciones_devolucion')->where('autorizacion_devolucion_id', $id)->update([
            'resultado' => $resultado, 'fecha_confirmacion' => $resultado === 'Confirmada' ? $this->ahoraUtc() : null,
            'comprobante' => $r['id_devolucion'] === null ? null : mb_substr((string) $r['id_devolucion'], 0, 100), 'detalle' => $r['detalle'] === null ? null : mb_substr((string) $r['detalle'], 0, 500),
        ]);
        $auditoria = new AuditoriaService($this->db);
        if ($resultado === 'Confirmada') {
            $this->completar($a);

            return ['ok' => true, 'estado' => 'Completada', 'mensaje' => 'Devolución completada: la pasarela la confirmó con el identificador ' . $r['id_devolucion'] . '.'];
        }
        if ($resultado === 'Rechazada') {
            $this->actualizarConVersion(self::OBJETO, $id, (int) $a['version_fila'], ['estado' => 'Fallida']);
            $this->liberar($a);
            $alertas = new AlertaService($this->db);
            $alertas->resolver(self::OBJETO, $id, 'Devolución fallida');
            $alertas->generar('Operativa', "La pasarela rechazó la devolución N.º {$id}: se liberó el importe reservado.", 'Evaluar una nueva autorización, por ejemplo por transferencia (CU-77, A5).', 'Gerente', null, self::OBJETO, $id);
            $auditoria->registrar('saldos-favor.ejecutar-devolucion', self::OBJETO, $id, 'Fallido', (string) ($r['detalle'] ?? 'Rechazada por la pasarela.'), ['importe' => (string) $a['importe']]);

            return ['ok' => false, 'estado' => 'Fallida', 'mensaje' => 'La pasarela rechazó la devolución: quedó Fallida y se liberó el importe. Un nuevo intento requiere una nueva autorización (A5).'];
        }
        if ($a['estado'] === 'EnEjecucion') {
            $this->actualizarConVersion(self::OBJETO, $id, (int) $a['version_fila'], ['estado' => 'EnConciliacion']);
            $auditoria->registrar('saldos-favor.ejecutar-devolucion', self::OBJETO, $id, 'Exitoso', 'Resultado incierto: queda en conciliación con el importe reservado (A4).', ['respuesta' => $r['estado']]);
        }

        return ['ok' => false, 'estado' => 'EnConciliacion', 'mensaje' => 'La pasarela no informó un resultado definitivo: la devolución quedó En conciliación con el importe reservado. No repita la operación; se resolverá con la conciliación automática (A4).'];
    }

    /** @param array<string, mixed> $a */
    private function completar(array $a): void
    {
        $this->actualizarConVersion(self::OBJETO, (int) $a['id'], (int) $a['version_fila'], ['estado' => 'Completada']);
        $s = $this->bloquear('saldos_a_favor', [(int) $a['saldo_a_favor_id']])[0];
        $this->actualizarConVersion('saldos_a_favor', (int) $s['id'], (int) $s['version_fila'], [
            'importe_reservado' => Decimal::max(Decimal::zero(), Decimal::of((string) $s['importe_reservado'])->sub((string) $a['importe']))->toFixed(2),
        ]);
        (new AlertaService($this->db))->resolver(self::OBJETO, (int) $a['id'], (string) (session('cuenta')['nombre'] ?? 'Sistema'));
        (new AuditoriaService($this->db))->registrar('saldos-favor.ejecutar-devolucion', self::OBJETO, (int) $a['id'], 'Exitoso', 'Devolución completada.', ['importe' => (string) $a['importe'], 'destino' => $a['destino']]);
    }

    /** Fallida, Vencida o Anulada: el importe vuelve a estar disponible (RN-73). */
    private function liberar(array $a): void
    {
        $s = $this->bloquear('saldos_a_favor', [(int) $a['saldo_a_favor_id']])[0];
        $this->actualizarConVersion('saldos_a_favor', (int) $s['id'], (int) $s['version_fila'], [
            'importe_disponible' => Decimal::of((string) $s['importe_disponible'])->add((string) $a['importe'])->toFixed(2),
            'importe_reservado' => Decimal::max(Decimal::zero(), Decimal::of((string) $s['importe_reservado'])->sub((string) $a['importe']))->toFixed(2),
        ]);
    }

    /** @param array<string, mixed> $a */
    private function vencer(array $a, ?string $proceso = null): void
    {
        $this->actualizarConVersion(self::OBJETO, (int) $a['id'], (int) $a['version_fila'], ['estado' => 'Vencida']);
        $this->liberar($a);
        (new AlertaService($this->db))->resolver(self::OBJETO, (int) $a['id'], $proceso === null ? 'Vencimiento de la autorización' : "Proceso {$proceso}");
        (new AuditoriaService($this->db))->registrar('saldos-favor.vencer-devolucion', self::OBJETO, (int) $a['id'], 'Exitoso', 'Superó las 72 horas sin iniciarse la ejecución (RN-73).', ['importe' => (string) $a['importe']], $proceso);
    }

    /** @return array<string, mixed> */
    private function autorizacionBloqueada(int $id, int $version): array
    {
        $a = $this->db->query(
            'SELECT a.*, s.transaccion_origen_id FROM dbo.autorizaciones_devolucion a WITH (UPDLOCK, ROWLOCK) JOIN dbo.saldos_a_favor s ON s.id = a.saldo_a_favor_id WHERE a.id = ?',
            [$id],
        )->getRowArray();
        if ($a === null) {
            throw new ReglaNegocioException('La autorización de devolución no existe.');
        }
        if ((int) $a['version_fila'] !== $version) {
            throw new ReglaNegocioException('La autorización cambió mientras la revisaba: vuelva a consultarla (RN-24).', 'RN-24');
        }

        return $a;
    }

    private function vigente(string $vence): bool
    {
        return (new DateTimeImmutable($vence))->getTimestamp() > time();
    }
}
