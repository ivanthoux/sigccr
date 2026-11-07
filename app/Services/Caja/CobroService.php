<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Caja\MediosCobro;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Imputador;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Cartera\PunitorioService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;

/**
 * Cobros en ventanilla (SS-05): CU-33 Registrar Cobro en Ventanilla, CU-35 Emitir Comprobante de Pago y CU-37 Anular
 * Cobro. RN-29, RN-31, RN-33, RN-57, RN-65, RN-77, D-11, D-12, D-30.
 *
 * El cobro bloquea el crédito y sus cuotas impagas, imputa el importe en el orden de RN-29, actualiza los saldos por
 * concepto, el estado de las cuotas y del crédito, registra un movimiento de caja por medio y emite el comprobante,
 * todo en una transacción.
 */
class CobroService extends BaseService
{
    private const ESTADOS_CREDITO = ['Activo', 'EnMora'];
    private const IMPAGAS = ['Pendiente', 'Vencida'];

    /**
     * CU-33 pasos 3 y 4: créditos del cliente con sus cuotas impagas, la deuda por concepto a la fecha y la
     * advertencia de morosidad avanzada (RN-65).
     *
     * @return array{creditos: list<array<string, mixed>>, total_vencido: string, max_atraso: int, morosidad_avanzada: bool, umbral: int}
     */
    public function obligaciones(int $clienteId): array
    {
        $hoy = $this->hoyNegocio();
        // RN-53: los punitorios se calculan hasta la fecha del pago; CU-88 completa los días que falten
        $this->transaccion(fn () => (new PunitorioService($this->db))->devengarCuotas($this->cuotasImpagas($clienteId), $hoy));
        $filas = $this->db->query(
            'SELECT q.*, c.numero AS credito_numero, c.estado AS credito_estado, l.nombre AS linea FROM dbo.cuotas q '
            . 'JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id '
            . 'JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . 'WHERE c.cliente_id = ? AND c.estado IN (?, ?) AND q.estado IN (?, ?) ORDER BY c.numero, q.numero',
            [$clienteId, ...self::ESTADOS_CREDITO, ...self::IMPAGAS],
        )->getResultArray();
        $creditos = [];
        $vencido = Decimal::zero();
        $maxAtraso = 0;
        foreach ($filas as $q) {
            $id = (int) $q['credito_id'];
            $creditos[$id] ??= ['id' => $id, 'numero' => (int) $q['credito_numero'], 'estado' => $q['credito_estado'], 'linea' => $q['linea'], 'cuotas' => []];
            $q['conceptos'] = CobroCuotas::conceptos($q);
            $q['total'] = CobroCuotas::total($q);
            $q['dias_atraso'] = CobroCuotas::diasAtraso((string) $q['vencimiento'], $hoy);
            if ($q['dias_atraso'] > 0) {
                $vencido = $vencido->add($q['total']);
                $maxAtraso = max($maxAtraso, $q['dias_atraso']);
            }
            $creditos[$id]['cuotas'][] = $q;
        }
        $umbral = (int) ((new ParametroService($this->db))->vigente('Morosidad')['valores']['umbral_morosidad_avanzada_dias'] ?? 60);

        return [
            'creditos' => array_values($creditos), 'total_vencido' => $vencido->toFixed(2), 'max_atraso' => $maxAtraso,
            'morosidad_avanzada' => $maxAtraso >= $umbral, 'umbral' => $umbral,
        ];
    }

