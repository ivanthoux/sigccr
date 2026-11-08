<?php

declare(strict_types=1);

namespace App\Services\Cartera;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Cartera\DeudaRefinanciable;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Plan;
use App\Libraries\Finanzas\Refinanciacion;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use DomainException;

/**
 * Refinanciación de créditos en mora (SS-06): CU-39 Simular Plan, CU-40 Registrar Propuesta, CU-41 Resolver,
 * CU-42 Localizar, CU-43 Consultar, CU-74 Registrar Aceptación y Aplicar, CU-96 Cancelar y el vencimiento de las
 * propuestas de CU-89. RN-36, RN-37, RN-70, D-36.
 */
class RefinanciacionService extends BaseService
{
    public const VIGENCIA_DIAS = 7;
    public const VIGENTES = ['PendienteResolucion', 'AprobadaPendienteAceptacion'];
    public const ESTADOS = [
        'PendienteResolucion' => 'Pendiente de resolución', 'AprobadaPendienteAceptacion' => 'Aprobada, pendiente de aceptación', 'Observada' => 'Observada',
        'Rechazada' => 'Rechazada', 'Aplicada' => 'Aplicada', 'Vencida' => 'Vencida', 'Cancelada' => 'Cancelada',
    ];
    private const OBJETO = 'propuestas_refinanciacion';

    // ------------------------------------------------------------------ CU-39

    /**
     * CU-39 paso 3 (A1, A5): créditos del cliente con cuotas no pagadas y su composición al día; solo los En mora se
     * admiten (RN-70). Antes se calculan los punitorios hasta hoy (RN-53).
     *
     * @return list<array<string, mixed>>
     */
    public function creditos(int $clienteId): array
    {
        $hoy = $this->hoyNegocio();
        $creditos = $this->db->query("SELECT id, numero, estado, capital, tna FROM dbo.creditos WHERE cliente_id = ? AND estado IN ('Activo', 'EnMora') ORDER BY numero", [$clienteId])->getResultArray();
        if ($creditos === []) {
            return [];
        }
        $ids = array_map(static fn ($c) => (int) $c['id'], $creditos);
        $this->transaccion(fn () => (new PunitorioService($this->db))->devengarCuotas(array_map(static fn ($q) => (int) $q['id'], $this->impagas($this->cuotasDe($ids))), $hoy));
        $porCredito = [];
        foreach ($this->impagas($this->cuotasDe($ids)) as $q) {
            $porCredito[(int) $q['credito_id']][] = $q;
        }
        $vigentes = $this->propuestasVigentesPorCredito($ids);
        $r = [];
        foreach ($creditos as $c) {
            $cuotas = $porCredito[(int) $c['id']] ?? [];
            if ($cuotas === []) {
                continue;
            }
            $vencidas = array_filter($cuotas, static fn ($q) => (string) $q['vencimiento'] < $hoy);
            $suma = static fn (array $filas, string ...$cols): Decimal => Decimal::sum(array_map(static fn ($q) => Decimal::sum(array_map(static fn ($k) => Decimal::of((string) $q[$k]), $cols)), $filas));
            $comp = DeudaRefinanciable::composicion($cuotas, $hoy);
            $r[] = $c + [
                'admitido' => $c['estado'] === 'EnMora',
                'cuotas' => count($cuotas),
                'capital_pendiente' => $comp['capital'],
                'interes_vencido' => $suma($vencidas, 'interes_pendiente'),
                'gastos_iva' => $suma($vencidas, 'gastos_pendiente', 'iva_pendiente'),
                'punitorios' => $comp['punitorio'],
                'no_capitalizable_anterior' => $suma($cuotas, 'no_capitalizable_pendiente'),
                'no_capitalizable' => $comp['no_capitalizable'],
                'total' => $comp['capital']->add($comp['no_capitalizable']),
                'propuesta_vigente' => $vigentes[(int) $c['id']] ?? null,
            ];
        }

        return $r;
    }

    /**
     * CU-39 pasos 4 a 10 (A2, A5).
     *
     * @param list<int|string> $creditoIds
     */
    public function simular(int $usuarioId, int $clienteId, array $creditoIds, string $tasaTexto, string $cuotasTexto): int
    {
        return $this->transaccion(function () use ($usuarioId, $clienteId, $creditoIds, $tasaTexto, $cuotasTexto): int {
            $creditos = $this->creditosAdmitidos($clienteId, $creditoIds);
            $limites = $this->limites();
            [$tasa, $n] = $this->condiciones($tasaTexto, $cuotasTexto, $limites);
            $hoy = $this->hoyNegocio();
            $ids = array_map(static fn ($c) => (int) $c['id'], $creditos);
            (new PunitorioService($this->db))->devengarCuotas(array_map(static fn ($q) => (int) $q['id'], $this->impagas($this->cuotasDe($ids))), $hoy);
            $cuotas = $this->impagas($this->cuotasDe($ids));
            $comp = DeudaRefinanciable::composicion($cuotas, $hoy);
            if (!$comp['capital']->isPositive()) {
                throw new ValidacionException(['creditos' => 'Los créditos seleccionados no tienen capital pendiente para refinanciar (A2).'], 'RN-70');
            }
            $plan = $this->plan($comp['capital'], $comp['no_capitalizable'], $tasa, $n, $hoy);
            $this->db->table('simulaciones_refinanciacion')->insert([
                'cliente_id' => $clienteId, 'fecha' => $this->ahoraUtc(), 'deuda_consolidada' => $comp['capital']->add($comp['no_capitalizable'])->toFixed(2),
                'cantidad_cuotas' => $n, 'tasa' => $tasa->toFixed(12), 'valor_cuota' => $plan->cuotas[0]->total()->toFixed(2), 'vence' => $this->vencimiento(),
                'usuario_id' => $usuarioId, 'fecha_corte' => $hoy, 'capital' => $comp['capital']->toFixed(2), 'no_capitalizable' => $comp['no_capitalizable']->toFixed(2),
                'version_parametros_id' => $limites['id'],
            ]);
            $id = (int) $this->db->insertID();
            foreach ($comp['cuotas'] as $cuotaId => $p) {
                $this->db->table('simulaciones_refinanciacion_cuotas')->insert([
                    'simulacion_id' => $id, 'cuota_id' => $cuotaId, 'capital_pendiente' => $p['capital']->toFixed(2),
                    'no_capitalizable' => $p['no_capitalizable']->toFixed(2), 'punitorio' => $p['punitorio']->toFixed(2),
                ]);
            }
            (new AuditoriaService($this->db))->registrar('refinanciaciones.simular', 'simulaciones_refinanciacion', $id, 'Exitoso', null, [
                'cliente' => $clienteId, 'creditos' => array_map(static fn ($c) => (int) $c['numero'], $creditos), 'capital' => $comp['capital']->toFixed(2),
                'no_capitalizable' => $comp['no_capitalizable']->toFixed(2), 'tasa' => $tasa->toFixed(6), 'cuotas' => $n, 'parametros' => $limites['id'],
            ]);

            return $id;
        });
    }

