<?php

declare(strict_types=1);

namespace App\Services\Cartera;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Cartera\Revocacion;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Caja\CajaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use CodeIgniter\I18n\Time;

/**
 * CU-102 Registrar Revocación del Préstamo (SS-06). RN-79, D-31, D-34.
 *
 * La revocación se registra en dos momentos: la comunicación del cliente, dentro de los 10 días hábiles, con el
 * cálculo del importe a restituir; y la confirmación de la transferencia recibida, que deja el crédito Revocado y sus
 * cuotas sin efecto. Mientras no se confirma, el crédito conserva su plan.
 */
class RevocacionService extends BaseService
{
    /**
     * CU-102 paso 2: créditos del cliente con su plazo de revocación.
     *
     * @return list<array<string, mixed>>
     */
    public function creditos(int $clienteId): array
    {
        $filas = $this->db->query(
            'SELECT c.id, c.numero, c.estado, c.capital, c.fecha_liquidacion, l.nombre AS linea, r.estado AS revocacion FROM dbo.creditos c '
            . 'JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . "LEFT JOIN dbo.revocaciones r ON r.credito_id = c.id WHERE c.cliente_id = ? AND c.origen = 'Liquidacion' ORDER BY c.fecha_liquidacion DESC",
            [$clienteId],
        )->getResultArray();
        foreach ($filas as &$f) {
            $p = $this->plazo((int) $f['id']);
            $f['fecha_limite'] = $p['limite'];
            $f['en_plazo'] = $p['limite'] !== null && $this->hoyNegocio() <= $p['limite'];
        }

        return $filas;
    }

    /**
     * CU-102 pasos 3 y 5: crédito, plazo, revocación registrada y, si aún no se registró, el importe a hoy.
     *
     * @return array<string, mixed>
     */
    public function datos(int $creditoId): array
    {
        $c = $this->db->query(
            'SELECT c.*, cl.denominacion, cl.cuit_cuil, l.nombre AS linea, q.importe_neto, q.cuenta_destino FROM dbo.creditos c JOIN dbo.clientes cl ON cl.id = c.cliente_id '
            . 'JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . 'LEFT JOIN dbo.liquidaciones q ON q.credito_id = c.id WHERE c.id = ?',
            [$creditoId],
        )->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('El crédito no existe.');
        }
        $c['plazo'] = $this->plazo($creditoId);
        $c['revocacion'] = $this->db->table('revocaciones')->where('credito_id', $creditoId)->get()->getRowArray();
        $c['pagado'] = $this->pagado($creditoId);
        $c['calculo'] = $c['revocacion'] === null && $c['importe_neto'] !== null ? $this->calcular($c, $this->hoyNegocio()) : null;

