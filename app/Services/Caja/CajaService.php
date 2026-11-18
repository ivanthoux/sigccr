<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Auditoria\Cambios;
use App\Libraries\Caja\ReglasCaja;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Cajas y jornadas (SS-05): CU-80 Registrar Caja, CU-81 Cambiar Estado de Caja, CU-72 Asignar Caja a Cajero,
 * CU-31 Registrar Apertura de Caja y CU-36 Registrar Cierre de Caja. RN-28, RN-32, RN-44, RN-59.
 *
 * Una asignación está vigente desde su inicio hasta su fin inclusive; finalizarla fija el fin en el día anterior
 * (D-21). Los índices únicos de jornadas_caja impiden dos jornadas abiertas por caja o por cajero.
 */
class CajaService extends BaseService
{
    // ── CU-80, CU-81 y CU-72 (Gerente) ─────────────────────────────────────────

    /**
     * Cajas con su asignación vigente o futura y su jornada abierta.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $hoy = $this->hoyNegocio();

        return $this->db->query(
            "SELECT c.*, a.id AS asignacion_id, a.desde, a.hasta, a.usuario_id AS cajero_id, ui.apellido + ', ' + ui.nombre AS cajero, "
            . 'j.id AS jornada_id, j.apertura FROM dbo.cajas c '
            . 'OUTER APPLY (SELECT TOP (1) x.* FROM dbo.asignaciones_caja x WHERE x.caja_id = c.id AND (x.hasta IS NULL OR x.hasta >= CAST(? AS date)) ORDER BY x.desde) a '
            . 'LEFT JOIN dbo.usuarios_internos ui ON ui.id = a.usuario_id '
            . "LEFT JOIN dbo.jornadas_caja j ON j.caja_id = c.id AND j.estado = 'Abierta' ORDER BY c.codigo",
            [$hoy],
        )->getResultArray();
    }

    /** CU-80 pasos 4 a 6 (A1, A2). */
    public function registrar(array $datos): int
    {
        $v = ReglasCaja::validarCaja($datos);
        if ($v['errores'] === [] && $this->db->table('cajas')->where('codigo', $v['datos']['codigo'])->countAllResults() > 0) {
            $v['errores']['codigo'] = 'Ya existe una caja con ese código.';
        }
        if ($v['errores'] !== []) {
            throw new ValidacionException($v['errores']);
        }

        return $this->transaccion(function () use ($v): int {
            $this->db->table('cajas')->insert($v['datos'] + ['estado' => 'Habilitada']);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('cajas.registrar', 'cajas', $id, 'Exitoso', null, $v['datos']);

            return $id;
        });
    }

    /** CU-81 pasos 3 a 7 (A1, A2). */
    public function cambiarEstado(int $cajaId, string $accion, string $motivo): string
    {
        $texto = Campos::texto($motivo);
        // H-18 (D-52): una acción fuera de la lista se rechaza; antes cualquier valor se tomaba como «rehabilitar»
        $errores = array_filter([
            'accion' => in_array(Campos::texto($accion), ['inhabilitar', 'rehabilitar'], true) ? null : 'Seleccione la acción.',
            'motivo' => Campos::motivo($texto),
        ]);
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }
        $accion = Campos::texto($accion);