    /**
     * CU-33 pasos 5 a 12 (A1 a A3, A7, A8).
     *
     * @param array<int|string, mixed> $seleccion crédito => números de cuota
     * @param list<array{medio: string, importe: string, numero_operacion?: ?string}> $medios
     * @return array{cobro_id: int, numero: int, importe: string, canceladas: int, parcial: ?int}
     */
    public function registrar(int $usuarioId, int $clienteId, array $seleccion, string $importeTexto, array $medios): array
    {
        $seleccion = array_map(static fn ($n) => array_values(array_map('intval', (array) $n)), array_filter($seleccion, static fn ($n) => $n !== [] && $n !== null));

        return $this->transaccion(function () use ($usuarioId, $clienteId, $seleccion, $importeTexto, $medios): array {
            // A3: jornada abierta hoy con la asignación vigente (RN-28)
            $jornada = (new CajaService($this->db))->jornadaOperable($usuarioId);
            if ($jornada === null) {
                throw new ReglaNegocioException('No tiene una caja abierta hoy: ábrala antes de registrar cobros (CU-31, RN-28).');
            }
            $creditoIds = array_map('intval', array_keys($seleccion));
            $creditos = [];
            foreach ($this->bloquear('creditos', $creditoIds) as $c) {
                if ((int) $c['cliente_id'] === $clienteId && in_array($c['estado'], self::ESTADOS_CREDITO, true)) {
                    $creditos[(int) $c['id']] = $c;
                }
            }
            if ($creditos !== []) {
                (new PunitorioService($this->db))->devengarCuotas($this->cuotasImpagas($clienteId, array_keys($creditos)), $this->hoyNegocio());
            }
            $cuotas = $creditos === [] ? [] : $this->db->query(
                'SELECT * FROM dbo.cuotas WITH (UPDLOCK, ROWLOCK) WHERE credito_id IN (' . implode(', ', array_fill(0, count($creditos), '?')) . ') AND estado IN (?, ?) ORDER BY credito_id, numero',
                [...array_keys($creditos), ...self::IMPAGAS],
            )->getResultArray();
            $pendientes = [];
            $porNumero = [];
            foreach ($cuotas as $q) {
                $pendientes[(int) $q['credito_id']][] = (int) $q['numero'];
                $porNumero[(int) $q['credito_id']][(int) $q['numero']] = $q;
            }
            $errores = CobroCuotas::validarSeleccion($pendientes, $seleccion, array_map(static fn ($c) => (string) $c['numero'], $creditos));
            if ($errores !== []) {
                throw new ValidacionException(['seleccion' => implode(' ', $errores)], 'RN-29');
            }
            $elegidas = [];
            foreach ($seleccion as $credito => $numeros) {
                foreach ($numeros as $n) {
                    $elegidas[] = $porNumero[(int) $credito][$n];
                }
            }
            $elegidas = CobroCuotas::ordenar($elegidas);
            $total = Decimal::sum(array_map(static fn ($q) => CobroCuotas::total($q), $elegidas));
            $imp = CobroCuotas::importeACobrar($importeTexto, $total);
            if ($imp['error'] !== null) {
                throw new ValidacionException(['importe' => $imp['error']], 'RN-29');
            }
            $importe = $imp['importe'];
            $m = MediosCobro::componer($medios, $importe);
            if ($m['errores'] !== []) {
                throw new ValidacionException(['medios' => implode(' ', $m['errores'])], 'RN-77');
            }
            $r = Imputador::imputar($importe, array_map(static fn ($q) => ['id' => (int) $q['id'], 'conceptos' => CobroCuotas::conceptos($q)], $elegidas));

            // Paso 9: cobro, medios, movimientos de caja e imputaciones
            $ahora = $this->ahoraUtc();
            $this->db->table('cobros')->insert([
                'cliente_id' => $clienteId, 'jornada_caja_id' => (int) $jornada['id'], 'fecha_hora' => $ahora, 'fecha_efectiva_pago' => $ahora,
                'importe' => $importe->toFixed(2), 'tipo' => 'Ordinario', 'canal' => 'Ventanilla', 'estado' => 'Confirmado', 'usuario_id' => $usuarioId, 'cambio' => '0.00',
            ]);
            $cobroId = (int) $this->db->insertID();
            $numero = (int) $this->db->table('cobros')->select('numero')->where('id', $cobroId)->get()->getRow()->numero;
            foreach ($m['medios'] as $medio) {
                $this->db->table('cobros_medios')->insert([
                    'cobro_id' => $cobroId, 'medio' => $medio['medio'], 'importe' => $medio['importe']->toFixed(2),
                    'importe_recibido' => $medio['recibido']->toFixed(2), 'numero_operacion' => $medio['numero_operacion'],
                ]);
                $medioId = (int) $this->db->insertID();
                $this->db->table('movimientos_caja')->insert([
                    'jornada_caja_id' => (int) $jornada['id'], 'fecha_hora' => $ahora, 'tipo' => 'Cobro', 'importe' => $medio['importe']->toFixed(2),
                    'medio' => $medio['medio'], 'concepto' => "Cobro N.º {$numero} · operación {$medio['numero_operacion']}", 'cobro_id' => $cobroId, 'cobro_medio_id' => $medioId,
                ]);
            }
            $porCuota = [];
            foreach ($r['imputaciones'] as $i) {
                $this->db->table('imputaciones')->insert([
                    'cobro_id' => $cobroId, 'cuota_id' => $i['cuota'], 'concepto' => $i['concepto'], 'importe' => $i['importe']->toFixed(2), 'parcial' => $i['parcial'] ? 1 : 0,
                ]);
                $porCuota[$i['cuota']][] = $i;
            }
            $parcial = null;
            foreach ($elegidas as $q) {
                if (!isset($porCuota[(int) $q['id']])) {
                    continue;
                }
                $s = CobroCuotas::saldosDespues($q, $porCuota[(int) $q['id']]);
                $this->actualizarConVersion('cuotas', (int) $q['id'], (int) $q['version_fila'], $s['columnas'] + ($s['pagada'] ? ['estado' => 'Pagada'] : []));
                if (!$s['pagada']) {
                    $parcial = (int) $q['numero'];
                }
            }
            foreach ($creditos as $c) {
                $this->actualizarEstadoCredito($c);
            }

            // Paso 11: comprobante (CU-35)
            $this->db->table('comprobantes_pago')->insert(['cobro_id' => $cobroId, 'fecha_emision' => $ahora, 'cantidad_reemisiones' => 0]);
            (new AuditoriaService($this->db))->registrar('cobros.registrar', 'cobros', $cobroId, 'Exitoso', null, [
                'numero' => $numero, 'cliente' => $clienteId, 'importe' => $importe->toFixed(2), 'total_seleccionado' => $total->toFixed(2),
                'cuotas' => array_map(static fn ($q) => (int) $q['id'], $elegidas), 'canceladas' => $r['canceladas'],
                'medios' => array_map(static fn ($x) => ['medio' => $x['medio'], 'importe' => $x['importe']->toFixed(2), 'operacion' => $x['numero_operacion']], $m['medios']),
            ]);

            return ['cobro_id' => $cobroId, 'numero' => $numero, 'importe' => $importe->toFixed(2), 'canceladas' => count($r['canceladas']), 'parcial' => $parcial];
        });
    }

