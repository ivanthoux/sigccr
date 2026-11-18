<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Libraries\Validacion\Campos;
use App\Adapters\Fabrica;
use App\Adapters\Pasarela\PasarelaInterface;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Caja\PagoElectronico;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Imputador;
use App\Services\BaseService;
use App\Services\Cartera\PunitorioService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use Throwable;

/**
 * Pagos electrónicos (SS-05): CU-34 Generar Orden de Pago Electrónico, CU-38 Consultar Estado de Pago Electrónico,
 * CU-90 Procesar Notificación de Pago Electrónico y CU-91 Conciliar Órdenes de Pago Pendientes.
 * RN-29, RN-30, RN-54 a RN-57, RNF-08, D-35.
 *
 * La consulta a la pasarela se hace siempre fuera de las transacciones. Cada transacción informada se registra una sola
 * vez (clave única de su identificador externo) y el cobro que confirma, también una sola vez.
 */
class PagoElectronicoService extends BaseService
{
    private const IMPAGAS = ['Pendiente', 'Vencida'];
    private const ABIERTAS = ['PendienteConfirmacion', 'EnConciliacion'];

    private PasarelaInterface $pasarela;

    public function __construct(?ConnectionInterface $db = null, ?PasarelaInterface $pasarela = null)
    {
        parent::__construct($db);
        $this->pasarela = $pasarela ?? Fabrica::pasarela();
    }

    /**
     * CU-34 pasos 4 a 11 (A1 a A4, A7). La orden se registra antes de enviarla a la pasarela, para conocer su número;
     * si la pasarela falla, se elimina y el intento queda auditado.
     *
     * @param array<int|string, mixed> $seleccion crédito => números de cuota
     * @return array{id: int, numero: int, importe: string, url_pago: string, vence: string}
     */
    public function generarOrden(?int $usuarioId, int $clienteId, array $seleccion, string $presentado): array
    {
        $seleccion = array_map(static fn ($n) => array_values(array_map([Campos::class, 'identificador'], (array) $n)), array_filter($seleccion, static fn ($n) => $n !== [] && $n !== null));
        $orden = $this->transaccion(function () use ($clienteId, $seleccion, $presentado): array {
            $elegidas = (new CobroService($this->db))->seleccionar($clienteId, $seleccion)['cuotas'];
            // A7: una cuota no integra dos órdenes abiertas y vigentes
            $existente = $this->db->query(
                'SELECT TOP 1 o.id, o.numero FROM dbo.ordenes_pago_electronico o JOIN dbo.ordenes_pago_cuotas oc ON oc.orden_pago_id = o.id '
                . 'WHERE oc.cuota_id IN (' . implode(', ', array_fill(0, count($elegidas), '?')) . ") AND o.estado IN ('PendienteConfirmacion', 'EnConciliacion') AND o.vence > SYSDATETIMEOFFSET()",
                array_map(static fn ($q) => (int) $q['id'], $elegidas),
            )->getRowArray();
            if ($existente !== null) {
                throw new ReglaNegocioException("Alguna cuota seleccionada ya integra la orden N.º {$existente['numero']}, pendiente y vigente: vuelva a compartir su medio de pago en lugar de generar otra (A7).", 'orden:' . $existente['id']);
            }
            $total = Decimal::sum(array_map(static fn ($q) => CobroCuotas::total($q), $elegidas));
            if (!$total->isPositive()) {
                throw new ValidacionException(['seleccion' => 'El importe de la orden debe ser mayor que cero (A3).'], 'RN-54');
            }
            $emision = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $vence = PagoElectronico::vencimiento($emision, config('Sigccr')->zonaNegocio);
            $this->db->table('ordenes_pago_electronico')->insert([
                'cliente_id' => $clienteId, 'importe' => $total->toFixed(2), 'emision' => $emision->format('Y-m-d H:i:s.vP'),
                'vence' => $vence->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.vP'), 'medio_presentado' => $presentado, 'estado' => 'PendienteConfirmacion',
            ]);
            $id = (int) $this->db->insertID();
            foreach ($elegidas as $q) {
                $this->db->table('ordenes_pago_cuotas')->insert(['orden_pago_id' => $id, 'cuota_id' => (int) $q['id'], 'importe' => CobroCuotas::total($q)->toFixed(2)]);
            }
            $numero = (int) $this->db->table('ordenes_pago_electronico')->select('numero')->where('id', $id)->get()->getRow()->numero;

            return ['id' => $id, 'numero' => $numero, 'importe' => $total->toFixed(2), 'vence' => $vence, 'cuotas' => count($elegidas)];
        });

        try {
            $r = $this->pasarela->crearOrden((string) $orden['numero'], $orden['importe'], "SIG-CCR orden N.º {$orden['numero']} ({$orden['cuotas']} cuota/s)", $orden['vence']);
        } catch (Throwable $e) {
            // A1, A4: sin respuesta de la pasarela no hay orden
            $this->transaccion(function () use ($orden): void {
                $this->db->table('ordenes_pago_cuotas')->where('orden_pago_id', $orden['id'])->delete();
                $this->db->table('ordenes_pago_electronico')->where('id', $orden['id'])->delete();
            });
            (new AuditoriaService($this->db))->registrar('pagos.generar-orden', 'clientes', $clienteId, 'Fallido', 'La pasarela no respondió: ' . $e->getMessage(), ['importe' => $orden['importe']]);
            throw new ReglaNegocioException('La pasarela de pagos no está disponible: no se generó la orden. Reintente más tarde o use otro medio de pago (A1, RNF-09).');
        }
        $this->db->table('ordenes_pago_electronico')->where('id', $orden['id'])->update(['referencia_externa' => $r['referencia_externa'], 'url_pago' => $r['url_pago']]);
        (new AuditoriaService($this->db))->registrar('pagos.generar-orden', 'ordenes_pago_electronico', $orden['id'], 'Exitoso', null, [
            'numero' => $orden['numero'], 'cliente' => $clienteId, 'importe' => $orden['importe'], 'usuario' => $usuarioId, 'presentado' => $presentado,
        ]);

        return ['id' => $orden['id'], 'numero' => $orden['numero'], 'importe' => $orden['importe'], 'url_pago' => $r['url_pago'], 'vence' => $orden['vence']->format('Y-m-d H:i:s')];
    }