        return $this->transaccion(function () use ($cajaId, $accion, $texto): string {
            $c = $this->bloquear('cajas', [$cajaId])[0] ?? null;
            if ($c === null) {
                throw new ReglaNegocioException('La caja no existe.');
            }
            $destino = $accion === 'inhabilitar' ? 'Inhabilitada' : 'Habilitada';
            if ($c['estado'] === $destino) {
                throw new ReglaNegocioException("La caja ya está {$destino}.");
            }
            if ($destino === 'Inhabilitada') {
                if ($this->db->table('jornadas_caja')->where('caja_id', $cajaId)->where('estado', 'Abierta')->countAllResults() > 0) {
                    throw new ReglaNegocioException('La caja tiene una jornada abierta: el Cajero debe cerrarla antes de inhabilitarla (CU-36).');
                }
                foreach ($this->db->query('SELECT id FROM dbo.asignaciones_caja WITH (UPDLOCK) WHERE caja_id = ? AND (hasta IS NULL OR hasta >= CAST(? AS date))', [$cajaId, $this->hoyNegocio()])->getResultArray() as $a) {
                    $this->terminarAsignacion((int) $a['id']);
                }
            }
            $this->db->table('cajas')->where('id', $cajaId)->update(['estado' => $destino]);
            (new AuditoriaService($this->db))->registrarCambios('cajas.cambiar_estado', 'cajas', $cajaId, Cambios::campo('estado', $c['estado'], $destino), $texto);

            return $destino;
        });
    }

    /**
     * CU-72 paso 2: Cajeros con su cuenta habilitada.
     *
     * @return list<array{id: int, nombre: string, asignada: ?string}>
     */
    public function cajeros(): array
    {
        return array_map(static fn ($f) => ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'asignada' => $f['asignada']], $this->db->query(
            "SELECT u.id, u.apellido + ', ' + u.nombre AS nombre, (SELECT TOP (1) c.codigo FROM dbo.asignaciones_caja a JOIN dbo.cajas c ON c.id = a.caja_id "
            . 'WHERE a.usuario_id = u.id AND (a.hasta IS NULL OR a.hasta >= CAST(? AS date))) AS asignada '
            . "FROM dbo.usuarios_internos u JOIN dbo.roles r ON r.id = u.rol_id JOIN dbo.cuentas_acceso ca ON ca.id = u.id WHERE r.codigo = 'Cajero' AND ca.estado = 'Habilitada' ORDER BY u.apellido, u.nombre",
            [$this->hoyNegocio()],
        )->getResultArray());
    }

    /** CU-72 pasos 3 a 8 (A1 a A3). */
    public function asignar(int $cajaId, int $cajeroId, string $desdeTexto, string $hastaTexto): int
    {
        $hoy = $this->hoyNegocio();
        $v = ReglasCaja::validarVigencia($desdeTexto, $hastaTexto, $hoy);
        if ($v['errores'] !== []) {
            throw new ValidacionException($v['errores']);
        }

        return $this->transaccion(function () use ($cajaId, $cajeroId, $v, $hoy): int {
            $c = $this->bloquear('cajas', [$cajaId])[0] ?? null;
            if ($c === null || $c['estado'] !== 'Habilitada') {
                throw new ReglaNegocioException('La caja no existe o está inhabilitada (CU-81).');
            }
            $cajero = array_values(array_filter($this->cajeros(), static fn ($x) => $x['id'] === $cajeroId))[0] ?? null;
            if ($cajero === null) {
                throw new ValidacionException(['cajero_id' => 'Seleccione un Cajero con la cuenta habilitada.']);
            }
            // A1: la caja no puede tener otra asignación vigente o futura; A2: el Cajero tampoco
            if ($this->db->query('SELECT 1 FROM dbo.asignaciones_caja WITH (UPDLOCK, HOLDLOCK) WHERE caja_id = ? AND (hasta IS NULL OR hasta >= CAST(? AS date))', [$cajaId, $hoy])->getRowArray() !== null) {
                throw new ReglaNegocioException('La caja ya está asignada: finalice la asignación vigente antes de asignarla a otro Cajero.');
            }
            if ($cajero['asignada'] !== null) {
                throw new ReglaNegocioException("El Cajero ya tiene asignada la caja {$cajero['asignada']}: finalice esa asignación antes de continuar.");
            }
            $this->db->table('asignaciones_caja')->insert(['caja_id' => $cajaId, 'usuario_id' => $cajeroId, 'desde' => $v['desde'], 'hasta' => $v['hasta']]);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('cajas.asignar_cajero', 'cajas', $cajaId, 'Exitoso', null, ['asignacion' => $id, 'cajero' => $cajeroId, 'desde' => $v['desde'], 'hasta' => $v['hasta']]);

            return $id;
        });
    }

    /** CU-72 A5: finaliza la asignación vigente de la caja, si no tiene una jornada abierta. */
    public function finalizarAsignacion(int $cajaId, string $motivo): void
    {
        $texto = Campos::texto($motivo);
        if (($error = Campos::motivo($texto)) !== null) {
            throw new ValidacionException(['motivo_fin' => $error]);
        }
        $this->transaccion(function () use ($cajaId, $texto): void {
            $a = $this->db->query('SELECT id, hasta FROM dbo.asignaciones_caja WITH (UPDLOCK) WHERE caja_id = ? AND (hasta IS NULL OR hasta >= CAST(? AS date))', [$cajaId, $this->hoyNegocio()])->getRowArray();
            if ($a === null) {
                throw new ReglaNegocioException('La caja no tiene una asignación vigente.');
            }
            if ($this->db->table('jornadas_caja')->where('caja_id', $cajaId)->where('estado', 'Abierta')->countAllResults() > 0) {
                throw new ReglaNegocioException('La caja tiene una jornada abierta: el Cajero debe cerrarla antes de finalizar la asignación (CU-36).');
            }
            $this->terminarAsignacion((int) $a['id']);
            $hasta = $this->db->table('asignaciones_caja')->select('hasta')->where('id', (int) $a['id'])->get()->getRow()->hasta;
            (new AuditoriaService($this->db))->registrarCambios('cajas.finalizar_asignacion', 'cajas', $cajaId, Cambios::campo('hasta', $a['hasta'] === null ? null : substr((string) $a['hasta'], 0, 10), substr((string) $hasta, 0, 10)), $texto, ['asignacion' => (int) $a['id']]);
        });
    }

    // ── CU-31 y CU-36 (Cajero) ─────────────────────────────────────────────────

    /**
     * CU-31 paso 2 (A1, A2, A4): asignación vigente y jornada abierta del Cajero.
     *
     * @return array{asignacion: ?array<string, mixed>, jornada: ?array<string, mixed>}
     */
    public function situacion(int $usuarioId): array
    {
        $hoy = $this->hoyNegocio();
        $asignacion = $this->db->query(
            'SELECT a.*, c.codigo, c.denominacion, c.ubicacion, c.estado AS estado_caja FROM dbo.asignaciones_caja a JOIN dbo.cajas c ON c.id = a.caja_id '
            . 'WHERE a.usuario_id = ? AND a.desde <= CAST(? AS date) AND (a.hasta IS NULL OR a.hasta >= CAST(? AS date))',
            [$usuarioId, $hoy, $hoy],
        )->getRowArray();
        $jornada = $this->db->query(
            'SELECT j.*, c.codigo, c.denominacion FROM dbo.jornadas_caja j JOIN dbo.cajas c ON c.id = j.caja_id WHERE j.usuario_id = ? AND j.estado = ?',
            [$usuarioId, 'Abierta'],
        )->getRowArray();
        if ($jornada !== null) {
            $jornada['fecha_local'] = (new DateTimeImmutable((string) $jornada['apertura']))->setTimezone(new DateTimeZone(config('Sigccr')->zonaNegocio))->format('Y-m-d');
            $jornada['anterior'] = $jornada['fecha_local'] < $hoy;
            $jornada['resumen'] = $this->resumen((int) $jornada['id']);
        }

        return ['asignacion' => $asignacion, 'jornada' => $jornada];
    }

    /**
     * Jornada en la que el Cajero puede registrar operaciones: abierta hoy y con la asignación vigente (RN-28). La
     * usan los cobros en ventanilla (CU-33, CU-97).
     *
     * @return ?array<string, mixed>
     */
    public function jornadaOperable(int $usuarioId): ?array
    {
        $s = $this->situacion($usuarioId);
        if ($s['jornada'] === null || $s['asignacion'] === null || $s['jornada']['anterior'] || (int) $s['asignacion']['id'] !== (int) $s['jornada']['asignacion_caja_id']) {
            return null;
        }

        return $s['jornada'];
    }

    /** CU-31 pasos 4 a 7 (A1 a A4). La jornada se abre sin fondo inicial: la caja no maneja efectivo (D-30, RN-77). */
    public function abrir(int $usuarioId): int
    {
        return $this->transaccion(function () use ($usuarioId): int {
            $s = $this->situacion($usuarioId);
            if ($s['jornada'] !== null) {
                throw new ReglaNegocioException('Ya tiene una caja abierta (' . $s['jornada']['codigo'] . '): ciérrela antes de abrir otra (RN-28).');
            }
            if ($s['asignacion'] === null || $s['asignacion']['estado_caja'] !== 'Habilitada') {
                throw new ReglaNegocioException('No tiene una caja asignada y habilitada: solicite la asignación al Gerente (CU-72).');
            }
            $this->db->table('jornadas_caja')->insert([
                'asignacion_caja_id' => (int) $s['asignacion']['id'], 'caja_id' => (int) $s['asignacion']['caja_id'], 'usuario_id' => $usuarioId,
                'apertura' => $this->ahoraUtc(), 'fondo_inicial' => '0.00', 'estado' => 'Abierta',
            ]);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('cajas.abrir', 'jornadas_caja', $id, 'Exitoso', null, ['caja' => $s['asignacion']['codigo']]);

            return $id;
        });
    }

    /**
     * CU-36 pasos 4 a 10 (A1 a A3, A5): conciliación de cada medio de pago y cierre (RN-32, D-30).
     *
     * @param array<string, mixed> $acreditados total acreditado por medio, según el extracto o el procesador
     * @param array<string, mixed> $explicaciones explicación de la diferencia por medio
     * @return list<array{medio: string, total_registrado: string, total_acreditado: string, diferencia: string, explicacion: ?string}>
     */
    public function cerrar(int $usuarioId, int $versionLeida, array $acreditados, array $explicaciones): array
    {
        return $this->transaccion(function () use ($usuarioId, $versionLeida, $acreditados, $explicaciones): array {
            $s = $this->situacion($usuarioId);
            if ($s['jornada'] === null) {
                throw new ReglaNegocioException('No tiene una caja abierta.');
            }
            $j = $this->bloquear('jornadas_caja', [(int) $s['jornada']['id']])[0];
            $resumen = $this->resumen((int) $j['id']);
            $v = ReglasCaja::validarConciliacion($resumen['por_medio'], $acreditados, $explicaciones);
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-32');
            }
            $this->actualizarConVersion('jornadas_caja', (int) $j['id'], $versionLeida, ['cierre' => $this->ahoraUtc(), 'estado' => 'Cerrada']);
            foreach ($v['conciliaciones'] as $c) {
                $this->db->table('conciliaciones_jornada')->insert(['jornada_caja_id' => (int) $j['id']] + $c);
            }
            $diferencias = array_values(array_filter($v['conciliaciones'], static fn ($c) => $c['diferencia'] !== '0.00'));
            (new AuditoriaService($this->db))->registrar('cajas.cerrar', 'jornadas_caja', (int) $j['id'], 'Exitoso', $diferencias === [] ? null : 'Cierre con diferencias en ' . count($diferencias) . ' medio(s).', [
                'cobros' => $resumen['cantidad'], 'total' => $resumen['total'], 'conciliacion' => $v['conciliaciones'],
            ]);

            return $v['conciliaciones'];
        });
    }

    /**
     * Conciliación registrada al cerrar una jornada (CU-32, CU-56).
     *
     * @return list<array<string, mixed>>
     */
    public function conciliacion(int $jornadaId): array
    {
        return $this->db->table('conciliaciones_jornada')->where('jornada_caja_id', $jornadaId)->orderBy('id')->get()->getResultArray();
    }

    /**
     * CU-32 pasos 2 a 4 (A1, A3): jornadas por caja, Cajero, fecha y estado. Un Cajero solo ve las propias.
     *
     * @param array<string, mixed> $f caja_id, cajero_id, fecha (Y-m-d), estado
     * @return list<array<string, mixed>>
     */
    public function jornadas(array $f, ?int $soloCajero): array
    {
        $b = $this->db->table('jornadas_caja j')
            ->select("j.id, j.apertura, j.cierre, j.estado, j.fondo_inicial, j.diferencia, c.codigo, c.denominacion, u.apellido + ', ' + u.nombre AS cajero, "
                . '(SELECT COUNT(*) FROM dbo.conciliaciones_jornada x WHERE x.jornada_caja_id = j.id AND x.diferencia <> 0) AS medios_con_diferencia, '
                . "(SELECT COALESCE(SUM(m.importe), 0) FROM dbo.movimientos_caja m WHERE m.jornada_caja_id = j.id AND m.tipo = 'Cobro') AS total_cobrado", false)
            ->join('cajas c', 'c.id = j.caja_id')->join('usuarios_internos u', 'u.id = j.usuario_id');
        if ($soloCajero !== null) {
            $b->where('j.usuario_id', $soloCajero);
        } elseif ((int) ($f['cajero_id'] ?? 0) > 0) {
            $b->where('j.usuario_id', (int) $f['cajero_id']);
        }
        if ((int) ($f['caja_id'] ?? 0) > 0) {
            $b->where('j.caja_id', (int) $f['caja_id']);
        }
        if (in_array($f['estado'] ?? '', ['Abierta', 'Cerrada'], true)) {
            $b->where('j.estado', $f['estado']);
        }
        if (($f['fecha'] ?? null) !== null) {
            // Fecha local de negocio (UTC−3, sin horario de verano)
            $b->where("CAST(SWITCHOFFSET(j.apertura, '-03:00') AS date) = " . $this->db->escape((string) $f['fecha']), null, false);
        }

        return $b->orderBy('j.apertura', 'DESC')->limit(200)->get()->getResultArray();
    }

    /**
     * CU-32 pasos 5 y 6 (A2, A3): detalle de una jornada con sus movimientos, totales por medio y conciliación.
     *
     * @return array<string, mixed>
     */
    public function jornada(int $id, ?int $soloCajero): array
    {
        $j = $this->db->query(
            "SELECT j.*, c.codigo, c.denominacion, u.apellido + ', ' + u.nombre AS cajero FROM dbo.jornadas_caja j JOIN dbo.cajas c ON c.id = j.caja_id "
            . 'JOIN dbo.usuarios_internos u ON u.id = j.usuario_id WHERE j.id = ?',
            [$id],
        )->getRowArray();
        if ($j === null || ($soloCajero !== null && (int) $j['usuario_id'] !== $soloCajero)) {
            throw new ReglaNegocioException('La jornada no existe o no le corresponde: un Cajero consulta solo sus jornadas (CU-32 A3).');
        }
        $j['resumen'] = $this->resumen($id);
        $j['conciliacion'] = $this->conciliacion($id);
        (new AuditoriaService($this->db))->registrar('cajas.consultar-movimientos', 'jornadas_caja', $id, 'Exitoso');

        return $j;
    }

    /**
     * RN-32: movimientos de la jornada y total registrado por medio.
     *
     * @return array<string, mixed>
     */
    private function resumen(int $jornadaId): array
    {
        $movimientos = $this->db->table('movimientos_caja')->select('id, fecha_hora, tipo, medio, importe, concepto')
            ->where('jornada_caja_id', $jornadaId)->orderBy('fecha_hora')->get()->getResultArray();

        return ReglasCaja::totalesPorMedio(array_map(static fn ($m) => ['tipo' => (string) $m['tipo'], 'medio' => (string) $m['medio'], 'importe' => (string) $m['importe']], $movimientos))
            + ['movimientos' => $movimientos];
    }

    /** Fin de una asignación: el día anterior (o período vacío si todavía no comenzó). */
    private function terminarAsignacion(int $asignacionId): void
    {
        $this->db->query(
            'UPDATE dbo.asignaciones_caja SET hasta = CASE WHEN desde < CAST(? AS date) THEN DATEADD(day, -1, CAST(? AS date)) ELSE DATEADD(day, -1, desde) END WHERE id = ?',
            [$this->hoyNegocio(), $this->hoyNegocio(), $asignacionId],
        );
    }
}
