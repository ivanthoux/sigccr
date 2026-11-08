<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Caja\SaldoAFavor;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Imputador;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Cartera\PunitorioService;
use App\Services\Comunes\AuditoriaService;

/**
 * Saldos a favor del cliente (SS-05): consulta con su origen y CU-76 Aplicar Saldo a Favor a Obligaciones (Cajero).
 * RN-29, RN-57, D-37.
 */
class SaldoFavorService extends BaseService
{
    private const IMPAGAS = ['Pendiente', 'Vencida'];
    public const ACTIVAS = ['PendienteEjecucion', 'EnEjecucion', 'EnConciliacion'];

    /**
     * Saldos del cliente con su origen (pago electrónico, anulación de un cobro o revocación), lo reservado y las
     * autorizaciones de devolución activas (CU-76 paso 3, CU-77 paso 2).
     *
     * @return list<array<string, mixed>>
     */
    public function saldos(int $clienteId, bool $soloConImporte = true): array
    {
        $filas = $this->db->query(
            'SELECT s.*, t.id_externo, t.estado AS estado_transaccion, o.numero AS orden, b.numero AS cobro_anulado, '
            . "(SELECT TOP 1 a.id FROM dbo.autorizaciones_devolucion a WHERE a.saldo_a_favor_id = s.id AND a.estado IN ('PendienteEjecucion', 'EnEjecucion', 'EnConciliacion')) AS autorizacion_activa "
            . 'FROM dbo.saldos_a_favor s LEFT JOIN dbo.transacciones_pasarela t ON t.id = s.transaccion_origen_id '
            . 'LEFT JOIN dbo.ordenes_pago_electronico o ON o.id = t.orden_pago_id LEFT JOIN dbo.anulaciones_cobro an ON an.saldo_a_favor_id = s.id '
            . 'LEFT JOIN dbo.cobros b ON b.id = an.cobro_id WHERE s.cliente_id = ?'
            . ($soloConImporte ? ' AND (s.importe_disponible > 0 OR s.importe_reservado > 0)' : '') . ' ORDER BY s.fecha, s.id',
            [$clienteId],
        )->getResultArray();
        foreach ($filas as &$s) {
            $s['origen'] = match (true) {
                $s['transaccion_origen_id'] !== null => 'Pago electrónico ' . $s['id_externo'] . ($s['orden'] !== null ? " (orden N.º {$s['orden']})" : ''),
                $s['cobro_anulado'] !== null => "Anulación del cobro N.º {$s['cobro_anulado']}",
                default => 'Excedente de una revocación u otro ingreso',
            };
        }
        unset($s);

        return $filas;
    }

    /**
     * Aplicaciones registradas del cliente, las más recientes primero.
     *
     * @return list<array<string, mixed>>
     */
    public function aplicaciones(int $clienteId, int $limite = 20): array
    {
        return $this->db->query(
            "SELECT TOP ({$limite}) a.*, u.apellido + ', ' + u.nombre AS cajero, (SELECT COUNT(DISTINCT i.cuota_id) FROM dbo.imputaciones i WHERE i.aplicacion_saldo_id = a.id) AS cuotas "
            . 'FROM dbo.aplicaciones_saldo a JOIN dbo.saldos_a_favor s ON s.id = a.saldo_a_favor_id JOIN dbo.usuarios_internos u ON u.id = a.usuario_id WHERE s.cliente_id = ? ORDER BY a.fecha DESC, a.id DESC',
            [$clienteId],
        )->getResultArray();
    }

    /**
     * CU-76 pasos 5 a 10 (A1 a A3, A5): el disponible se aplica desde el saldo más antiguo a las cuotas seleccionadas,
     * consecutivas desde la más antigua, con la prelación de RN-29. Si no alcanza, la última cuota queda con saldo
     * (imputación parcial excepcional, RN-57). Cada saldo usado genera su aplicación.
     *
     * @param array<int|string, mixed> $seleccion crédito => números de cuota
     * @return array{importe: string, aplicaciones: int, canceladas: int, parcial: ?int, remanente: string}
     */
    public function aplicar(int $cajeroId, int $clienteId, array $seleccion, string $fechaTexto, string $medioTexto, string $observacionesTexto): array
    {
        $fecha = Campos::fecha($fechaTexto);
        $medio = Campos::texto($medioTexto);
        $observaciones = Campos::texto($observacionesTexto);
        $errores = [];
        if ($fecha === null || $fecha > $this->hoyNegocio()) {
            $errores['fecha_conformidad'] = 'Ingrese la fecha de la solicitud o conformidad del cliente (no posterior a hoy, A3).';
        }
        if ($medio === '' || mb_strlen($medio) > 100) {
            $errores['medio_conformidad'] = 'Indique el medio de la solicitud o conformidad del cliente (A3).';
        }
        if (mb_strlen($observaciones) > 150) {
            $errores['observaciones'] = 'Las observaciones admiten hasta 150 caracteres.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-57');
        }
        $constancia = mb_substr(formato_fecha((string) $fecha) . ' · ' . $medio . ($observaciones !== '' ? ' · ' . $observaciones : ''), 0, 300);
        $seleccion = array_map(static fn ($n) => array_values(array_map('intval', (array) $n)), array_filter($seleccion, static fn ($n) => $n !== [] && $n !== null));

