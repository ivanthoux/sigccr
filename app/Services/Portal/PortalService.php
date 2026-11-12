<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Exceptions\AccesoAjenoException;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Portal\ReglasPortal;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Services\BaseService;
use App\Services\Caja\CancelacionAnticipadaService;
use App\Services\Caja\CobroService;
use App\Services\Caja\PagoElectronicoService;
use App\Services\Caja\SaldoFavorService;
use App\Services\Cartera\PunitorioService;
use App\Services\Clientes\ClienteService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Solicitudes\SolicitudService;
use Throwable;

/**
 * Consultas y operaciones del portal de autogestión (SS-07): CU-45 a CU-49. El portal no tiene reglas propias: usa
 * las operaciones de los subsistemas que proveen cada información, restringidas al cliente de la sesión (3.7.1).
 * Cada lectura de un objeto comprueba que pertenezca al cliente; si no, se deniega con un mensaje genérico y el
 * intento queda en la auditoría (CU-46 A3, CU-47 A4, CU-48 A3).
 */
class PortalService extends BaseService
{
    /** Créditos que se muestran en el estado de cuenta: los vigentes y los cerrados como antecedente. */
    private const CREDITOS_VIGENTES = ['Activo', 'EnMora'];

    /** CU-45 paso 2: perfil del cliente, sin modificar sus datos ni su acceso. */
    public function perfil(int $clienteId): array
    {
        $c = (new ClienteService($this->db))->obtener($clienteId);
        (new AuditoriaService($this->db))->registrar('portal.consultar-perfil', 'clientes', $clienteId);

        return $c;
    }

    /**
     * CU-46 paso 2 (A1): solicitudes del cliente con su acción pendiente.
     *
     * @return list<array<string, mixed>>
     */
    public function solicitudes(int $clienteId): array
    {
        $filas = (new SolicitudService($this->db))->buscar(['cliente' => (string) $clienteId]);
        foreach ($filas as &$s) {
            $s['etiqueta'] = EstadosSolicitud::ETIQUETAS[$s['estado']] ?? $s['estado'];
            $s['accion'] = ReglasPortal::accionPendiente((string) $s['estado']);
        }
        unset($s);

        return $filas;
    }

    /**
     * CU-46 pasos 3 y 4 (A2, A3): detalle de una solicitud propia, con su evaluación y su plan si se liquidó.
     *
     * @return array<string, mixed>
     */
    public function solicitud(int $clienteId, int $id): array
    {
        $this->propio('solicitudes_credito', $id, $clienteId);
        $s = (new SolicitudService($this->db))->obtener($id);
        $s['etiqueta'] = EstadosSolicitud::ETIQUETAS[$s['estado']] ?? $s['estado'];
        $s['accion'] = ReglasPortal::accionPendiente((string) $s['estado']);
        $s['condicion_final'] = ReglasPortal::condicionFinal((string) $s['estado']);
        $s['faltantes_nombres'] = $s['faltantes'] === [] ? [] : array_column($this->db->table('tipos_documentacion')->select('nombre')->whereIn('codigo', $s['faltantes'])->get()->getResultArray(), 'nombre');
        $s['evaluacion'] = $this->db->query(
            'SELECT TOP 1 modalidad, procesamiento, resultado, fecha_inicio, fecha_finalizacion, vence, vigencia FROM dbo.evaluaciones_crediticias '
            . 'WHERE solicitud_id = ? ORDER BY id DESC',
            [$id],
        )->getRowArray();
        $s['credito'] = $s['liquidacion'] === null ? null : $this->db->query(
            'SELECT TOP 1 c.id, c.numero, c.estado FROM dbo.creditos c JOIN dbo.liquidaciones l ON l.credito_id = c.id WHERE l.solicitud_id = ?',
            [$id],
        )->getRowArray();
        (new AuditoriaService($this->db))->registrar('portal.consultar-solicitud', 'solicitudes_credito', $id);

        return $s;
    }

