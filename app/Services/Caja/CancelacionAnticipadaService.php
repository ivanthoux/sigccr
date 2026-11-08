<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Caja\MediosCobro;
use App\Libraries\Finanzas\CancelacionAnticipada;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Cartera\PunitorioService;
use App\Services\Cartera\RefinanciacionService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;

/** CU-97 Registrar Cancelación Anticipada de Crédito (Cajero). RN-29, RN-36, RN-67, RN-77, D-38. */
class CancelacionAnticipadaService extends BaseService
{
    private const ADMITIDOS = ['Activo', 'EnMora'];

    /**
     * CU-97 paso 2 (A2): créditos activos o en mora del cliente.
     *
     * @return list<array<string, mixed>>
     */
    public function creditos(int $clienteId): array
    {
        return $this->db->query(
            'SELECT c.id, c.numero, c.estado, c.capital, c.cantidad_cuotas, c.fecha_liquidacion, c.origen, l.nombre AS linea, '
            . "(SELECT COUNT(*) FROM dbo.cuotas q WHERE q.credito_id = c.id AND q.estado IN ('Pendiente', 'Vencida')) AS cuotas_pendientes "
            . 'FROM dbo.creditos c JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . "WHERE c.cliente_id = ? AND c.estado IN ('Activo', 'EnMora') ORDER BY c.numero",
            [$clienteId],
        )->getResultArray();
    }

    /**
     * CU-97 paso 3: importe de la cancelación a hoy, con su detalle y las órdenes electrónicas pendientes (A4).
     *
     * @return array<string, mixed>
     */
    public function calcular(int $creditoId): array
    {
        return $this->transaccion(function () use ($creditoId): array {
            $c = $this->creditoAdmitido($creditoId);

            return $this->detalle($c) + ['credito' => $c];
        });
    }

    /**
     * CU-97 pasos 4 a 8 (A1, A3, A4, A6).
     *
     * @return array{cobro_id: int, numero: int, importe: string}
     */
    public function registrar(int $cajeroId, int $creditoId, string $importeTexto, string $medio, string $numeroOperacion): array
    {
        return $this->transaccion(function () use ($cajeroId, $creditoId, $importeTexto, $medio, $numeroOperacion): array {
            $jornada = (new CajaService($this->db))->jornadaOperable($cajeroId);
            if ($jornada === null) {
                throw new ReglaNegocioException('No tiene una caja abierta hoy: ábrala antes de registrar la cancelación (CU-31, A1).');
            }
            $c = $this->creditoAdmitido($creditoId);
            $d = $this->detalle($c);
            if ($d['ordenes'] !== []) {
                throw new ReglaNegocioException('Hay cuotas del crédito en la orden de pago electrónico N.º ' . implode(', ', $d['ordenes']) . ', pendiente o en conciliación: espere su confirmación o vencimiento (A4, RN-30).', 'RN-30');
            }
            $total = $d['calculo']['total'];
            $importe = Campos::importe($importeTexto);
            if ($importe === null || $total->compare($importe) !== 0) {
                throw new ValidacionException(['importe' => 'El importe debe ser igual al total de la cancelación: ' . formato_importe($total->toFixed(2)) . '. No se admite la cancelación parcial (A3, RN-67).'], 'RN-67');
            }
            $m = MediosCobro::componer([['medio' => $medio, 'importe' => $total->toFixed(2), 'numero_operacion' => $numeroOperacion]], $total);
            if ($m['errores'] !== []) {
                throw new ValidacionException(['medios' => implode(' ', $m['errores'])], 'RN-77');
            }
            $cobros = new CobroService($this->db);
            ['cobro_id' => $cobroId, 'numero' => $numero] = $cobros->asentarVentanilla($jornada, $cajeroId, (int) $c['cliente_id'], $total, $m['medios'], 'CancelacionAnticipada');
            foreach ($d['distribucion']['imputaciones'] as $i) {
                $this->db->table('imputaciones')->insert(['cobro_id' => $cobroId, 'cuota_id' => $i['cuota'], 'concepto' => $i['concepto'], 'importe' => $i['importe']->toFixed(2), 'parcial' => 0]);
            }
            // Paso 6: todas las cuotas quedan Pagadas; lo no devengado se reduce sin cobrarse (RN-67)
            foreach ($d['cuotas'] as $q) {
                $this->actualizarConVersion('cuotas', (int) $q['id'], (int) $q['version_fila'], [
                    'estado' => 'Pagada', 'capital_pendiente' => '0.00', 'interes_pendiente' => '0.00', 'gastos_pendiente' => '0.00', 'iva_pendiente' => '0.00',
                    'no_capitalizable_pendiente' => '0.00', 'punitorio_pendiente' => '0.0000', 'iva_punitorio_pendiente' => '0.0000',
                ]);
            }
            $this->actualizarConVersion('creditos', $creditoId, (int) $c['version_fila'], ['estado' => 'Cancelado']);
            $k = $d['calculo'];
            $r = $d['distribucion'];
            $this->db->table('cancelaciones_anticipadas')->insert([
                'credito_id' => $creditoId, 'cobro_id' => $cobroId, 'fecha_pago' => $d['fecha'], 'vencidas' => $k['vencidas']->toFixed(2),
                'capital_no_vencido' => $k['capital_no_vencido']->toFixed(2), 'no_capitalizable_no_vencido' => $k['no_capitalizable_no_vencido']->toFixed(2),
                'interes_en_curso' => $k['interes_en_curso']->toFixed(2), 'iva_interes_en_curso' => $k['iva_interes_en_curso']->toFixed(2),
                'dias_en_curso' => $k['dias_en_curso'], 'cuota_en_curso' => $k['cuota_en_curso'], 'interes_no_devengado' => $r['interes_no_devengado']->toFixed(2),
                'gastos_no_devengados' => $r['gastos_no_devengados']->toFixed(2), 'iva_no_devengado' => $r['iva_no_devengado']->toFixed(2), 'total' => $total->toFixed(2),
            ]);
            // Paso 7 (RN-36)
            (new RefinanciacionService($this->db))->observarPorCuotas(array_map(static fn ($q) => (int) $q['id'], $d['cuotas']), "Cancelación anticipada del crédito N.º {$c['numero']} (cobro {$numero}).");
            (new AuditoriaService($this->db))->registrar('creditos.cancelar-anticipadamente', 'creditos', $creditoId, 'Exitoso', null, [
                'numero' => (int) $c['numero'], 'cobro' => $numero, 'total' => $total->toFixed(2), 'cuotas' => count($d['cuotas']),
                'reduccion' => $r['interes_no_devengado']->add($r['gastos_no_devengados'])->add($r['iva_no_devengado'])->toFixed(2),
                'medio' => $medio, 'operacion' => $numeroOperacion,
            ]);

            return ['cobro_id' => $cobroId, 'numero' => $numero, 'importe' => $total->toFixed(2)];
        });
    }