    /**
     * CU-38 pasos 1 y 2 (A4, A5).
     *
     * @param array<string, mixed> $f numero, texto, estado, cliente_id
     * @return list<array<string, mixed>>
     */
    public function buscar(array $f): array
    {
        $b = $this->db->table('ordenes_pago_electronico o')->select('o.id, o.numero, o.importe, o.emision, o.vence, o.estado, o.medio_presentado, cl.denominacion, cl.cuit_cuil, '
            . '(SELECT TOP 1 b.numero FROM dbo.cobros b JOIN dbo.transacciones_pasarela t ON t.id = b.transaccion_pasarela_id WHERE t.orden_pago_id = o.id) AS cobro', false)
            ->join('clientes cl', 'cl.id = o.cliente_id');
        if ((int) ($f['cliente_id'] ?? 0) > 0) {
            $b->where('o.cliente_id', (int) $f['cliente_id']);
        }
        if (($f['numero'] ?? '') !== '') {
            $b->where('o.numero', (int) $f['numero']);
        }
        if (($f['texto'] ?? '') !== '') {
            $b->groupStart()->like('cl.denominacion', Campos::literalLike((string) $f['texto']))->orLike('cl.cuit_cuil', Campos::literalLike((string) preg_replace('/\D/', '', (string) $f['texto']) ?: (string) $f['texto']))->groupEnd();
        }
        if (in_array($f['estado'] ?? '', ['PendienteConfirmacion', 'Confirmada', 'Rechazada', 'EnConciliacion', 'Vencida'], true)) {
            $b->where('o.estado', $f['estado']);
        }

        return $b->orderBy('o.emision', 'DESC')->limit(200)->get()->getResultArray();
    }