    /**
     * CU-47 paso 2 (A1, A2): resumen consolidado con la deuda por concepto (punitorios al día de hoy), las cuotas
     * pendientes y vencidas, los próximos vencimientos, el saldo a favor y lo comprometido en operaciones pendientes.
     * La deuda y el saldo a favor se muestran por separado y la consulta no aplica ni devuelve nada (RN-57).
     *
     * @return array<string, mixed>
     */
    public function estadoCuenta(int $clienteId): array
    {
        $obligaciones = (new CobroService($this->db))->obligaciones($clienteId);
        $cuotas = array_merge(...array_map(static fn ($c) => $c['cuotas'], $obligaciones['creditos']) ?: [[]]);
        $creditos = $this->db->query(
            'SELECT c.id, c.numero, c.estado, c.capital, c.cantidad_cuotas, c.fecha_liquidacion, l.nombre AS linea, '
            . "(SELECT COUNT(*) FROM dbo.cuotas q WHERE q.credito_id = c.id AND q.estado IN ('Pendiente', 'Vencida')) AS impagas "
            . 'FROM dbo.creditos c JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . 'WHERE c.cliente_id = ? ORDER BY CASE WHEN c.estado IN (?, ?) THEN 0 ELSE 1 END, c.numero DESC',
            [$clienteId, ...self::CREDITOS_VIGENTES],
        )->getResultArray();
        $saldos = (new SaldoFavorService($this->db))->saldos($clienteId);
        $disponible = '0.00';
        $reservado = '0.00';
        foreach ($saldos as $sf) {
            $disponible = bcadd($disponible, (string) $sf['importe_disponible'], 2);
            $reservado = bcadd($reservado, (string) $sf['importe_reservado'], 2);
        }
        $ordenes = $this->db->query(
            "SELECT id, numero, importe, vence, estado, url_pago FROM dbo.ordenes_pago_electronico WHERE cliente_id = ? AND (estado = 'EnConciliacion' "
            . "OR (estado = 'PendienteConfirmacion' AND vence > SYSDATETIMEOFFSET())) ORDER BY emision",
            [$clienteId],
        )->getResultArray();
        (new AuditoriaService($this->db))->registrar('portal.consultar-estado-cuenta', 'clientes', $clienteId);

        return [
            'creditos' => $creditos,
            'deuda' => ReglasPortal::resumenDeuda($cuotas),
            'vencidas' => array_values(array_filter($cuotas, static fn ($q) => $q['dias_atraso'] > 0)),
            'proximos' => ReglasPortal::proximosVencimientos($cuotas),
            'saldo_disponible' => $disponible,
            'saldo_reservado' => $reservado,
            'ordenes' => $ordenes,
            'morosidad_avanzada' => $obligaciones['morosidad_avanzada'],
        ];
    }

    /**
     * CU-47 pasos 3 y 4 (A4, A7): plan de cuotas de un crédito propio, con los punitorios a la fecha y el importe de
     * la cancelación anticipada a hoy (sin registrar ninguna operación).
     *
     * @return array<string, mixed>
     */
    public function plan(int $clienteId, int $creditoId): array
    {
        $this->propio('creditos', $creditoId, $clienteId);
        $hoy = $this->hoyNegocio();
        $impagas = array_map('intval', array_column($this->db->query("SELECT id FROM dbo.cuotas WHERE credito_id = ? AND estado IN ('Pendiente', 'Vencida')", [$creditoId])->getResultArray(), 'id'));
        $this->transaccion(fn () => (new PunitorioService($this->db))->devengarCuotas($impagas, $hoy));
        $credito = $this->db->query(
            'SELECT c.*, l.nombre AS linea FROM dbo.creditos c JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id '
            . 'JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id WHERE c.id = ?',
            [$creditoId],
        )->getRowArray();
        $cuotas = $this->db->query(
            'SELECT q.*, CASE WHEN EXISTS (SELECT 1 FROM dbo.imputaciones i WHERE i.cuota_id = q.id AND i.parcial = 1) THEN 1 ELSE 0 END AS con_parcial '
            . 'FROM dbo.cuotas q WHERE q.credito_id = ? ORDER BY q.numero',
            [$creditoId],
        )->getResultArray();
        foreach ($cuotas as &$q) {
            $impaga = in_array($q['estado'], ['Pendiente', 'Vencida'], true);
            $q['conceptos'] = CobroCuotas::conceptos($q);
            $q['total'] = $impaga ? CobroCuotas::total($q) : null;
            $q['dias_atraso'] = $impaga ? CobroCuotas::diasAtraso((string) $q['vencimiento'], $hoy) : 0;
        }
        unset($q);
        $cancelacion = null;
        if (in_array($credito['estado'], self::CREDITOS_VIGENTES, true)) {
            try {
                $cancelacion = (new CancelacionAnticipadaService($this->db))->calcular($creditoId)['calculo']['total']->toFixed(2);
            } catch (ReglaNegocioException) {
                $cancelacion = null;
            }
        }
        (new AuditoriaService($this->db))->registrar('portal.consultar-plan', 'creditos', $creditoId);

        return ['credito' => $credito, 'cuotas' => $cuotas, 'cancelacion' => $cancelacion, 'fecha' => $hoy];
    }