    /**
     * CU-35 paso 4: comprobante con el cobro, sus medios y las cuotas alcanzadas por concepto.
     *
     * @return array<string, mixed>
     */
    public function comprobante(int $cobroId): array
    {
        $c = $this->db->query(
            "SELECT b.*, p.numero AS comprobante, p.fecha_emision, p.cantidad_reemisiones, cl.denominacion, cl.cuit_cuil, u.apellido + ', ' + u.nombre AS cajero, "
            . 'a.fecha AS fecha_anulacion, a.motivo AS motivo_anulacion FROM dbo.cobros b JOIN dbo.comprobantes_pago p ON p.cobro_id = b.id '
            . 'JOIN dbo.clientes cl ON cl.id = b.cliente_id LEFT JOIN dbo.usuarios_internos u ON u.id = b.usuario_id '
            . 'LEFT JOIN dbo.anulaciones_cobro a ON a.cobro_id = b.id WHERE b.id = ?',
            [$cobroId],
        )->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('El cobro no existe o no tiene comprobante.');
        }
        $c['medios'] = $this->db->table('cobros_medios')->where('cobro_id', $cobroId)->orderBy('id')->get()->getResultArray();
        $imputaciones = $this->db->query(
            'SELECT i.concepto, i.importe, q.id AS cuota_id, q.numero AS cuota, q.vencimiento, q.estado AS estado_cuota, c.numero AS credito '
            . 'FROM dbo.imputaciones i JOIN dbo.cuotas q ON q.id = i.cuota_id JOIN dbo.creditos c ON c.id = q.credito_id WHERE i.cobro_id = ? ORDER BY q.vencimiento, c.numero, q.numero, i.id',
            [$cobroId],
        )->getResultArray();
        $cuotas = [];
        foreach ($imputaciones as $i) {
            $k = (int) $i['cuota_id'];
            $cuotas[$k] ??= ['credito' => (int) $i['credito'], 'cuota' => (int) $i['cuota'], 'vencimiento' => $i['vencimiento'], 'estado' => $i['estado_cuota'], 'conceptos' => [], 'total' => '0.00'];
            $cuotas[$k]['conceptos'][$i['concepto']] = Decimal::of($cuotas[$k]['conceptos'][$i['concepto']] ?? '0')->add((string) $i['importe'])->toFixed(2);
            $cuotas[$k]['total'] = Decimal::of($cuotas[$k]['total'])->add((string) $i['importe'])->toFixed(2);
        }
        $c['cuotas'] = array_values($cuotas);