    /**
     * CU-39 paso 10 y CU-40 pasos 3 y 6: simulación con su plan, sus cuotas y los créditos que quedaron fuera (A5).
     *
     * @return array<string, mixed>
     */
    public function simulacion(int $id): array
    {
        $s = $this->db->query('SELECT s.*, cl.denominacion, cl.cuit_cuil FROM dbo.simulaciones_refinanciacion s JOIN dbo.clientes cl ON cl.id = s.cliente_id WHERE s.id = ?', [$id])->getRowArray();
        if ($s === null) {
            throw new ReglaNegocioException('La simulación no existe.');
        }
        $s['cuotas'] = $this->db->query(
            'SELECT sc.*, q.numero, q.vencimiento, c.numero AS credito, c.id AS credito_id FROM dbo.simulaciones_refinanciacion_cuotas sc JOIN dbo.cuotas q ON q.id = sc.cuota_id '
            . 'JOIN dbo.creditos c ON c.id = q.credito_id WHERE sc.simulacion_id = ? ORDER BY c.numero, q.numero',
            [$id],
        )->getResultArray();
        $incluidos = array_values(array_unique(array_map(static fn ($q) => (int) $q['credito_id'], $s['cuotas'])));
        $s['creditos'] = array_values(array_unique(array_map(static fn ($q) => (int) $q['credito'], $s['cuotas'])));
        $s['excluidos'] = array_map('intval', array_column($this->db->query(
            "SELECT numero FROM dbo.creditos WHERE cliente_id = ? AND estado = 'EnMora'" . ($incluidos === [] ? '' : ' AND id NOT IN (' . implode(', ', $incluidos) . ')') . ' ORDER BY numero',
            [(int) $s['cliente_id']],
        )->getResultArray(), 'numero'));
        $s['plan'] = $this->plan(Decimal::of((string) $s['capital']), Decimal::of((string) $s['no_capitalizable']), Decimal::of((string) $s['tasa']), (int) $s['cantidad_cuotas'], (string) $s['fecha_corte']);
        $s['vigente'] = $this->vigente((string) $s['vence']);
        $s['propuesta'] = $this->db->query('SELECT TOP 1 id, numero, version, estado FROM dbo.propuestas_refinanciacion WHERE simulacion_id = ?', [$id])->getRowArray();

        return $s;
    }

    /**
     * CU-40 paso 3: simulaciones vigentes del cliente sin propuesta registrada.
     *
     * @return list<array<string, mixed>>
     */
    public function simulacionesVigentes(int $clienteId): array
    {
        return $this->db->query(
            'SELECT s.id, s.fecha, s.deuda_consolidada, s.cantidad_cuotas, s.tasa, s.valor_cuota, s.vence FROM dbo.simulaciones_refinanciacion s '
            . 'WHERE s.cliente_id = ? AND s.vence > SYSDATETIMEOFFSET() AND NOT EXISTS (SELECT 1 FROM dbo.propuestas_refinanciacion p WHERE p.simulacion_id = s.id) ORDER BY s.fecha DESC',
            [$clienteId],
        )->getResultArray();
    }

    // ------------------------------------------------------------------ CU-40

    /**
     * CU-40 pasos 5 a 10 (A1 a A3, A5): la propuesta fija la deuda al día de su registro (fecha de corte, RN-70).
     *
     * @return array{id: int, numero: int, version: int}
     */
    public function registrarPropuesta(int $usuarioId, int $simulacionId): array
    {
        return $this->transaccion(function () use ($usuarioId, $simulacionId): array {
            $s = $this->bloquear('simulaciones_refinanciacion', [$simulacionId])[0] ?? null;
            if ($s === null) {
                throw new ReglaNegocioException('La simulación no existe.');
            }
            $previa = $this->db->query('SELECT TOP 1 numero FROM dbo.propuestas_refinanciacion WHERE simulacion_id = ?', [$simulacionId])->getRowArray();
            if ($previa !== null) {
                throw new ReglaNegocioException("Con esta simulación ya se registró la propuesta N.º {$previa['numero']}.");
            }
            if (!$this->vigente((string) $s['vence'])) {
                throw new ReglaNegocioException('La simulación superó su vigencia de 7 días corridos: genere una nueva (CU-39, A1).', 'RN-36');
            }
            $guardadas = $this->composicionGuardada('simulaciones_refinanciacion_cuotas', 'simulacion_id', $simulacionId);
            $creditoIds = $this->creditosDeCuotas(array_keys($guardadas));
            $creditos = $this->bloquear('creditos', $creditoIds);
            $hoy = $this->hoyNegocio();
            (new PunitorioService($this->db))->devengarCuotas(array_map(static fn ($q) => (int) $q['id'], $this->impagas($this->cuotasDe($creditoIds))), $hoy);
            $actuales = $this->cuotasDe($creditoIds);
            $variaciones = DeudaRefinanciable::variaciones($guardadas, $actuales, (string) $s['fecha_corte']);
            foreach ($creditos as $c) {
                if ($c['estado'] !== 'EnMora') {
                    $variaciones[] = "El crédito {$c['numero']} ya no está en mora.";
                }
            }
            if ($variaciones !== []) {
                throw new ReglaNegocioException('La deuda cambió desde la simulación: genere una nueva (A2). ' . implode(' ', $variaciones), 'RN-36');
            }
            $vigentes = $this->propuestasVigentesPorCredito($creditoIds);
            if ($vigentes !== []) {
                $p = reset($vigentes);
                throw new ReglaNegocioException("El cliente ya tiene la propuesta N.º {$p['numero']} vigente sobre alguno de estos créditos: consúltela o cancélela antes de registrar otra (A3).", 'propuesta:' . $p['id']);
            }
            $limites = $this->limites();
            try {
                Refinanciacion::validarCondiciones(Decimal::of((string) $s['tasa']), (int) $s['cantidad_cuotas'], Decimal::of($limites['tasa_minima']), Decimal::of($limites['tasa_maxima']), $limites['cuotas_maximas']);
            } catch (DomainException $e) {
                throw new ReglaNegocioException($e->getMessage() . ' Los límites cambiaron después de la simulación: genere una nueva.', 'RN-70');
            }
            // A5: corrección de una propuesta observada sobre los mismos créditos
            $anterior = $this->observadaSinSucesora($creditoIds);
            $numero = $anterior === null ? (int) $this->db->query('SELECT NEXT VALUE FOR dbo.seq_propuestas AS n')->getRow()->n : (int) $anterior['numero'];
            $version = $anterior === null ? 1 : (int) $anterior['version'] + 1;
            $comp = DeudaRefinanciable::composicion($this->impagas($actuales), $hoy);
            $this->db->table('propuestas_refinanciacion')->insert([
                'numero' => $numero, 'version' => $version, 'cliente_id' => (int) $s['cliente_id'], 'propuesta_anterior_id' => $anterior['id'] ?? null,
                'simulacion_id' => $simulacionId, 'version_parametros_id' => $limites['id'], 'fecha_registro' => $this->ahoraUtc(), 'vence' => $this->vencimiento(),
                'fecha_corte' => $hoy, 'capital_refinanciado' => $comp['capital']->toFixed(2), 'concepto_no_capitalizable' => $comp['no_capitalizable']->toFixed(2),
                'cantidad_cuotas' => (int) $s['cantidad_cuotas'], 'tasa' => (string) $s['tasa'], 'estado' => 'PendienteResolucion', 'usuario_id' => $usuarioId,
            ]);
            $id = (int) $this->db->insertID();
            foreach ($comp['cuotas'] as $cuotaId => $p) {
                $this->db->table('propuestas_cuotas')->insert([
                    'propuesta_id' => $id, 'cuota_id' => $cuotaId, 'capital_pendiente' => $p['capital']->toFixed(2),
                    'no_capitalizable' => $p['no_capitalizable']->toFixed(2), 'punitorio' => $p['punitorio']->toFixed(2),
                ]);
            }
            $alertas = new AlertaService($this->db);
            if ($anterior !== null) {
                $alertas->resolver(self::OBJETO, (int) $anterior['id'], 'Nueva versión de la propuesta');
            }
            $cliente = (string) $this->db->table('clientes')->select('denominacion')->where('id', (int) $s['cliente_id'])->get()->getRow()->denominacion;
            $alertas->generar('Operativa', "Propuesta de refinanciación N.º {$numero}" . ($version > 1 ? " (versión {$version})" : '') . " de {$cliente}, pendiente de resolución.", 'Resolver la propuesta antes de su vencimiento (CU-41).', 'Gerente', null, self::OBJETO, $id);
            (new AuditoriaService($this->db))->registrar('refinanciaciones.registrar-propuesta', self::OBJETO, $id, 'Exitoso', null, [
                'numero' => $numero, 'version' => $version, 'simulacion' => $simulacionId, 'capital' => $comp['capital']->toFixed(2),
                'no_capitalizable' => $comp['no_capitalizable']->toFixed(2), 'anterior' => $anterior['id'] ?? null,
            ]);

            return ['id' => $id, 'numero' => $numero, 'version' => $version];
        });
    }