    /**
     * Detalle de la cancelación para el comprobante (CU-35 con CU-97 paso 8).
     *
     * @return array<string, mixed>|null
     */
    public function delCobro(int $cobroId): ?array
    {
        return $this->db->query('SELECT a.*, c.numero AS credito FROM dbo.cancelaciones_anticipadas a JOIN dbo.creditos c ON c.id = a.credito_id WHERE a.cobro_id = ?', [$cobroId])->getRowArray();
    }

    /** @return array<string, mixed> */
    private function creditoAdmitido(int $creditoId): array
    {
        $c = $this->bloquear('creditos', [$creditoId])[0] ?? null;
        if ($c === null || !in_array($c['estado'], self::ADMITIDOS, true)) {
            throw new ReglaNegocioException('El crédito no existe o no está activo ni en mora: no admite cancelación anticipada (A2).');
        }

        return $c;
    }

    /**
     * Cuotas bloqueadas con los punitorios al día, cálculo de RN-67 y su distribución.
     *
     * @param array<string, mixed> $c crédito bloqueado
     * @return array{fecha: string, cuotas: list<array<string, mixed>>, calculo: array<string, mixed>, distribucion: array<string, mixed>, ordenes: list<int>}
     */
    private function detalle(array $c): array
    {
        $hoy = $this->hoyNegocio();
        $ids = array_map('intval', array_column($this->db->query("SELECT id FROM dbo.cuotas WHERE credito_id = ? AND estado IN ('Pendiente', 'Vencida')", [(int) $c['id']])->getResultArray(), 'id'));
        (new PunitorioService($this->db))->devengarCuotas($ids, $hoy);
        $cuotas = $this->db->query("SELECT * FROM dbo.cuotas WITH (UPDLOCK, ROWLOCK) WHERE credito_id = ? AND estado IN ('Pendiente', 'Vencida') ORDER BY numero", [(int) $c['id']])->getResultArray();
        if ($cuotas === []) {
            throw new ReglaNegocioException('El crédito no tiene cuotas pendientes (A2).');
        }
        $entrada = array_map(static fn ($q) => ['id' => (int) $q['id'], 'numero' => (int) $q['numero'], 'vencimiento' => new DateTimeImmutable((string) $q['vencimiento']), 'conceptos' => CobroCuotas::conceptos($q)], $cuotas);
        $fechaBase = new DateTimeImmutable(Time::parse((string) $c['fecha_liquidacion'])->setTimezone(config('Sigccr')->zonaNegocio)->format('Y-m-d'));
        $iva = Decimal::of((string) (new ParametroService($this->db))->vigente('Estructuracion')['valores']['alicuota_iva']);
        $pago = new DateTimeImmutable($hoy);
        $calculo = CancelacionAnticipada::calcular($entrada, $pago, $fechaBase, $iva);
        $ordenes = array_map('intval', array_column($this->db->query(
            'SELECT DISTINCT o.numero FROM dbo.ordenes_pago_electronico o JOIN dbo.ordenes_pago_cuotas oc ON oc.orden_pago_id = o.id '
            . "WHERE oc.cuota_id IN (" . implode(', ', $ids) . ") AND (o.estado = 'EnConciliacion' OR (o.estado = 'PendienteConfirmacion' AND o.vence > SYSDATETIMEOFFSET()))",
        )->getResultArray(), 'numero'));

        return ['fecha' => $hoy, 'cuotas' => $cuotas, 'calculo' => $calculo, 'distribucion' => CancelacionAnticipada::distribuir($entrada, $pago, $calculo), 'ordenes' => $ordenes];
    }
}