        return $c;
    }

    /** CU-35 paso 7 y A3: cada emisión, consulta o reemisión se audita; la reemisión no genera un nuevo cobro (RN-31). */
    public function registrarEmision(int $cobroId, bool $reemision): void
    {
        $this->transaccion(function () use ($cobroId, $reemision): void {
            if ($reemision) {
                $this->db->query('UPDATE dbo.comprobantes_pago SET cantidad_reemisiones = cantidad_reemisiones + 1 WHERE cobro_id = ?', [$cobroId]);
            }
            (new AuditoriaService($this->db))->registrar($reemision ? 'cobros.reemitir-comprobante' : 'cobros.consultar-comprobante', 'cobros', $cobroId, 'Exitoso');
        });
    }

    /**
     * CU-35 pasos 1 y 2 (A2): cobros por número de comprobante, cliente o fecha.
     *
     * @param array<string, mixed> $f numero, texto, fecha (Y-m-d)
     * @return list<array<string, mixed>>
     */
    public function buscar(array $f): array
    {
        $b = $this->db->table('cobros b')->select('b.id, b.numero, b.fecha_hora, b.importe, b.estado, b.canal, p.numero AS comprobante, cl.denominacion, cl.cuit_cuil')
            ->join('comprobantes_pago p', 'p.cobro_id = b.id')->join('clientes cl', 'cl.id = b.cliente_id');
        if (($f['numero'] ?? '') !== '') {
            $b->groupStart()->where('p.numero', (int) $f['numero'])->orWhere('b.numero', (int) $f['numero'])->groupEnd();
        }
        if (($f['texto'] ?? '') !== '') {
            $b->groupStart()->like('cl.denominacion', (string) $f['texto'])->orLike('cl.cuit_cuil', preg_replace('/\D/', '', (string) $f['texto']) ?: (string) $f['texto'])->groupEnd();
        }
        if (($f['fecha'] ?? null) !== null) {
            // Fecha local de negocio (UTC−3, sin horario de verano)
            $b->where("CAST(SWITCHOFFSET(b.fecha_hora, '-03:00') AS date) = " . $this->db->escape((string) $f['fecha']), null, false);
        }

        return $b->orderBy('b.fecha_hora', 'DESC')->limit(200)->get()->getResultArray();
    }

    /**
     * CU-37 paso 4 (A2): cobros confirmados posteriores que alcanzaron las mismas cuotas. Su imputación partió de los
     * saldos que dejó este cobro, por lo que deben revisarse (RN-33).
     *
     * @return list<array<string, mixed>>
     */
    public function dependientes(int $cobroId): array
    {
        return $this->db->query(
            'SELECT DISTINCT b.id, b.numero, b.fecha_hora, b.importe FROM dbo.cobros b JOIN dbo.imputaciones i ON i.cobro_id = b.id '
            . "WHERE b.estado = 'Confirmado' AND b.id <> ? AND b.fecha_hora > (SELECT fecha_hora FROM dbo.cobros WHERE id = ?) "
            . 'AND i.cuota_id IN (SELECT cuota_id FROM dbo.imputaciones WHERE cobro_id = ?) ORDER BY b.fecha_hora',
            [$cobroId, $cobroId, $cobroId],
        )->getResultArray();
    }

    /**
     * CU-37 pasos 6 a 10 (A1, A2, A4): anulación del cobro, restitución de las cuotas alcanzadas, estado del crédito y
     * saldo a favor por el importe recibido (RN-33, RN-57). El cobro original y sus movimientos de caja se conservan.
     *
     * @return array{saldo_a_favor: string, cuotas: int, dependientes: int}
     */
    public function anular(int $cobroId, string $motivoTexto, bool $continuarConDependientes, int $usuarioId): array
    {
        $motivo = Campos::texto($motivoTexto);
        $error = Campos::motivo($motivo) ?? (mb_strlen($motivo) > 500 ? 'El motivo admite hasta 500 caracteres.' : null);
        if ($error !== null) {
            throw new ValidacionException(['motivo' => $error], 'RN-33');
        }

        return $this->transaccion(function () use ($cobroId, $motivo, $continuarConDependientes, $usuarioId): array {
            $b = $this->bloquear('cobros', [$cobroId])[0] ?? null;
            if ($b === null) {
                throw new ReglaNegocioException('El cobro no existe.');
            }
            if ($b['tipo'] === 'Revocacion') {
                throw new ReglaNegocioException('La restitución de una revocación no se anula: el crédito ya quedó sin efecto (RN-79).');
            }
            if ($b['estado'] === 'Anulado') {
                throw new ReglaNegocioException('El cobro N.º ' . $b['numero'] . ' ya fue anulado: no se generan nuevos ajustes (A1).', 'RN-33');
            }
            $dependientes = $this->dependientes($cobroId);
            if ($dependientes !== [] && !$continuarConDependientes) {
                throw new ValidacionException(['dependientes' => 'Hay ' . count($dependientes) . ' cobro(s) posteriores sobre las mismas cuotas. Revíselos o confirme que continúa dejándolos identificados para su revisión (RN-33).'], 'RN-33');
            }
            $hoy = $this->hoyNegocio();
            $imputaciones = $this->db->table('imputaciones')->select('cuota_id, concepto, importe')->where('cobro_id', $cobroId)->get()->getResultArray();
            $porCuota = [];
            foreach ($imputaciones as $i) {
                $porCuota[(int) $i['cuota_id']][] = ['concepto' => (string) $i['concepto'], 'importe' => (string) $i['importe']];
            }
            $cuotas = $this->bloquear('cuotas', array_keys($porCuota));
            $creditoIds = array_values(array_unique(array_map(static fn ($q) => (int) $q['credito_id'], $cuotas)));
            $creditos = $this->bloquear('creditos', $creditoIds);
            foreach ($cuotas as $q) {
                $r = CobroCuotas::restaurar($q, $porCuota[(int) $q['id']], $hoy);
                $this->actualizarConVersion('cuotas', (int) $q['id'], (int) $q['version_fila'], $r['columnas'] + ['estado' => $r['estado']]);
            }
            foreach ($creditos as $c) {
                $this->restaurarEstadoCredito($c, $hoy);
            }

            // Paso 9: el importe recibido queda como saldo a favor del cliente (RN-33, RN-57)
            $this->db->table('saldos_a_favor')->insert([
                'cliente_id' => (int) $b['cliente_id'], 'fecha' => $this->ahoraUtc(), 'importe_original' => (string) $b['importe'],
                'importe_disponible' => (string) $b['importe'], 'importe_reservado' => '0.00',
            ]);
            $saldoId = (int) $this->db->insertID();
            $this->db->table('anulaciones_cobro')->insert([
                'cobro_id' => $cobroId, 'fecha' => $this->ahoraUtc(), 'motivo' => $motivo, 'usuario_id' => $usuarioId, 'saldo_a_favor_id' => $saldoId,
            ]);
            $this->db->table('cobros')->where('id', $cobroId)->update(['estado' => 'Anulado']);
            (new AuditoriaService($this->db))->registrar('cobros.anular', 'cobros', $cobroId, 'Exitoso', $motivo, [
                'numero' => (int) $b['numero'], 'importe' => (string) $b['importe'], 'cuotas' => array_keys($porCuota), 'saldo_a_favor' => $saldoId,
                'dependientes' => array_map(static fn ($d) => (int) $d['numero'], $dependientes),
            ]);

            return ['saldo_a_favor' => (string) $b['importe'], 'cuotas' => count($cuotas), 'dependientes' => count($dependientes)];
        });
    }

    /**
     * Estado del crédito después de anular un cobro (4.2.7): En mora si tiene cuotas vencidas impagas; si no, Activo.
     *
     * @param array<string, mixed> $c fila de creditos bloqueada
     */
    private function restaurarEstadoCredito(array $c, string $hoy): void
    {
        if (!in_array($c['estado'], ['Activo', 'EnMora', 'Cancelado'], true)) {
            return;
        }
        $r = $this->db->query(
            'SELECT COUNT(*) AS impagas, SUM(CASE WHEN vencimiento < CAST(? AS date) THEN 1 ELSE 0 END) AS vencidas FROM dbo.cuotas WHERE credito_id = ? AND estado IN (?, ?)',
            [$hoy, (int) $c['id'], ...self::IMPAGAS],
        )->getRowArray();
        $destino = (int) $r['impagas'] === 0 ? 'Cancelado' : ((int) $r['vencidas'] > 0 ? 'EnMora' : 'Activo');
        if ($destino !== $c['estado']) {
            $this->actualizarConVersion('creditos', (int) $c['id'], (int) $c['version_fila'], ['estado' => $destino]);
        }
    }

    /**
     * Cuotas impagas de los créditos activos o en mora del cliente (o de los créditos indicados).
     *
     * @param list<int>|null $creditoIds
     * @return list<int>
     */
    private function cuotasImpagas(int $clienteId, ?array $creditoIds = null): array
    {
        $b = $this->db->table('cuotas q')->select('q.id')->join('creditos c', 'c.id = q.credito_id')
            ->where('c.cliente_id', $clienteId)->whereIn('c.estado', self::ESTADOS_CREDITO)->whereIn('q.estado', self::IMPAGAS);
        if ($creditoIds !== null) {
            $b->whereIn('c.id', $creditoIds === [] ? [0] : $creditoIds);
        }

        return array_map('intval', array_column($b->orderBy('q.id')->get()->getResultArray(), 'id'));
    }

    /**
     * Estado del crédito después del cobro (4.2.7): Cancelado si no quedan cuotas impagas; Activo si estaba En mora y
     * ya no tiene cuotas vencidas impagas.
     *
     * @param array<string, mixed> $c fila de creditos bloqueada
     */
    private function actualizarEstadoCredito(array $c): void
    {
        $r = $this->db->query(
            'SELECT COUNT(*) AS impagas, SUM(CASE WHEN vencimiento < CAST(? AS date) THEN 1 ELSE 0 END) AS vencidas FROM dbo.cuotas WHERE credito_id = ? AND estado IN (?, ?)',
            [$this->hoyNegocio(), (int) $c['id'], ...self::IMPAGAS],
        )->getRowArray();
        $destino = match (true) {
            (int) $r['impagas'] === 0 => 'Cancelado',
            $c['estado'] === 'EnMora' && (int) $r['vencidas'] === 0 => 'Activo',
            default => null,
        };
        if ($destino !== null) {
            $this->actualizarConVersion('creditos', (int) $c['id'], (int) $c['version_fila'], ['estado' => $destino]);
        }
    }
}