    // ------------------------------------------------------------------ CU-42 y CU-43

    /**
     * CU-42 pasos 2 a 4 (A1, A2).
     *
     * @param array<string, mixed> $f numero, texto, estado, desde, hasta, cliente_id
     * @return list<array<string, mixed>>
     */
    public function buscar(array $f): array
    {
        $desde = ($f['desde'] ?? '') === '' ? null : Campos::fecha($f['desde']);
        $hasta = ($f['hasta'] ?? '') === '' ? null : Campos::fecha($f['hasta']);
        $errores = [];
        if (($f['desde'] ?? '') !== '' && $desde === null) {
            $errores['desde'] = 'Fecha no válida.';
        }
        if (($f['hasta'] ?? '') !== '' && $hasta === null) {
            $errores['hasta'] = 'Fecha no válida.';
        }
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $errores['hasta'] = 'La fecha final no puede ser anterior a la inicial (A2).';
        }
        if (($f['numero'] ?? '') !== '' && !ctype_digit((string) $f['numero'])) {
            $errores['numero'] = 'Ingrese solo dígitos.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }
        $b = $this->db->table('propuestas_refinanciacion p')
            ->select('p.id, p.numero, p.version, p.fecha_registro, p.vence, p.estado, p.capital_refinanciado, p.concepto_no_capitalizable, p.cantidad_cuotas, p.tasa, cl.denominacion, cl.cuit_cuil')
            ->join('clientes cl', 'cl.id = p.cliente_id');
        if ((int) ($f['cliente_id'] ?? 0) > 0) {
            $b->where('p.cliente_id', (int) $f['cliente_id']);
        }
        if (($f['numero'] ?? '') !== '') {
            $b->where('p.numero', (int) $f['numero']);
        }
        if (($f['texto'] ?? '') !== '') {
            $digitos = preg_replace('/\D/', '', (string) $f['texto']);
            $b->groupStart()->like('cl.denominacion', (string) $f['texto']);
            if ($digitos !== '') {
                $b->orLike('cl.cuit_cuil', $digitos)->orWhere('cl.id IN (SELECT cliente_id FROM dbo.personas_humanas WHERE nro_documento = ' . $this->db->escape($digitos) . ')', null, false);
            }
            $b->groupEnd();
        }
        if (isset(self::ESTADOS[$f['estado'] ?? ''])) {
            $b->where('p.estado', $f['estado']);
        }
        if ($desde !== null) {
            $b->where('p.fecha_registro >=', Time::parse($desde . ' 00:00:00', config('Sigccr')->zonaNegocio)->setTimezone('UTC')->format('Y-m-d H:i:s'));
        }
        if ($hasta !== null) {
            $b->where('p.fecha_registro <', Time::parse($hasta . ' 00:00:00', config('Sigccr')->zonaNegocio)->addDays(1)->setTimezone('UTC')->format('Y-m-d H:i:s'));
        }
        ($f['estado'] ?? '') === 'PendienteResolucion' ? $b->orderBy('p.vence') : $b->orderBy('p.fecha_registro', 'DESC');

        return array_map(fn ($p) => $p + ['vigente' => $this->vigente((string) $p['vence'])], $b->limit(200)->get()->getResultArray());
    }