    /**
     * CU-38 paso 4 (A1 a A3): la orden con sus cuotas, transacciones, incidencias y cobro, sin consultar a la pasarela.
     *
     * @return array<string, mixed>
     */
    public function orden(int $id, ?int $soloCliente = null): array
    {
        $o = $this->db->query('SELECT o.*, cl.denominacion, cl.cuit_cuil FROM dbo.ordenes_pago_electronico o JOIN dbo.clientes cl ON cl.id = o.cliente_id WHERE o.id = ?', [$id])->getRowArray();
        if ($o === null || ($soloCliente !== null && (int) $o['cliente_id'] !== $soloCliente)) {
            throw new ReglaNegocioException('La orden no existe (A5).');
        }
        $o['cuotas'] = $this->db->query(
            'SELECT oc.importe, q.numero, q.vencimiento, q.estado, c.numero AS credito FROM dbo.ordenes_pago_cuotas oc JOIN dbo.cuotas q ON q.id = oc.cuota_id '
            . 'JOIN dbo.creditos c ON c.id = q.credito_id WHERE oc.orden_pago_id = ? ORDER BY q.vencimiento, c.numero, q.numero',
            [$id],
        )->getResultArray();
        $o['transacciones'] = $this->db->query(
            'SELECT t.*, b.id AS cobro_id, b.numero AS cobro, b.importe AS importe_cobro FROM dbo.transacciones_pasarela t LEFT JOIN dbo.cobros b ON b.transaccion_pasarela_id = t.id WHERE t.orden_pago_id = ? ORDER BY t.fecha_recepcion',
            [$id],
        )->getResultArray();
        $o['incidencias'] = $this->db->query(
            'SELECT i.*, t.id_externo FROM dbo.incidencias_conciliacion i JOIN dbo.transacciones_pasarela t ON t.id = i.transaccion_pasarela_id WHERE t.orden_pago_id = ? ORDER BY i.fecha',
            [$id],
        )->getResultArray();
        $o['saldos_a_favor'] = $this->db->query(
            'SELECT s.importe_original FROM dbo.saldos_a_favor s JOIN dbo.transacciones_pasarela t ON t.id = s.transaccion_origen_id WHERE t.orden_pago_id = ?',
            [$id],
        )->getResultArray();

        return $o;
    }

    /**
     * CU-90 pasos 1 a 3 (A1): registra la notificación y responde enseguida; el proceso pagos:notificaciones la
     * procesa en segundo plano.
     *
     * @param array<string, string> $cabeceras en minúsculas
     */
    public function recibirNotificacion(string $cuerpo, array $cabeceras): bool
    {
        $valida = $this->pasarela->firmaValida($cuerpo, $cabeceras);
        $datos = json_decode($cuerpo, true);
        $evento = is_array($datos) ? (string) ($datos['id'] ?? '') : '';
        if ($evento !== '' && $this->db->table('notificaciones_pasarela')->where('id_evento', $evento)->countAllResults() > 0) {
            return true;
        }
        $this->db->table('notificaciones_pasarela')->insert([
            'recibida_en' => $this->ahoraUtc(), 'id_evento' => $evento === '' ? null : mb_substr($evento, 0, 100), 'firma_valida' => $valida ? 1 : 0,
            'cuerpo' => mb_substr($cuerpo, 0, 20000), 'estado' => $valida ? 'Pendiente' : 'Descartada', 'intentos' => 0,
            'error' => $valida ? null : 'Firma no válida: incidencia de seguridad (A1).',
        ]);
        if (!$valida) {
            (new AuditoriaService($this->db))->registrar('pagos.notificacion', 'notificaciones_pasarela', (int) $this->db->insertID(), 'Fallido', 'Notificación con firma no válida (RNF-08).');
        }

        return $valida;
    }

    /**
     * Notificaciones válidas pendientes, de la más antigua a la más reciente (CU-90 paso 3).
     *
     * @return list<array<string, mixed>>
     */
    public function notificacionesPendientes(int $limite = 100): array
    {
        return $this->db->table('notificaciones_pasarela')->where('estado', 'Pendiente')->where('firma_valida', 1)->orderBy('recibida_en')->get($limite)->getResultArray();
    }

    /** CU-90 pasos 4 a 10 para una notificación. */
    public function procesarNotificacion(int $id): void
    {
        $n = $this->db->table('notificaciones_pasarela')->where('id', $id)->get()->getRowArray();
        if ($n === null || $n['estado'] !== 'Pendiente') {
            return;
        }
        $datos = json_decode((string) $n['cuerpo'], true);
        $referencia = is_array($datos) ? (string) ($datos['referencia_externa'] ?? ($datos['data']['external_reference'] ?? '')) : '';
        $orden = $referencia === '' ? null : $this->db->table('ordenes_pago_electronico')->select('id')->where('referencia_externa', $referencia)->get()->getRow();
        try {
            if ($orden === null) {
                // A8: no corresponde a ninguna orden registrada
                (new AlertaService($this->db))->generar('Operativa', "Notificación de pago sin orden registrada (referencia «{$referencia}»).", 'Revisar el pago en la pasarela y conciliarlo manualmente (CU-90 A8).', 'Gerente', null, 'notificaciones_pasarela', $id);
                $this->db->table('notificaciones_pasarela')->where('id', $id)->update(['estado' => 'Descartada', 'procesada_en' => $this->ahoraUtc(), 'error' => 'Orden inexistente (A8).', 'intentos' => (int) $n['intentos'] + 1]);

                return;
            }
            $this->procesarOrden((int) $orden->id);
            $this->db->table('notificaciones_pasarela')->where('id', $id)->update(['estado' => 'Procesada', 'procesada_en' => $this->ahoraUtc(), 'intentos' => (int) $n['intentos'] + 1]);
        } catch (Throwable $e) {
            $intentos = (int) $n['intentos'] + 1;
            $this->db->table('notificaciones_pasarela')->where('id', $id)->update(['estado' => $intentos >= 5 ? 'Error' : 'Pendiente', 'intentos' => $intentos, 'error' => mb_substr($e->getMessage(), 0, 500)]);

            throw $e;
        }
    }