    /**
     * CU-47 pasos 5 y 6 (A3): detalle de una cuota con los días de atraso y la tolerancia del crédito.
     *
     * @return array<string, mixed>
     */
    public function cuota(int $clienteId, int $creditoId, int $numero): array
    {
        $plan = $this->plan($clienteId, $creditoId);
        foreach ($plan['cuotas'] as $q) {
            if ((int) $q['numero'] === $numero) {
                $q['situacion'] = ReglasPortal::situacionAtraso((int) $q['dias_atraso'], (int) $plan['credito']['tolerancia_dias']);

                return ['credito' => $plan['credito'], 'cuota' => $q, 'fecha' => $plan['fecha']];
            }
        }
        throw new ReglaNegocioException('La cuota no existe.');
    }

    /**
     * CU-48 paso 2 (A1): cobros del cliente con su comprobante, medio, cuotas alcanzadas y estado.
     *
     * @return list<array<string, mixed>>
     */
    public function comprobantes(int $clienteId): array
    {
        return $this->db->query(
            'SELECT b.id, b.numero, b.fecha_hora, b.importe, b.estado, b.canal, b.tipo, p.numero AS comprobante, '
            . "STUFF((SELECT DISTINCT ', ' + m.medio FROM dbo.cobros_medios m WHERE m.cobro_id = b.id FOR XML PATH('')), 1, 2, '') AS medios, "
            . "STUFF((SELECT ', ' + CAST(x.credito AS VARCHAR(10)) + '/' + CAST(x.cuota AS VARCHAR(5)) FROM (SELECT DISTINCT c.numero AS credito, q.numero AS cuota "
            . 'FROM dbo.imputaciones i JOIN dbo.cuotas q ON q.id = i.cuota_id JOIN dbo.creditos c ON c.id = q.credito_id WHERE i.cobro_id = b.id) x '
            . "ORDER BY x.credito, x.cuota FOR XML PATH('')), 1, 2, '') AS cuotas "
            . 'FROM dbo.cobros b JOIN dbo.comprobantes_pago p ON p.cobro_id = b.id WHERE b.cliente_id = ? ORDER BY b.fecha_hora DESC',
            [$clienteId],
        )->getResultArray();
    }

    /**
     * CU-48 pasos 3 a 7 (A2, A3): comprobante de un cobro propio. Cada consulta, descarga o reemisión se audita y no
     * genera un nuevo cobro (RN-31).
     *
     * @return array<string, mixed>
     */
    public function comprobante(int $clienteId, int $cobroId, bool $reemision): array
    {
        $this->propio('cobros', $cobroId, $clienteId);
        $cobros = new CobroService($this->db);
        $c = $cobros->comprobante($cobroId);
        $cobros->registrarEmision($cobroId, $reemision);

        return $c;
    }

    /**
     * CU-49 paso 2 (A1, A8): cuotas impagas por crédito, con su total actualizado, y las órdenes vigentes.
     *
     * @return array<string, mixed>
     */
    public function obligaciones(int $clienteId): array
    {
        return (new CobroService($this->db))->obligaciones($clienteId);
    }

    /**
     * CU-49 pasos 5 a 10 (A2 a A4, A7, A8). Reutiliza CU-34 con el cliente como solicitante y el portal como medio.
     *
     * @param array<int|string, mixed> $seleccion crédito => números de cuota
     * @return array{id: int, numero: int, importe: string, url_pago: string, vence: string}
     */
    public function generarOrden(int $clienteId, array $seleccion): array
    {
        return (new PagoElectronicoService($this->db))->generarOrden(null, $clienteId, $seleccion, 'Portal');
    }

    /** CU-49 paso 8 (A8): una orden propia con su medio de pago. */
    public function orden(int $clienteId, int $id): array
    {
        $this->propio('ordenes_pago_electronico', $id, $clienteId);

        return (new PagoElectronicoService($this->db))->orden($id, $clienteId);
    }

    /**
     * Comprueba que el objeto pertenezca al cliente de la sesión; si no existe o es de otro cliente, audita el intento
     * y lanza el mismo mensaje genérico en ambos casos, para no revelar si existe.
     */
    private function propio(string $tabla, int $id, int $clienteId): void
    {
        $fila = $this->db->table($tabla)->select('cliente_id')->where('id', $id)->get()->getRowArray();
        if ($fila !== null && (int) $fila['cliente_id'] === $clienteId) {
            return;
        }
        try {
            (new AuditoriaService($this->db))->registrar('portal.acceso-denegado', $tabla, $id, 'Rechazado', $fila === null ? 'Objeto inexistente' : 'Objeto de otro cliente');
        } catch (Throwable $e) {
            log_message('error', 'No se pudo auditar un acceso denegado del portal: ' . $e->getMessage());
        }
        throw new AccesoAjenoException();
    }
}