    /**
     * CU-43 pasos 3 a 5 (A1 a A5). Si la propuesta no se aplicó, el plan es el que resultaría de aplicarla hoy, sin los
     * punitorios posteriores al corte.
     *
     * @return array<string, mixed>
     */
    public function propuesta(int $id, ?int $soloCliente = null): array
    {
        $p = $this->db->query(
            "SELECT p.*, cl.denominacion, cl.cuit_cuil, u.apellido + ', ' + u.nombre AS analista, uc.apellido + ', ' + uc.nombre AS cancelada_por "
            . 'FROM dbo.propuestas_refinanciacion p JOIN dbo.clientes cl ON cl.id = p.cliente_id JOIN dbo.usuarios_internos u ON u.id = p.usuario_id '
            . 'LEFT JOIN dbo.usuarios_internos uc ON uc.id = p.usuario_cancelacion_id WHERE p.id = ?',
            [$id],
        )->getRowArray();
        if ($p === null || ($soloCliente !== null && (int) $p['cliente_id'] !== $soloCliente)) {
            throw new ReglaNegocioException('La propuesta no existe (A6).');
        }
        $p['vigente'] = $this->vigente((string) $p['vence']);
        $p['cuotas'] = $this->db->query(
            'SELECT pc.*, q.numero, q.vencimiento, q.estado AS estado_cuota, q.interes, q.gastos, q.iva, c.numero AS credito, c.estado AS estado_credito '
            . 'FROM dbo.propuestas_cuotas pc JOIN dbo.cuotas q ON q.id = pc.cuota_id JOIN dbo.creditos c ON c.id = q.credito_id WHERE pc.propuesta_id = ? ORDER BY c.numero, q.numero',
            [$id],
        )->getResultArray();
        $p['resoluciones'] = $this->db->query(
            "SELECT r.*, u.apellido + ', ' + u.nombre AS gerente FROM dbo.resoluciones_propuesta r JOIN dbo.usuarios_internos u ON u.id = r.usuario_id WHERE r.propuesta_id = ? ORDER BY r.fecha",
            [$id],
        )->getResultArray();
        $p['versiones'] = $this->db->query('SELECT id, version, estado, fecha_registro FROM dbo.propuestas_refinanciacion WHERE numero = ? ORDER BY version', [(int) $p['numero']])->getResultArray();
        $p['alertas'] = $this->db->table('alertas')->where('objeto_tipo', self::OBJETO)->where('objeto_id', (string) $id)->orderBy('fecha_generacion', 'DESC')->get()->getResultArray();
        $p['aceptacion'] = $this->db->query("SELECT a.*, u.apellido + ', ' + u.nombre AS responsable FROM dbo.aceptaciones_refinanciacion a JOIN dbo.usuarios_internos u ON u.id = a.usuario_id WHERE a.propuesta_id = ?", [$id])->getRowArray();
        $p['observacion'] = $p['estado'] === 'Observada' ? $this->motivoObservacion($p) : null;
        $p['credito'] = $this->db->query('SELECT id, numero, estado, fecha_liquidacion FROM dbo.creditos WHERE propuesta_origen_id = ?', [$id])->getRowArray();
        if ($p['credito'] !== null) {
            $p['plan_aplicado'] = $this->db->query('SELECT * FROM dbo.cuotas WHERE credito_id = ? ORDER BY numero', [(int) $p['credito']['id']])->getResultArray();
            $p['plan'] = null;
        } else {
            $p['plan_aplicado'] = null;
            $p['plan'] = $this->plan(Decimal::of((string) $p['capital_refinanciado']), Decimal::of((string) $p['concepto_no_capitalizable']), Decimal::of((string) $p['tasa']), (int) $p['cantidad_cuotas'], $this->hoyNegocio());
        }

        return $p;
    }

    // ------------------------------------------------------------------ CU-41

    /**
     * CU-41 pasos 5 a 11 (A1 a A5). Si la propuesta venció o la deuda cambió, registra el nuevo estado y lo informa
     * sin aprobar.
     *
     * @return array{ok: bool, estado: string, mensaje: string}
     */
    public function resolver(int $gerenteId, int $id, int $version, string $decision, string $fundamentoTexto): array
    {
        $fundamento = Campos::texto($fundamentoTexto);
        $errores = [];
        if (!in_array($decision, ['Aprobada', 'Rechazada', 'Observada'], true)) {
            $errores['decision'] = 'Seleccione Aprobar, Rechazar u Observar.';
        }
        if (($e = Campos::motivo($fundamento)) !== null) {
            $errores['fundamento'] = $decision === 'Aprobada' ? 'Indique el fundamento de la aprobación (al menos 5 caracteres).' : $e;
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-36');
        }

        return $this->transaccion(function () use ($gerenteId, $id, $version, $decision, $fundamento): array {
            $p = $this->propuestaBloqueada($id, $version);
            if ($p['estado'] !== 'PendienteResolucion') {
                throw new ReglaNegocioException('La propuesta ya fue resuelta o no está pendiente de resolución (' . self::ESTADOS[$p['estado']] . ', A5).');
            }
            $r = $this->revalidar($p);
            if ($r['vencida']) {
                $this->vencer($p);

                return ['ok' => false, 'estado' => 'Vencida', 'mensaje' => 'La propuesta superó su vigencia de 7 días corridos: quedó Vencida y no puede resolverse (A1). Las obligaciones originales no cambiaron.'];
            }
            if ($r['variaciones'] !== []) {
                $motivo = 'La deuda cambió desde el registro de la propuesta: ' . implode(' ', $r['variaciones']);
                $this->db->table('resoluciones_propuesta')->insert(['propuesta_id' => $id, 'decision' => 'Observada', 'fundamento' => mb_substr($motivo, 0, 1000), 'fecha' => $this->ahoraUtc(), 'usuario_id' => $gerenteId]);
                $this->observar($p, $motivo);

                return ['ok' => false, 'estado' => 'Observada', 'mensaje' => 'La deuda cambió desde el registro: la propuesta quedó Observada y no puede aplicarse. Debe registrarse una nueva versión con un nuevo plazo y una nueva resolución (A2).'];
            }
            $this->db->table('resoluciones_propuesta')->insert(['propuesta_id' => $id, 'decision' => $decision, 'fundamento' => $fundamento, 'fecha' => $this->ahoraUtc(), 'usuario_id' => $gerenteId]);
            $alertas = new AlertaService($this->db);
            $alertas->resolver(self::OBJETO, $id, (string) (session('cuenta')['nombre'] ?? 'Gerente'));
            if ($decision === 'Observada') {
                $this->observar($p, $fundamento, false);
                $mensaje = 'La propuesta quedó Observada: su corrección requiere una nueva versión con un nuevo plazo y una nueva resolución (A4).';
            } else {
                $estado = $decision === 'Aprobada' ? 'AprobadaPendienteAceptacion' : 'Rechazada';
                $this->actualizarConVersion(self::OBJETO, $id, (int) $p['version_fila'], ['estado' => $estado]);
                if ($estado === 'AprobadaPendienteAceptacion') {
                    $alertas->generar('Operativa', "La propuesta de refinanciación N.º {$p['numero']} fue aprobada y espera la aceptación del cliente.", 'Registrar la aceptación y aplicar la refinanciación antes de su vencimiento (CU-74).', null, (int) $p['usuario_id'], self::OBJETO, $id);
                }
                $mensaje = $estado === 'AprobadaPendienteAceptacion'
                    ? 'Propuesta aprobada: queda pendiente de la aceptación del cliente, con la vigencia original. Las obligaciones no cambian hasta que se aplique (CU-74).'
                    : 'Propuesta rechazada (A3).';
            }
            (new AuditoriaService($this->db))->registrar('refinanciaciones.resolver', self::OBJETO, $id, 'Exitoso', $fundamento, ['numero' => (int) $p['numero'], 'version' => (int) $p['version'], 'decision' => $decision]);

            return ['ok' => true, 'estado' => $decision === 'Aprobada' ? 'AprobadaPendienteAceptacion' : $decision, 'mensaje' => $mensaje];
        });
    }

    // ------------------------------------------------------------------ CU-74