    /**
     * CU-89 paso 7: órdenes pendientes de confirmación cuyo límite se alcanzó. Las órdenes en conciliación no vencen; las
     * vencidas se siguen consultando con la pasarela por CU-91 y un pago efectivo anterior al límite todavía se confirma.
     *
     * @return list<int>
     */
    public function ordenesVencidas(): array
    {
        return array_map('intval', array_column($this->db->query(
            "SELECT id FROM dbo.ordenes_pago_electronico WHERE estado = 'PendienteConfirmacion' AND vence <= SYSDATETIMEOFFSET() ORDER BY vence",
        )->getResultArray(), 'id'));
    }

    /** CU-89 para una orden (dentro de la transacción del elemento; A1: si cambió, no se vence). */
    public function vencerOrden(int $id): void
    {
        $o = $this->bloquear('ordenes_pago_electronico', [$id])[0] ?? null;
        if ($o === null || $o['estado'] !== 'PendienteConfirmacion' || (new DateTimeImmutable((string) $o['vence']))->getTimestamp() > time()) {
            return;
        }
        $this->actualizarConVersion('ordenes_pago_electronico', $id, (int) $o['version_fila'], ['estado' => 'Vencida']);
        (new AuditoriaService($this->db))->registrar('pagos.vencer-orden', 'ordenes_pago_electronico', $id, 'Exitoso', 'Límite de la orden alcanzado sin confirmación (RN-54).', ['numero' => (int) $o['numero']], 'vencimientos:controlar');
    }

    /**
     * CU-91 paso 2: órdenes a consultar (pendientes de más de 10 minutos, en conciliación y vencidas el día anterior).
     *
     * @return list<int>
     */
    public function ordenesAConciliar(): array
    {
        return array_map('intval', array_column($this->db->query(
            "SELECT id FROM dbo.ordenes_pago_electronico WHERE (estado = 'PendienteConfirmacion' AND emision <= DATEADD(minute, -?, SYSDATETIMEOFFSET())) "
            . "OR estado = 'EnConciliacion' OR (estado = 'Vencida' AND vence >= DATEADD(day, -2, SYSDATETIMEOFFSET())) ORDER BY emision",
            [PagoElectronico::MINUTOS_CONSULTA],
        )->getResultArray(), 'id'));
    }

    /**
     * CU-90 pasos 4 a 9 y CU-91 pasos 3 a 5 (A1, A2): consulta las transacciones de la orden y procesa cada una; sin
     * transacciones, la orden vencida pasa a Vencida (CU-34 A6).
     *
     * @return string resultado para el resumen de la ejecución
     */
    public function procesarOrden(int $ordenId): string
    {
        $o = $this->db->table('ordenes_pago_electronico')->where('id', $ordenId)->get()->getRowArray();
        if ($o === null || $o['referencia_externa'] === null) {
            return 'sin referencia';
        }
        // Fuera de toda transacción (RNF-08); si la pasarela no responde, la orden se conserva y se reintenta (A1)
        $pagos = $this->pasarela->consultarPagos((string) $o['referencia_externa']);
        $resultados = [];
        foreach ($pagos as $p) {
            $resultados[] = $this->transaccion(fn (): string => $this->procesarPago($ordenId, $p));
        }
        if ($pagos === [] && $o['estado'] === 'PendienteConfirmacion' && Time::now('UTC')->format('Y-m-d H:i:s') >= Time::parse((string) $o['vence'])->setTimezone('UTC')->format('Y-m-d H:i:s')) {
            $this->db->table('ordenes_pago_electronico')->where('id', $ordenId)->where('estado', 'PendienteConfirmacion')->update(['estado' => 'Vencida', 'version_fila' => (int) $o['version_fila'] + 1]);
            $resultados[] = 'vencida';
        }
        $demorada = $o['estado'] === 'EnConciliacion' && Time::parse((string) $o['emision'])->getTimestamp() < time() - PagoElectronico::HORAS_ALERTA_CONCILIACION * 3600;
        if ($demorada && $this->db->table('alertas')->where('objeto_tipo', 'ordenes_pago_electronico')->where('objeto_id', (string) $ordenId)->where('estado', 'Pendiente')->countAllResults() === 0) {
            (new AlertaService($this->db))->generar('Operativa', "La orden N.º {$o['numero']} lleva más de 48 horas en conciliación.", 'Conciliarla manualmente con la pasarela (CU-91 A2).', 'Gerente', null, 'ordenes_pago_electronico', $ordenId);
        }

        return $resultados === [] ? 'sin novedades' : implode(', ', $resultados);
    }