        return $this->transaccion(function () use ($cajeroId, $clienteId, $seleccion, $constancia): array {
            $saldos = $this->db->query('SELECT * FROM dbo.saldos_a_favor WITH (UPDLOCK, ROWLOCK) WHERE cliente_id = ? ORDER BY fecha, id', [$clienteId])->getResultArray();
            $disponible = SaldoAFavor::disponible($saldos);
            if (!$disponible->isPositive()) {
                $reservado = Decimal::sum(array_map(static fn ($s) => Decimal::of((string) $s['importe_reservado']), $saldos));
                throw new ReglaNegocioException($reservado->isPositive()
                    ? 'El saldo a favor del cliente está reservado para una devolución autorizada: no puede aplicarse hasta que se resuelva (A2, RN-73).'
                    : 'El cliente no tiene saldo a favor disponible (A1).', 'RN-57');
            }
            $creditos = [];
            foreach ($this->bloquear('creditos', array_map('intval', array_keys($seleccion))) as $c) {
                if ((int) $c['cliente_id'] === $clienteId && in_array($c['estado'], ['Activo', 'EnMora'], true)) {
                    $creditos[(int) $c['id']] = $c;
                }
            }
            $hoy = $this->hoyNegocio();
            $ids = $creditos === [] ? [] : array_map('intval', array_column($this->db->table('cuotas')->select('id')->whereIn('credito_id', array_keys($creditos))->whereIn('estado', self::IMPAGAS)->get()->getResultArray(), 'id'));
            (new PunitorioService($this->db))->devengarCuotas($ids, $hoy);
            $pendientes = [];
            $porNumero = [];
            foreach ($ids === [] ? [] : $this->db->table('cuotas')->whereIn('id', $ids)->orderBy('credito_id')->orderBy('numero')->get()->getResultArray() as $q) {
                $pendientes[(int) $q['credito_id']][] = (int) $q['numero'];
                $porNumero[(int) $q['credito_id']][(int) $q['numero']] = (int) $q['id'];
            }
            $e = CobroCuotas::validarSeleccion($pendientes, $seleccion, array_map(static fn ($c) => (string) $c['numero'], $creditos));
            if ($e !== []) {
                throw new ValidacionException(['seleccion' => implode(' ', $e)], 'RN-29');
            }
            $elegidas = [];
            foreach ($seleccion as $credito => $numeros) {
                foreach ($numeros as $n) {
                    $elegidas[] = $porNumero[(int) $credito][$n];
                }
            }
            $leer = fn (): array => CobroCuotas::ordenar($this->db->query(
                'SELECT * FROM dbo.cuotas WITH (UPDLOCK, ROWLOCK) WHERE id IN (' . implode(', ', $elegidas) . ") AND estado IN ('Pendiente', 'Vencida')",
            )->getResultArray());
            $total = Decimal::sum(array_map(static fn ($q) => CobroCuotas::total($q), $leer()));
            $importe = Decimal::min($disponible, $total);
            $cobros = new CobroService($this->db);
            $aplicaciones = [];
            $canceladas = 0;
            $parcial = null;
            foreach (SaldoAFavor::distribuir($importe, $saldos) as $parte) {
                $cuotas = $leer();
                if ($cuotas === []) {
                    break;
                }
                $r = Imputador::imputar($parte['importe'], array_map(static fn ($q) => ['id' => (int) $q['id'], 'conceptos' => CobroCuotas::conceptos($q)], $cuotas), true);
                $this->db->table('aplicaciones_saldo')->insert([
                    'saldo_a_favor_id' => $parte['saldo'], 'fecha' => $this->ahoraUtc(), 'importe' => $parte['importe']->toFixed(2), 'constancia_conformidad' => $constancia, 'usuario_id' => $cajeroId,
                ]);
                $aplicacionId = (int) $this->db->insertID();
                $res = $cobros->imputar($aplicacionId, $cuotas, $r['imputaciones'], $this->bloquear('creditos', array_keys($creditos)), 'aplicacion_saldo_id');
                $parcial = $res['parcial'];
                $canceladas += count($r['canceladas']);
                $saldo = array_values(array_filter($saldos, static fn ($s) => (int) $s['id'] === $parte['saldo']))[0];
                $this->actualizarConVersion('saldos_a_favor', $parte['saldo'], (int) $saldo['version_fila'], [
                    'importe_disponible' => Decimal::of((string) $saldo['importe_disponible'])->sub($parte['importe'])->toFixed(2),
                ]);
                $aplicaciones[] = ['aplicacion' => $aplicacionId, 'saldo' => $parte['saldo'], 'importe' => $parte['importe']->toFixed(2)];
            }
            $remanente = $disponible->sub($importe);
            (new AuditoriaService($this->db))->registrar('saldos-favor.aplicar', 'clientes', $clienteId, 'Exitoso', $constancia, [
                'importe' => $importe->toFixed(2), 'total_seleccionado' => $total->toFixed(2), 'aplicaciones' => $aplicaciones, 'cuotas' => $elegidas,
                'canceladas' => $canceladas, 'cuota_parcial' => $parcial, 'remanente' => $remanente->toFixed(2),
            ]);

            return ['importe' => $importe->toFixed(2), 'aplicaciones' => count($aplicaciones), 'canceladas' => $canceladas, 'parcial' => $parcial, 'remanente' => $remanente->toFixed(2)];
        });
    }
}