    /**
     * CU-74 paso 4: revalida la propuesta aprobada y presenta el plan definitivo con los punitorios posteriores al
     * corte (RN-70). No modifica la propuesta.
     *
     * @return array{vencida: bool, variaciones: list<string>, punitorios: Decimal, no_capitalizable: Decimal, plan: ?Plan, ordenes: list<int>}
     */
    public function planDefinitivo(int $id): array
    {
        $p = $this->db->table(self::OBJETO)->where('id', $id)->get()->getRowArray();
        if ($p === null) {
            throw new ReglaNegocioException('La propuesta no existe.');
        }
        // Solo calcula los punitorios hasta hoy (idempotente, RN-53); la propuesta no cambia
        $r = $this->transaccion(fn (): array => $this->revalidar($p));
        $noCap = Decimal::of((string) $p['concepto_no_capitalizable'])->add($r['punitorios']);
        $plan = $r['vencida'] || $r['variaciones'] !== [] ? null : $this->plan(Decimal::of((string) $p['capital_refinanciado']), $noCap, Decimal::of((string) $p['tasa']), (int) $p['cantidad_cuotas'], $this->hoyNegocio());

        return ['vencida' => $r['vencida'], 'variaciones' => $r['variaciones'], 'punitorios' => $r['punitorios'], 'no_capitalizable' => $noCap, 'plan' => $plan, 'ordenes' => $this->ordenesVigentes($p)];
    }

    /**
     * CU-74 pasos 5 a 9 (A1, A2, A4): una sola transacción; ante cualquier error se revierte todo (RN-46).
     *
     * @return array{ok: bool, mensaje: string, credito_id?: int, credito?: int}
     */
    public function aplicar(int $usuarioId, int $id, int $version, string $fechaTexto, string $medioTexto, string $observacionesTexto): array
    {
        $fecha = Campos::fecha($fechaTexto);
        $medio = Campos::texto($medioTexto);
        $observaciones = Campos::texto($observacionesTexto);
        $errores = [];
        if ($fecha === null || $fecha > $this->hoyNegocio()) {
            $errores['fecha_constancia'] = 'Ingrese la fecha de la aceptación del cliente (no posterior a hoy).';
        }
        if ($medio === '' || mb_strlen($medio) > 100) {
            $errores['medio'] = 'Indique el medio de la aceptación (hasta 100 caracteres).';
        }
        if (mb_strlen($observaciones) > 500) {
            $errores['observaciones'] = 'Las observaciones admiten hasta 500 caracteres.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-37');
        }