    /**
     * Una transacción de la orden (CU-90 pasos 5 a 9, A2 a A7). Se ejecuta dentro de una transacción.
     *
     * @param array{id_externo: string, estado: string, importe: string, moneda: string, fecha_efectiva: string} $p
     */
    private function procesarPago(int $ordenId, array $p): string
    {
        $o = $this->bloquear('ordenes_pago_electronico', [$ordenId])[0];
        $t = $this->db->query('SELECT * FROM dbo.transacciones_pasarela WITH (UPDLOCK, ROWLOCK) WHERE id_externo = ?', [$p['id_externo']])->getRowArray();
        $clase = PagoElectronico::clasificar($p['estado']);
        if ($t !== null && PagoElectronico::clasificar((string) $t['estado']) !== 'pendiente') {
            return 'repetida';
        }
        $fechaEfectiva = Time::parse($p['fecha_efectiva'])->setTimezone('UTC');
        if ($t === null) {
            $this->db->table('transacciones_pasarela')->insert([
                'orden_pago_id' => $ordenId, 'id_externo' => $p['id_externo'], 'estado' => $p['estado'], 'importe' => Decimal::of($p['importe'])->toFixed(2),
                'moneda' => strtoupper($p['moneda']), 'fecha_efectiva' => $fechaEfectiva->format('Y-m-d H:i:s.vP'), 'fecha_recepcion' => $this->ahoraUtc(),
            ]);
            $txId = (int) $this->db->insertID();
        } else {
            $txId = (int) $t['id'];
            $this->db->table('transacciones_pasarela')->where('id', $txId)->update(['estado' => $p['estado'], 'fecha_efectiva' => $fechaEfectiva->format('Y-m-d H:i:s.vP')]);
        }
        $actualizar = fn (string $estado) => $this->actualizarConVersion('ordenes_pago_electronico', $ordenId, (int) $o['version_fila'], ['estado' => $estado]);

        if ($clase === 'pendiente') {
            // A7: estado no definitivo; no es un pago fallido (RN-30)
            if ($o['estado'] !== 'Confirmada') {
                $actualizar('EnConciliacion');
            }
            $this->incidencia($txId, 'EstadoDesconocido');

            return 'en conciliación';
        }
        $this->resolverIncidencia($txId, 'EstadoDesconocido', 'Estado definitivo informado por la pasarela: ' . $p['estado'] . '.');
        if ($clase === 'rechazado') {
            if (in_array($o['estado'], self::ABIERTAS, true) || $o['estado'] === 'Vencida') {
                $actualizar('Rechazada');
            }
            (new AuditoriaService($this->db))->registrar('pagos.procesar', 'ordenes_pago_electronico', $ordenId, 'Exitoso', 'Transacción rechazada; la deuda se mantiene (A3).', ['transaccion' => $p['id_externo']]);

            return 'rechazada';
        }

        return $this->confirmarPago($o, $txId, $p, $fechaEfectiva);
    }