        return $c;
    }

    /**
     * CU-102 pasos 4 y 5 (A1, A4, A5): comunicación del cliente y cálculo del importe a restituir. Si lo pagado cubre
     * el importe, la revocación se confirma en el acto.
     *
     * @return array{importe: string, confirmada: bool}
     */
    public function registrar(int $creditoId, string $fechaTexto, string $medioTexto, int $usuarioId): array
    {
        $fecha = Campos::fecha($fechaTexto);
        $medio = Campos::texto($medioTexto);
        $e = [];
        if ($fecha === null) {
            $e['fecha_comunicacion'] = 'Ingrese la fecha en que el cliente comunicó la revocación.';
        } elseif ($fecha > $this->hoyNegocio()) {
            $e['fecha_comunicacion'] = 'La fecha de la comunicación no puede ser futura.';
        }
        if (mb_strlen($medio) < 3 || mb_strlen($medio) > 60) {
            $e['medio_comunicacion'] = 'Indique el medio por el que la comunicó (por ejemplo: presencial, correo electrónico, carta documento).';
        }
        if ($e !== []) {
            throw new ValidacionException($e, 'RN-79');
        }

        return $this->transaccion(function () use ($creditoId, $fecha, $medio, $usuarioId): array {
            $c = $this->bloquear('creditos', [$creditoId])[0] ?? null;
            if ($c === null || !in_array($c['estado'], ['Activo', 'EnMora'], true)) {
                throw new ReglaNegocioException('Solo se revoca un crédito activo.');
            }
            if ($this->db->table('revocaciones')->where('credito_id', $creditoId)->countAllResults() > 0) {
                throw new ReglaNegocioException('El crédito ya tiene una revocación registrada.');
            }
            $plazo = $this->plazo($creditoId);
            if ($plazo['limite'] === null) {
                throw new ReglaNegocioException('El crédito no tiene un contrato con constancia de recepción: no se puede computar el plazo de revocación.');
            }
            if ($fecha < $plazo['desembolso']) {
                throw new ValidacionException(['fecha_comunicacion' => 'La comunicación no puede ser anterior al desembolso (' . date('d/m/Y', (int) strtotime($plazo['desembolso'])) . ').'], 'RN-79');
            }
            if ($fecha > $plazo['limite']) {
                throw new ReglaNegocioException('El plazo de revocación venció el ' . date('d/m/Y', (int) strtotime($plazo['limite'])) . ': el cliente puede cancelar el crédito anticipadamente (CU-97) (A1).', 'RN-79');
            }
            $liquidacion = $this->db->table('liquidaciones')->select('importe_neto')->where('credito_id', $creditoId)->get()->getRow();
            $k = $this->calcular($c + ['importe_neto' => $liquidacion->importe_neto ?? null], (string) $fecha);
            $ahora = $this->ahoraUtc();
            $confirmada = $k['restituir']->isZero();
            $this->db->table('revocaciones')->insert([
                'credito_id' => $creditoId, 'fecha_comunicacion' => $fecha, 'medio_comunicacion' => $medio, 'fecha_limite' => $plazo['limite'],
                'capital' => $k['neto'], 'dias_transcurridos' => $k['dias'], 'interes_devengado' => $k['interes']->toFixed(2), 'iva_interes' => $k['iva']->toFixed(2),
                'pagado_previo' => $k['pagado']->toFixed(2), 'importe_restituir' => $k['restituir']->toFixed(2), 'saldo_a_favor' => $k['saldo_a_favor']->toFixed(2),
                'estado' => $confirmada ? 'Confirmada' : 'PendienteRestitucion', 'fecha_registro' => $ahora, 'usuario_id' => $usuarioId,
            ] + ($confirmada ? ['fecha_confirmacion' => $ahora, 'usuario_confirmacion_id' => $usuarioId] : []));
            $id = (int) $this->db->insertID();
            if ($confirmada) {
                $this->dejarSinEfecto($c, $k['saldo_a_favor']);
            }
            (new AuditoriaService($this->db))->registrar('creditos.revocar', 'creditos', $creditoId, 'Exitoso', null, [
                'revocacion' => $id, 'fecha' => $fecha, 'medio' => $medio, 'importe' => $k['restituir']->toFixed(2), 'confirmada' => $confirmada,
            ]);

            return ['importe' => $k['restituir']->toFixed(2), 'confirmada' => $confirmada];
        });
    }

    /** CU-102 pasos 6 a 8 (A2, A3): transferencia de restitución recibida; el crédito queda Revocado. */
    public function confirmar(int $creditoId, string $operacionTexto, string $importeTexto, int $usuarioId): void
    {
        $operacion = trim($operacionTexto);
        $importe = Campos::importe($importeTexto);
        $e = [];
        if ($operacion === '' || mb_strlen($operacion) > 60) {
            $e['numero_operacion'] = 'Informe el número de operación de la transferencia recibida.';
        }
        if ($importe === null) {
            $e['importe'] = 'Ingrese el importe transferido por el cliente.';
        }
        if ($e !== []) {
            throw new ValidacionException($e, 'RN-79');
        }

        $this->transaccion(function () use ($creditoId, $operacion, $importe, $usuarioId): void {
            $jornada = (new CajaService($this->db))->jornadaOperable($usuarioId);
            if ($jornada === null) {
                throw new ReglaNegocioException('No tiene una caja abierta hoy: ábrala para registrar la transferencia recibida (CU-31, RN-28).');
            }
            $c = $this->bloquear('creditos', [$creditoId])[0] ?? null;
            $r = $this->db->query('SELECT * FROM dbo.revocaciones WITH (UPDLOCK, ROWLOCK) WHERE credito_id = ?', [$creditoId])->getRowArray();
            if ($c === null || $r === null || $r['estado'] !== 'PendienteRestitucion') {
                throw new ReglaNegocioException('El crédito no tiene una revocación pendiente de restitución.');
            }
            if (Decimal::of((string) $importe)->compare((string) $r['importe_restituir']) !== 0) {
                $dif = Decimal::of((string) $r['importe_restituir'])->sub((string) $importe);
                throw new ValidacionException(['importe' => 'El importe a restituir es $ ' . str_replace('.', ',', (string) $r['importe_restituir']) . ': ' . ($dif->isPositive() ? 'faltan $ ' : 'sobran $ ') . str_replace('.', ',', $dif->abs()->toFixed(2)) . ' (A3).'], 'RN-79');
            }
            $ahora = $this->ahoraUtc();
            $this->db->table('cobros')->insert([
                'cliente_id' => (int) $c['cliente_id'], 'jornada_caja_id' => (int) $jornada['id'], 'fecha_hora' => $ahora, 'fecha_efectiva_pago' => $ahora,
                'importe' => (string) $r['importe_restituir'], 'tipo' => 'Revocacion', 'canal' => 'Ventanilla', 'estado' => 'Confirmado', 'usuario_id' => $usuarioId, 'cambio' => '0.00',
            ]);
            $cobroId = (int) $this->db->insertID();
            $numero = (int) $this->db->table('cobros')->select('numero')->where('id', $cobroId)->get()->getRow()->numero;
            $this->db->table('cobros_medios')->insert([
                'cobro_id' => $cobroId, 'medio' => 'Transferencia', 'importe' => (string) $r['importe_restituir'], 'importe_recibido' => (string) $r['importe_restituir'], 'numero_operacion' => $operacion,
            ]);
            $this->db->table('movimientos_caja')->insert([
                'jornada_caja_id' => (int) $jornada['id'], 'fecha_hora' => $ahora, 'tipo' => 'Cobro', 'importe' => (string) $r['importe_restituir'], 'medio' => 'Transferencia',
                'concepto' => "Restitución por revocación del crédito N.º {$c['numero']} · cobro N.º {$numero} · operación {$operacion}", 'cobro_id' => $cobroId, 'cobro_medio_id' => (int) $this->db->insertID(),
            ]);
            $this->db->table('comprobantes_pago')->insert(['cobro_id' => $cobroId, 'fecha_emision' => $ahora, 'cantidad_reemisiones' => 0]);
            $this->db->table('revocaciones')->where('id', (int) $r['id'])->update(['estado' => 'Confirmada', 'cobro_id' => $cobroId, 'fecha_confirmacion' => $ahora, 'usuario_confirmacion_id' => $usuarioId]);
            $this->dejarSinEfecto($c, Decimal::of((string) $r['saldo_a_favor']));
            (new AuditoriaService($this->db))->registrar('creditos.confirmar-revocacion', 'creditos', $creditoId, 'Exitoso', null, ['revocacion' => (int) $r['id'], 'cobro' => $numero, 'operacion' => $operacion]);
        });
    }

    /**
     * Plazo de revocación del crédito: desde la recepción del contrato o el desembolso, lo último (RN-79).
     *
     * @return array{recepcion: ?string, desembolso: ?string, limite: ?string}
     */
    public function plazo(int $creditoId): array
    {
        $r = $this->db->query(
            'SELECT TOP 1 c.fecha_liquidacion, k.fecha_recepcion FROM dbo.creditos c JOIN dbo.liquidaciones q ON q.credito_id = c.id '
            . 'LEFT JOIN dbo.contratos_prestamo k ON k.solicitud_id = q.solicitud_id AND k.documento_legajo_id IS NOT NULL WHERE c.id = ? ORDER BY k.id DESC',
            [$creditoId],
        )->getRowArray();
        if ($r === null) {
            return ['recepcion' => null, 'desembolso' => null, 'limite' => null];
        }
        $desembolso = Time::parse((string) $r['fecha_liquidacion'])->setTimezone(config('Sigccr')->zonaNegocio)->format('Y-m-d');
        $recepcion = $r['fecha_recepcion'] === null ? null : substr((string) $r['fecha_recepcion'], 0, 10);
        if ($recepcion === null) {
            return ['recepcion' => null, 'desembolso' => $desembolso, 'limite' => null];
        }
        $noHabiles = array_map(static fn ($f) => substr((string) $f['fecha'], 0, 10), $this->db->query(
            'SELECT fecha FROM dbo.dias_no_habiles WHERE fecha BETWEEN CAST(? AS date) AND DATEADD(day, 60, CAST(? AS date))',
            [min($recepcion, $desembolso), max($recepcion, $desembolso)],
        )->getResultArray());

        return ['recepcion' => $recepcion, 'desembolso' => $desembolso, 'limite' => Revocacion::fechaLimite($recepcion, $desembolso, $noHabiles)];
    }

    /**
     * @param array<string, mixed> $c fila de creditos con importe_neto
     * @return array{neto: string, pagado: Decimal, dias: int, interes: Decimal, iva: Decimal, total: Decimal, restituir: Decimal, saldo_a_favor: Decimal}
     */
    private function calcular(array $c, string $fecha): array
    {
        $plazo = $this->plazo((int) $c['id']);
        $alicuota = (string) (new ParametroService($this->db))->vigente('Estructuracion')['valores']['alicuota_iva'];
        $pagado = Decimal::of($this->pagado((int) $c['id']));
        $neto = (string) $c['importe_neto'];

        return ['neto' => $neto, 'pagado' => $pagado] + Revocacion::importe(Decimal::of($neto), Decimal::of((string) $c['tna']), (string) $plazo['desembolso'], $fecha, Decimal::of($alicuota), $pagado);
    }

    /** Cobros confirmados imputados a las cuotas del crédito. */
    private function pagado(int $creditoId): string
    {
        return (string) $this->db->query(
            "SELECT ISNULL(SUM(i.importe), 0) AS t FROM dbo.imputaciones i JOIN dbo.cuotas q ON q.id = i.cuota_id JOIN dbo.cobros b ON b.id = i.cobro_id WHERE q.credito_id = ? AND b.estado = 'Confirmado'",
            [$creditoId],
        )->getRow()->t;
    }

    /**
     * El crédito pasa a Revocado y sus cuotas no pagadas, a SinEfecto; el exceso pagado queda como saldo a favor.
     *
     * @param array<string, mixed> $c fila de creditos bloqueada
     */
    private function dejarSinEfecto(array $c, Decimal $saldoAFavor): void
    {
        $this->db->query("UPDATE dbo.cuotas SET estado = 'SinEfecto', version_fila = version_fila + 1 WHERE credito_id = ? AND estado IN ('Pendiente', 'Vencida')", [(int) $c['id']]);
        $this->actualizarConVersion('creditos', (int) $c['id'], (int) $c['version_fila'], ['estado' => 'Revocado']);
        if ($saldoAFavor->isPositive()) {
            $this->db->table('saldos_a_favor')->insert([
                'cliente_id' => (int) $c['cliente_id'], 'fecha' => $this->ahoraUtc(), 'importe_original' => $saldoAFavor->toFixed(2),
                'importe_disponible' => $saldoAFavor->toFixed(2), 'importe_reservado' => '0.00',
            ]);
        }
    }
}