        return $this->transaccion(function () use ($usuarioId, $id, $version, $fecha, $medio, $observaciones): array {
            $p = $this->propuestaBloqueada($id, $version);
            if ($p['estado'] !== 'AprobadaPendienteAceptacion') {
                throw new ReglaNegocioException('Solo se aplica una propuesta aprobada y pendiente de la aceptación del cliente (estado actual: ' . self::ESTADOS[$p['estado']] . ').');
            }
            if ($fecha < substr(Time::parse((string) $p['fecha_registro'])->setTimezone(config('Sigccr')->zonaNegocio)->format('Y-m-d'), 0, 10)) {
                throw new ValidacionException(['fecha_constancia' => 'La aceptación no puede ser anterior al registro de la propuesta.'], 'RN-37');
            }
            $r = $this->revalidar($p);
            if ($r['vencida']) {
                $this->vencer($p);

                return ['ok' => false, 'mensaje' => 'La propuesta superó su vigencia de 7 días corridos: quedó Vencida y no se aplicó. Las obligaciones originales siguen vigentes (A1).'];
            }
            if ($r['variaciones'] !== []) {
                $this->observar($p, 'La deuda cambió desde el registro de la propuesta: ' . implode(' ', $r['variaciones']));

                return ['ok' => false, 'mensaje' => 'La deuda cambió desde la aprobación: la propuesta quedó Observada y no se aplicó. Debe registrarse una nueva versión (A2). ' . implode(' ', $r['variaciones'])];
            }
            $ordenes = $this->ordenesVigentes($p);
            if ($ordenes !== []) {
                throw new ReglaNegocioException('Hay cuotas comprendidas en la orden de pago electrónico N.º ' . implode(', ', $ordenes) . ', todavía vigente: espere su confirmación o vencimiento antes de aplicar (RN-30).');
            }
            $hoy = $this->hoyNegocio();
            $ahora = $this->ahoraUtc();
            $noCap = Decimal::of((string) $p['concepto_no_capitalizable'])->add($r['punitorios']);
            $capital = Decimal::of((string) $p['capital_refinanciado']);
            $plan = $this->plan($capital, $noCap, Decimal::of((string) $p['tasa']), (int) $p['cantidad_cuotas'], $hoy);
            $morosidad = (new ParametroService($this->db))->vigente('Morosidad');
            $originales = $r['creditos'];
            usort($originales, static fn ($a, $b) => (int) $a['numero'] <=> (int) $b['numero']);

            // Paso 7: nuevo crédito con origen Refinanciación, sin desembolso (RN-37)
            $this->db->table('creditos')->insert([
                'cliente_id' => (int) $p['cliente_id'], 'version_condiciones_id' => (int) $originales[0]['version_condiciones_id'], 'propuesta_origen_id' => $id,
                'version_morosidad_id' => $morosidad['id'], 'origen' => 'Refinanciacion', 'capital' => $capital->toFixed(2), 'tna' => (string) $p['tasa'],
                'cantidad_cuotas' => (int) $p['cantidad_cuotas'], 'fecha_liquidacion' => $ahora, 'tolerancia_dias' => (int) ($morosidad['valores']['tolerancia_dias'] ?? 0), 'estado' => 'Activo',
            ]);
            $creditoId = (int) $this->db->insertID();
            foreach ($plan->cuotas as $q) {
                $this->db->table('cuotas')->insert([
                    'credito_id' => $creditoId, 'numero' => $q->numero, 'vencimiento' => $q->vencimiento->format('Y-m-d'),
                    'capital' => $q->capital->toFixed(2), 'interes' => $q->interes->toFixed(2), 'gastos' => $q->gastos->toFixed(2), 'iva' => $q->iva->toFixed(2),
                    'concepto_no_capitalizable' => $q->noCapitalizable->toFixed(2), 'capital_pendiente' => $q->capital->toFixed(2), 'interes_pendiente' => $q->interes->toFixed(2),
                    'gastos_pendiente' => $q->gastos->toFixed(2), 'iva_pendiente' => $q->iva->toFixed(2), 'no_capitalizable_pendiente' => $q->noCapitalizable->toFixed(2), 'estado' => 'Pendiente',
                ]);
            }
            // Las cuotas originales quedan sustituidas con sus saldos como antecedente: no se pagan ni se eliminan
            $sustituidas = 0;
            foreach ($this->impagas($r['actuales']) as $q) {
                $this->actualizarConVersion('cuotas', (int) $q['id'], (int) $q['version_fila'], ['estado' => 'Sustituida']);
                $sustituidas++;
            }
            foreach ($originales as $c) {
                $this->actualizarConVersion('creditos', (int) $c['id'], (int) $c['version_fila'], ['estado' => 'Refinanciado']);
            }
            $this->db->table('aceptaciones_refinanciacion')->insert([
                'propuesta_id' => $id, 'fecha' => $ahora, 'medio' => $medio, 'observaciones' => $observaciones === '' ? null : $observaciones,
                'usuario_id' => $usuarioId, 'fecha_constancia' => $fecha, 'punitorios_incorporados' => $r['punitorios']->toFixed(2),
            ]);
            $this->actualizarConVersion(self::OBJETO, $id, (int) $p['version_fila'], ['estado' => 'Aplicada']);
            (new AlertaService($this->db))->resolver(self::OBJETO, $id, (string) (session('cuenta')['nombre'] ?? 'Sistema'));
            $numero = (int) $this->db->table('creditos')->select('numero')->where('id', $creditoId)->get()->getRow()->numero;
            (new AuditoriaService($this->db))->registrar('refinanciaciones.aplicar', self::OBJETO, $id, 'Exitoso', null, [
                'numero' => (int) $p['numero'], 'version' => (int) $p['version'], 'credito' => $numero, 'originales' => array_map(static fn ($c) => (int) $c['numero'], $originales),
                'cuotas_sustituidas' => $sustituidas, 'capital' => $capital->toFixed(2), 'no_capitalizable' => $noCap->toFixed(2), 'punitorios_incorporados' => $r['punitorios']->toFixed(2),
                'medio' => $medio, 'fecha_constancia' => $fecha,
            ]);

            return ['ok' => true, 'mensaje' => "Refinanciación aplicada: el crédito N.º {$numero} reemplaza a " . count($originales) . " crédito(s) con {$sustituidas} cuota(s) sustituida(s), sin desembolso.", 'credito_id' => $creditoId, 'credito' => $numero];
        });
    }

    // ------------------------------------------------------------------ CU-96

    /**
     * CU-96 pasos 3 a 7 (A1 a A3): desistimiento del cliente (Analista) o causa administrativa (Gerente).
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function cancelar(int $usuarioId, string $rol, int $id, int $version, string $motivoTexto, string $fechaTexto, string $medioTexto): array
    {
        $tipo = match ($rol) {
            'AnalistaCredito' => 'Desistimiento',
            'Gerente' => 'Administrativa',
            default => throw new ReglaNegocioException('Solo el Analista de Crédito o el Gerente pueden cancelar una propuesta.'),
        };
        $motivo = Campos::texto($motivoTexto);
        $medio = Campos::texto($medioTexto);
        $fecha = $tipo === 'Desistimiento' ? Campos::fecha($fechaTexto) : null;
        $errores = [];
        if (($e = Campos::motivo($motivo)) !== null || mb_strlen($motivo) > 500) {
            $errores['motivo'] = $e ?? 'El motivo admite hasta 500 caracteres.';
        }
        if ($tipo === 'Desistimiento') {
            if ($fecha === null || $fecha > $this->hoyNegocio()) {
                $errores['fecha_desistimiento'] = 'Ingrese la fecha de la constancia de desistimiento (no posterior a hoy).';
            }
            if ($medio === '' || mb_strlen($medio) > 100) {
                $errores['medio_desistimiento'] = 'Indique el medio por el que el cliente desistió (hasta 100 caracteres).';
            }
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-36');
        }

        return $this->transaccion(function () use ($usuarioId, $id, $version, $tipo, $motivo, $fecha, $medio): array {
            $p = $this->propuestaBloqueada($id, $version);
            if (!in_array($p['estado'], [...self::VIGENTES, 'Observada'], true)) {
                throw new ReglaNegocioException('La propuesta está ' . mb_strtolower(self::ESTADOS[$p['estado']]) . ': no puede cancelarse (A1, A3).', 'RN-36');
            }
            if (in_array($p['estado'], self::VIGENTES, true) && !$this->vigente((string) $p['vence'])) {
                $this->vencer($p);

                return ['ok' => false, 'mensaje' => 'La propuesta ya había superado su vigencia: quedó Vencida (A1).'];
            }
            $this->actualizarConVersion(self::OBJETO, $id, (int) $p['version_fila'], [
                'estado' => 'Cancelada', 'motivo_cancelacion' => $motivo, 'tipo_cancelacion' => $tipo, 'fecha_cancelacion' => $this->ahoraUtc(),
                'usuario_cancelacion_id' => $usuarioId, 'fecha_desistimiento' => $fecha, 'medio_desistimiento' => $tipo === 'Desistimiento' ? $medio : null,
            ]);
            (new AlertaService($this->db))->resolver(self::OBJETO, $id, (string) (session('cuenta')['nombre'] ?? 'Sistema'));
            (new AuditoriaService($this->db))->registrar('refinanciaciones.cancelar-propuesta', self::OBJETO, $id, 'Exitoso', $motivo, [
                'numero' => (int) $p['numero'], 'version' => (int) $p['version'], 'tipo' => $tipo, 'estado_anterior' => $p['estado'], 'fecha_desistimiento' => $fecha, 'medio' => $medio,
            ]);

            return ['ok' => true, 'mensaje' => 'Propuesta cancelada por ' . ($tipo === 'Desistimiento' ? 'desistimiento del cliente' : 'causa administrativa') . '. Las obligaciones originales no se modificaron.'];
        });
    }

    // ------------------------------------------------------------------ CU-89 y RN-36

    /**
     * CU-89: propuestas pendientes o aprobadas que superaron su vigencia.
     *
     * @return list<int>
     */
    public function propuestasVencidas(): array
    {
        return array_map('intval', array_column($this->db->query(
            "SELECT id FROM dbo.propuestas_refinanciacion WHERE estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion') AND vence <= SYSDATETIMEOFFSET() ORDER BY vence",
        )->getResultArray(), 'id'));
    }

    /** CU-89 para una propuesta (se ejecuta dentro de la transacción del elemento). */
    public function vencerPropuesta(int $id): void
    {
        $p = $this->bloquear(self::OBJETO, [$id])[0] ?? null;
        if ($p !== null && in_array($p['estado'], self::VIGENTES, true) && !$this->vigente((string) $p['vence'])) {
            $this->vencer($p, 'vencimientos:controlar');
        }
    }

    /**
     * RN-36: un pago, una condonación o la anulación de un cobro sobre cuotas comprendidas deja Observadas las
     * propuestas vigentes. Se llama dentro de la transacción de esa operación.
     *
     * @param list<int> $cuotaIds
     */
    public function observarPorCuotas(array $cuotaIds, string $causa): void
    {
        if ($cuotaIds === []) {
            return;
        }
        $ids = array_map('intval', array_column($this->db->query(
            'SELECT DISTINCT p.id FROM dbo.propuestas_refinanciacion p JOIN dbo.propuestas_cuotas pc ON pc.propuesta_id = p.id '
            . "WHERE p.estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion') AND pc.cuota_id IN (" . implode(', ', array_map('intval', $cuotaIds)) . ')',
        )->getResultArray(), 'id'));
        foreach ($this->bloquear(self::OBJETO, $ids) as $p) {
            if (in_array($p['estado'], self::VIGENTES, true)) {
                $this->observar($p, $causa);
            }
        }
    }

    // ------------------------------------------------------------------ auxiliares

    /**
     * @param array<string, mixed> $p fila bloqueada
     * @return array{vencida: bool, variaciones: list<string>, punitorios: Decimal, actuales: list<array<string, mixed>>, creditos: list<array<string, mixed>>}
     */
    private function revalidar(array $p): array
    {
        $guardadas = $this->composicionGuardada('propuestas_cuotas', 'propuesta_id', (int) $p['id']);
        $creditoIds = $this->creditosDeCuotas(array_keys($guardadas));
        $creditos = $this->bloquear('creditos', $creditoIds);
        $vencida = !$this->vigente((string) $p['vence']);
        if ($vencida) {
            return ['vencida' => true, 'variaciones' => [], 'punitorios' => Decimal::zero(), 'actuales' => [], 'creditos' => $creditos];
        }
        (new PunitorioService($this->db))->devengarCuotas(array_map(static fn ($q) => (int) $q['id'], $this->impagas($this->cuotasDe($creditoIds))), $this->hoyNegocio());
        $actuales = $this->cuotasDe($creditoIds, true);
        $variaciones = DeudaRefinanciable::variaciones($guardadas, $actuales, (string) $p['fecha_corte']);
        foreach ($creditos as $c) {
            if ($c['estado'] !== 'EnMora') {
                $variaciones[] = "El crédito {$c['numero']} ya no está en mora (" . $c['estado'] . ').';
            }
        }

        return [
            'vencida' => false, 'variaciones' => $variaciones, 'actuales' => $actuales, 'creditos' => $creditos,
            'punitorios' => DeudaRefinanciable::punitoriosDesdeCorte($guardadas, $actuales, (string) $p['fecha_corte']),
        ];
    }

    /** @param array<string, mixed> $p */
    private function observar(array $p, string $motivo, bool $registrarCausa = true): void
    {
        $this->actualizarConVersion(self::OBJETO, (int) $p['id'], (int) $p['version_fila'], ['estado' => 'Observada']);
        $alertas = new AlertaService($this->db);
        $alertas->resolver(self::OBJETO, (int) $p['id'], 'Propuesta observada');
        $alertas->generar('Operativa', "La propuesta de refinanciación N.º {$p['numero']} quedó Observada.", 'Registrar una nueva versión con una nueva simulación, nuevo plazo y nueva resolución (CU-39, CU-40).', null, (int) $p['usuario_id'], self::OBJETO, (int) $p['id']);
        if ($registrarCausa) {
            (new AuditoriaService($this->db))->registrar('refinanciaciones.observar', self::OBJETO, (int) $p['id'], 'Exitoso', mb_substr($motivo, 0, 1000), ['numero' => (int) $p['numero'], 'version' => (int) $p['version'], 'estado_anterior' => $p['estado']]);
        }
    }

    /** @param array<string, mixed> $p */
    private function vencer(array $p, ?string $proceso = null): void
    {
        $this->actualizarConVersion(self::OBJETO, (int) $p['id'], (int) $p['version_fila'], ['estado' => 'Vencida']);
        (new AlertaService($this->db))->resolver(self::OBJETO, (int) $p['id'], $proceso === null ? 'Vencimiento de la propuesta' : "Proceso {$proceso}");
        (new AuditoriaService($this->db))->registrar('refinanciaciones.vencer', self::OBJETO, (int) $p['id'], 'Exitoso', 'Superó la vigencia de 7 días corridos sin aplicarse (RN-36).', ['numero' => (int) $p['numero'], 'estado_anterior' => $p['estado']], $proceso);
    }

    /** @param array<string, mixed> $p */
    private function motivoObservacion(array $p): ?string
    {
        $r = $this->db->query(
            "SELECT TOP 1 motivo, fecha_hora FROM (SELECT motivo, fecha_hora FROM dbo.registros_auditoria WHERE accion = 'refinanciaciones.observar' AND objeto_tipo = ? AND objeto_id = ? "
            . "UNION ALL SELECT fundamento, fecha FROM dbo.resoluciones_propuesta WHERE propuesta_id = ? AND decision = 'Observada') x ORDER BY fecha_hora DESC",
            [self::OBJETO, (string) $p['id'], (int) $p['id']],
        )->getRowArray();

        return $r['motivo'] ?? null;
    }

    /** @return array<string, mixed> */
    private function propuestaBloqueada(int $id, int $version): array
    {
        $p = $this->bloquear(self::OBJETO, [$id])[0] ?? null;
        if ($p === null) {
            throw new ReglaNegocioException('La propuesta no existe.');
        }
        if ((int) $p['version_fila'] !== $version) {
            throw new ReglaNegocioException('La propuesta cambió mientras la revisaba (por ejemplo, otro usuario la resolvió o la aplicó). Vuelva a consultarla (RN-24).', 'RN-24');
        }

        return $p;
    }

    /**
     * @param list<int|string> $creditoIds
     * @return list<array<string, mixed>>
     */
    private function creditosAdmitidos(int $clienteId, array $creditoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $creditoIds))));
        $creditos = array_values(array_filter($this->bloquear('creditos', $ids), static fn ($c) => (int) $c['cliente_id'] === $clienteId && $c['estado'] === 'EnMora'));
        if ($creditos === [] || count($creditos) !== count($ids)) {
            throw new ValidacionException(['creditos' => 'Seleccione uno o más créditos en mora del cliente (RN-70).'], 'RN-70');
        }

        return $creditos;
    }

    /**
     * @param array{id: int, tasa_minima: string, tasa_maxima: string, cuotas_maximas: int} $limites
     * @return array{0: Decimal, 1: int}
     */
    private function condiciones(string $tasaTexto, string $cuotasTexto, array $limites): array
    {
        $t = str_replace(',', '.', str_replace([' ', '%'], '', trim($tasaTexto)));
        $n = trim($cuotasTexto);
        $errores = [];
        if (preg_match('/^\d{1,3}(\.\d{1,4})?$/', $t) !== 1) {
            $errores['tasa'] = 'Ingrese la TNA en porcentaje, por ejemplo 48 o 48,5.';
        }
        if (!ctype_digit($n) || (int) $n < 1) {
            $errores['cuotas'] = 'Ingrese una cantidad de cuotas mayor que cero.';
        }
        if ($errores === []) {
            $tasa = Decimal::of($t)->div(100);
            if ($tasa->compare($limites['tasa_minima']) < 0 || $tasa->compare($limites['tasa_maxima']) > 0) {
                $errores['tasa'] = 'La TNA debe estar entre ' . $this->pct($limites['tasa_minima']) . ' y ' . $this->pct($limites['tasa_maxima']) . ' (RN-70, A2).';
            }
            if ((int) $n > $limites['cuotas_maximas']) {
                $errores['cuotas'] = "La cantidad de cuotas no puede superar {$limites['cuotas_maximas']} (RN-70, A2).";
            }
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-70');
        }

        return [Decimal::of($t)->div(100), (int) $n];
    }

    /** @return array{id: int, tasa_minima: string, tasa_maxima: string, cuotas_maximas: int} */
    public function limites(): array
    {
        $v = (new ParametroService($this->db))->vigente('Morosidad');
        $r = (array) ($v['valores']['refinanciacion'] ?? []);

        return ['id' => $v['id'], 'tasa_minima' => (string) ($r['tasa_minima'] ?? '0.30'), 'tasa_maxima' => (string) ($r['tasa_maxima'] ?? '0.80'), 'cuotas_maximas' => (int) ($r['cuotas_maximas'] ?? 36)];
    }

    private function plan(Decimal $capital, Decimal $noCapitalizable, Decimal $tasa, int $cuotas, string $fechaBase): Plan
    {
        $iva = Decimal::of((string) (new ParametroService($this->db))->vigente('Estructuracion')['valores']['alicuota_iva']);

        return Refinanciacion::plan($capital, $noCapitalizable, $tasa, $cuotas, new DateTimeImmutable(substr($fechaBase, 0, 10)), $iva);
    }

    /**
     * @param list<int> $creditoIds
     * @return list<array<string, mixed>>
     */
    private function cuotasDe(array $creditoIds, bool $bloquear = false): array
    {
        if ($creditoIds === []) {
            return [];
        }

        return $this->db->query(
            'SELECT q.*, c.numero AS credito_numero FROM dbo.cuotas q' . ($bloquear ? ' WITH (UPDLOCK, ROWLOCK)' : '') . ' JOIN dbo.creditos c ON c.id = q.credito_id '
            . 'WHERE q.credito_id IN (' . implode(', ', array_map('intval', $creditoIds)) . ') ORDER BY c.numero, q.numero',
        )->getResultArray();
    }

    /**
     * @param list<array<string, mixed>> $cuotas
     * @return list<array<string, mixed>>
     */
    private function impagas(array $cuotas): array
    {
        return array_values(array_filter($cuotas, static fn ($q) => in_array($q['estado'], DeudaRefinanciable::IMPAGAS, true)));
    }

    /** @return array<int, array{capital_pendiente: string, no_capitalizable: string, punitorio: string}> */
    private function composicionGuardada(string $tabla, string $columna, int $id): array
    {
        $r = [];
        foreach ($this->db->table($tabla)->where($columna, $id)->get()->getResultArray() as $f) {
            $r[(int) $f['cuota_id']] = ['capital_pendiente' => (string) $f['capital_pendiente'], 'no_capitalizable' => (string) $f['no_capitalizable'], 'punitorio' => (string) $f['punitorio']];
        }

        return $r;
    }

    /**
     * @param list<int> $cuotaIds
     * @return list<int>
     */
    private function creditosDeCuotas(array $cuotaIds): array
    {
        if ($cuotaIds === []) {
            return [];
        }

        return array_map('intval', array_column($this->db->query('SELECT DISTINCT credito_id FROM dbo.cuotas WHERE id IN (' . implode(', ', array_map('intval', $cuotaIds)) . ')')->getResultArray(), 'credito_id'));
    }

    /**
     * Propuestas vigentes (pendientes o aprobadas y no vencidas) que comprenden cuotas de los créditos (CU-40 A3).
     *
     * @param list<int> $creditoIds
     * @return array<int, array{id: int, numero: int, estado: string}> por crédito
     */
    private function propuestasVigentesPorCredito(array $creditoIds): array
    {
        if ($creditoIds === []) {
            return [];
        }
        $r = [];
        $filas = $this->db->query(
            'SELECT DISTINCT q.credito_id, p.id, p.numero, p.estado FROM dbo.propuestas_refinanciacion p JOIN dbo.propuestas_cuotas pc ON pc.propuesta_id = p.id JOIN dbo.cuotas q ON q.id = pc.cuota_id '
            . "WHERE p.estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion') AND p.vence > SYSDATETIMEOFFSET() AND q.credito_id IN (" . implode(', ', array_map('intval', $creditoIds)) . ')',
        )->getResultArray();
        foreach ($filas as $f) {
            $r[(int) $f['credito_id']] = ['id' => (int) $f['id'], 'numero' => (int) $f['numero'], 'estado' => (string) $f['estado']];
        }

        return $r;
    }

    /**
     * Última propuesta Observada sin nueva versión que comprende cuotas de los créditos (CU-40 A5).
     *
     * @param list<int> $creditoIds
     * @return array<string, mixed>|null
     */
    private function observadaSinSucesora(array $creditoIds): ?array
    {
        return $this->db->query(
            'SELECT TOP 1 p.id, p.numero, p.version FROM dbo.propuestas_refinanciacion p '
            . "WHERE p.estado = 'Observada' AND NOT EXISTS (SELECT 1 FROM dbo.propuestas_refinanciacion s WHERE s.propuesta_anterior_id = p.id) "
            . 'AND EXISTS (SELECT 1 FROM dbo.propuestas_cuotas pc JOIN dbo.cuotas q ON q.id = pc.cuota_id WHERE pc.propuesta_id = p.id AND q.credito_id IN (' . implode(', ', array_map('intval', $creditoIds)) . ')) '
            . 'ORDER BY p.fecha_registro DESC',
        )->getRowArray();
    }

    /**
     * Órdenes de pago electrónico vigentes que incluyen cuotas de la propuesta.
     *
     * @param array<string, mixed> $p
     * @return list<int>
     */
    private function ordenesVigentes(array $p): array
    {
        return array_map('intval', array_column($this->db->query(
            'SELECT DISTINCT o.numero FROM dbo.ordenes_pago_electronico o JOIN dbo.ordenes_pago_cuotas oc ON oc.orden_pago_id = o.id JOIN dbo.propuestas_cuotas pc ON pc.cuota_id = oc.cuota_id '
            . "WHERE pc.propuesta_id = ? AND o.estado IN ('PendienteConfirmacion', 'EnConciliacion') AND o.vence > SYSDATETIMEOFFSET()",
            [(int) $p['id']],
        )->getResultArray(), 'numero'));
    }

    private function vencimiento(): string
    {
        return Time::now('UTC')->addDays(self::VIGENCIA_DIAS)->format('Y-m-d H:i:s.vP');
    }

    private function vigente(string $vence): bool
    {
        return (new DateTimeImmutable($vence))->getTimestamp() > time();
    }

    private function pct(string $tasa): string
    {
        return rtrim(rtrim(number_format((float) $tasa * 100, 2, ',', '.'), '0'), ',') . ' %';
    }
}