    /**
     * CU-90 pasos 7 a 9 (A4 a A6): cobro electrónico confirmado una sola vez, con la fecha efectiva informada.
     *
     * @param array<string, mixed> $o orden bloqueada
     * @param array{id_externo: string, estado: string, importe: string, moneda: string, fecha_efectiva: string} $p
     */
    private function confirmarPago(array $o, int $txId, array $p, Time $fechaEfectiva): string
    {
        $ordenId = (int) $o['id'];
        $importe = Decimal::of($p['importe']);
        $vence = new DateTimeImmutable((string) $o['vence']);
        $incidencias = PagoElectronico::incidencias((string) $o['importe'], $importe->toFixed(2), $p['moneda'], $vence, new DateTimeImmutable($fechaEfectiva->format('c')));
        $fechaLocal = (clone $fechaEfectiva)->setTimezone(config('Sigccr')->zonaNegocio)->format('Y-m-d');

        $cuotaIds = array_map('intval', array_column($this->db->table('ordenes_pago_cuotas')->select('cuota_id')->where('orden_pago_id', $ordenId)->get()->getResultArray(), 'cuota_id'));
        $creditoIds = array_values(array_unique(array_map('intval', array_column($this->db->table('cuotas')->select('credito_id')->whereIn('id', $cuotaIds)->get()->getResultArray(), 'credito_id'))));
        $creditos = $this->bloquear('creditos', $creditoIds);
        $impagas = array_map('intval', array_column($this->db->table('cuotas')->select('id')->whereIn('id', $cuotaIds)->whereIn('estado', self::IMPAGAS)->get()->getResultArray(), 'id'));
        // RN-55: punitorios hasta la fecha efectiva del pago; los registrados después se revierten con ajuste trazable
        $hasta = min($fechaLocal, $this->hoyNegocio());
        (new PunitorioService($this->db))->devengarCuotas($impagas, $hasta);
        $ajuste = $this->revertirPunitoriosPosteriores($impagas, $fechaLocal);
        $cuotas = $impagas === [] ? [] : CobroCuotas::ordenar($this->db->query('SELECT * FROM dbo.cuotas WITH (UPDLOCK, ROWLOCK) WHERE id IN (' . implode(', ', array_fill(0, count($impagas), '?')) . ')', $impagas)->getResultArray());

        $alertas = [];
        $cobro = null;
        if ($cuotas === []) {
            // A6: las cuotas se pagaron por otro medio; todo el importe queda como saldo a favor (RN-57)
            $incidencias[] = 'ObligacionesCanceladas';
            $excedente = $importe;
        } else {
            $total = Decimal::sum(array_map(static fn ($q) => CobroCuotas::total($q), $cuotas));
            $aplicar = Decimal::min($importe, $total);
            $excedente = $importe->sub($aplicar);
            $r = Imputador::imputar($aplicar, array_map(static fn ($q) => ['id' => (int) $q['id'], 'conceptos' => CobroCuotas::conceptos($q)], $cuotas));
            $this->db->table('cobros')->insert([
                'cliente_id' => (int) $o['cliente_id'], 'transaccion_pasarela_id' => $txId, 'fecha_hora' => $this->ahoraUtc(), 'fecha_efectiva_pago' => $fechaEfectiva->format('Y-m-d H:i:s.vP'),
                'importe' => $aplicar->toFixed(2), 'tipo' => 'Ordinario', 'canal' => 'Electronico', 'estado' => 'Confirmado', 'cambio' => '0.00',
            ]);
            $cobroId = (int) $this->db->insertID();
            $cobro = (int) $this->db->table('cobros')->select('numero')->where('id', $cobroId)->get()->getRow()->numero;
            $this->db->table('cobros_medios')->insert(['cobro_id' => $cobroId, 'medio' => 'PagoElectronico', 'importe' => $aplicar->toFixed(2), 'importe_recibido' => $aplicar->toFixed(2), 'numero_operacion' => mb_substr($p['id_externo'], 0, 60)]);
            (new CobroService($this->db))->imputar($cobroId, $cuotas, $r['imputaciones'], $creditos);
            $this->db->table('comprobantes_pago')->insert(['cobro_id' => $cobroId, 'fecha_emision' => $this->ahoraUtc(), 'cantidad_reemisiones' => 0]);
        }
        if ($excedente->isPositive()) {
            $this->db->table('saldos_a_favor')->insert([
                'cliente_id' => (int) $o['cliente_id'], 'transaccion_origen_id' => $txId, 'fecha' => $this->ahoraUtc(),
                'importe_original' => $excedente->toFixed(2), 'importe_disponible' => $excedente->toFixed(2), 'importe_reservado' => '0.00',
            ]);
        }
        foreach (array_unique($incidencias) as $tipo) {
            $this->incidencia($txId, $tipo);
            $alertas[] = ['ImporteDistinto' => 'importe distinto al de la orden', 'FueraDePlazo' => 'pago posterior al vencimiento de la orden', 'ObligacionesCanceladas' => 'cuotas ya canceladas por otro medio'][$tipo];
        }
        if ($alertas !== []) {
            (new AlertaService($this->db))->generar('Operativa', "Pago electrónico de la orden N.º {$o['numero']} con incidencias: " . implode('; ', $alertas) . '.', 'Revisar la conciliación de la orden (CU-38, RN-56, RN-57).', 'Gerente', null, 'ordenes_pago_electronico', $ordenId);
        }
        $this->actualizarConVersion('ordenes_pago_electronico', $ordenId, (int) $o['version_fila'], ['estado' => 'Confirmada']);
        (new AuditoriaService($this->db))->registrar('pagos.confirmar', 'ordenes_pago_electronico', $ordenId, 'Exitoso', null, [
            'transaccion' => $p['id_externo'], 'importe' => $importe->toFixed(2), 'cobro' => $cobro, 'excedente' => $excedente->toFixed(2),
            'incidencias' => array_values(array_unique($incidencias)), 'ajuste_punitorios' => $ajuste,
        ]);

        return $cobro === null ? 'saldo a favor' : "cobro {$cobro}";
    }

    /**
     * RN-55: elimina los tramos de punitorios de días posteriores a la fecha efectiva del pago, descuenta su importe de
     * los punitorios pendientes y devuelve el detalle para la auditoría. CU-88 recalcula los días siguientes si queda
     * saldo.
     *
     * @param list<int> $cuotaIds
     * @return list<array{cuota: int, desde: string, importe: string, iva: string}>
     */
    private function revertirPunitoriosPosteriores(array $cuotaIds, string $fecha): array
    {
        $ajustes = [];
        foreach ($cuotaIds as $id) {
            $t = $this->db->query(
                'SELECT MIN(fecha) AS desde, ISNULL(SUM(CASE WHEN exigible = 1 THEN importe ELSE 0 END), 0) AS importe, ISNULL(SUM(CASE WHEN exigible = 1 THEN iva ELSE 0 END), 0) AS iva, COUNT(*) AS n '
                . 'FROM dbo.tramos_punitorios WHERE cuota_id = ? AND fecha > CAST(? AS date)',
                [$id, $fecha],
            )->getRowArray();
            if ((int) $t['n'] === 0) {
                continue;
            }
            $q = $this->db->table('cuotas')->select('punitorio_pendiente, iva_punitorio_pendiente, version_fila')->where('id', $id)->get()->getRowArray();
            $menos = static fn (string $actual, string $resta): string => Decimal::max(Decimal::zero(), Decimal::of($actual)->sub($resta))->toFixed(4);
            $this->db->table('cuotas')->where('id', $id)->update([
                'punitorio_pendiente' => $menos((string) $q['punitorio_pendiente'], (string) $t['importe']),
                'iva_punitorio_pendiente' => $menos((string) $q['iva_punitorio_pendiente'], (string) $t['iva']),
                'version_fila' => (int) $q['version_fila'] + 1,
            ]);
            $this->db->query('DELETE FROM dbo.tramos_punitorios WHERE cuota_id = ? AND fecha > CAST(? AS date)', [$id, $fecha]);
            $ajustes[] = ['cuota' => $id, 'desde' => substr((string) $t['desde'], 0, 10), 'importe' => (string) $t['importe'], 'iva' => (string) $t['iva']];
        }

        return $ajustes;
    }

    private function incidencia(int $txId, string $tipo): void
    {
        if ($this->db->table('incidencias_conciliacion')->where('transaccion_pasarela_id', $txId)->where('tipo', $tipo)->countAllResults() === 0) {
            $this->db->table('incidencias_conciliacion')->insert(['transaccion_pasarela_id' => $txId, 'tipo' => $tipo, 'fecha' => $this->ahoraUtc(), 'estado' => 'Pendiente']);
        }
    }

    private function resolverIncidencia(int $txId, string $tipo, string $resolucion): void
    {
        $this->db->table('incidencias_conciliacion')->where('transaccion_pasarela_id', $txId)->where('tipo', $tipo)->where('estado', 'Pendiente')
            ->update(['estado' => 'Resuelta', 'resolucion' => $resolucion]);
    }
}
