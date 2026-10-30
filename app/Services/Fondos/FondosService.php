<?php

declare(strict_types=1);

namespace App\Services\Fondos;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\ReglasCaja;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;

/**
 * Disponibilidad de fondos de la entidad (SS-05, RN-41, RN-71).
 *
 *   fondos = movimientos de fondos realizados (ingresos − egresos, CU-85) + importes imputados a obligaciones
 *            (cobros confirmados y aplicaciones de saldo) − desembolsos (liquidaciones) − devoluciones completadas
 *   disponible libre = fondos − reservas y compromisos vigentes − reserva mínima (parámetros de campañas, CU-51)
 *
 * Las reservas se constituyen con un bloqueo de aplicación para que dos aprobaciones simultáneas no usen los mismos
 * fondos.
 */
class FondosService extends BaseService
{
    /** @return array{fondos: string, afectaciones: string, reserva_minima: string, libre: string} */
    public function disponibilidad(): array
    {
        $q = fn (string $sql): Decimal => Decimal::of((string) ($this->db->query($sql)->getRowArray()['t'] ?? '0'));
        $movimientos = $q("SELECT COALESCE(SUM(CASE WHEN caracter = 'Ingreso' THEN importe ELSE -importe END), 0) AS t FROM dbo.movimientos_fondos WHERE estado = 'Realizado'");
        $imputado = $q('SELECT COALESCE(SUM(i.importe), 0) AS t FROM dbo.imputaciones i LEFT JOIN dbo.cobros c ON c.id = i.cobro_id '
            . "WHERE i.aplicacion_saldo_id IS NOT NULL OR c.estado = 'Confirmado'");
        $desembolsos = $q('SELECT COALESCE(SUM(importe_neto), 0) AS t FROM dbo.liquidaciones');
        $devoluciones = $q("SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.autorizaciones_devolucion WHERE estado = 'Completada'");
        $afectaciones = $q("SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.afectaciones_fondos WHERE estado = 'Vigente'");
        $minima = Decimal::of((string) ((new ParametroService($this->db))->vigente('Campanas')['valores']['reserva_minima'] ?? '0'));
        $fondos = $movimientos->add($imputado)->sub($desembolsos)->sub($devoluciones);

        return [
            'fondos' => $fondos->toFixed(2),
            'afectaciones' => $afectaciones->toFixed(2),
            'reserva_minima' => $minima->toFixed(2),
            'libre' => $fondos->sub($afectaciones)->sub($minima)->toFixed(2),
        ];
    }

    /**
     * Constituye la reserva de una solicitud aprobada (RN-41), si el disponible libre alcanza. Debe llamarse dentro de
     * la transacción de la aprobación: el bloqueo se libera con ella.
     *
     * @return ?int id de la reserva, o null si los fondos no alcanzan
     */
    public function reservarParaSolicitud(int $solicitudId, string $importe): ?int
    {
        $this->db->query("DECLARE @r INT; EXEC @r = sp_getapplock @Resource = 'sigccr:fondos', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 10000; SELECT @r AS r;");
        if (Decimal::of($this->disponibilidad()['libre'])->compare($importe) < 0) {
            return null;
        }
        $this->db->table('afectaciones_fondos')->insert([
            'tipo' => 'Reserva', 'importe' => Decimal::of($importe)->toFixed(2), 'fecha' => $this->ahoraUtc(), 'estado' => 'Vigente',
            'referencia_tipo' => 'Solicitud', 'referencia_id' => $solicitudId,
        ]);

        return (int) $this->db->insertID();
    }

    /** Reserva o compromiso vigente de una solicitud (RN-41), o null. */
    public function afectacionVigente(int $solicitudId): ?array
    {
        return $this->db->query(
            "SELECT * FROM dbo.afectaciones_fondos WITH (UPDLOCK, ROWLOCK) WHERE referencia_tipo = 'Solicitud' AND referencia_id = ? AND estado = 'Vigente'",
            [$solicitudId],
        )->getRowArray();
    }

    /** Rechazo, cancelación, vencimiento o pérdida de vigencia de la aprobación liberan la afectación (RN-41). */
    public function liberarDeSolicitud(int $solicitudId): int
    {
        $this->db->query(
            "UPDATE dbo.afectaciones_fondos SET estado = 'Liberada', version_fila = version_fila + 1 WHERE referencia_tipo = 'Solicitud' AND referencia_id = ? AND estado = 'Vigente'",
            [$solicitudId],
        );

        return $this->db->affectedRows();
    }

    /** La liquidación consume la reserva; el egreso queda registrado por el desembolso (RN-41, RN-71). */
    public function consumir(int $afectacionId): void
    {
        $this->db->query("UPDATE dbo.afectaciones_fondos SET estado = 'Consumida', version_fila = version_fila + 1 WHERE id = ? AND estado = 'Vigente'", [$afectacionId]);
    }

    // ── CU-85 Registrar Movimiento de Fondos ───────────────────────────────────

    /** Saldos a favor de clientes: se muestran separados y no integran la disponibilidad (GLO-47, RN-57). */
    public function saldosAFavor(): string
    {
        return Decimal::of((string) ($this->db->query('SELECT COALESCE(SUM(importe_disponible + importe_reservado), 0) AS t FROM dbo.saldos_a_favor')->getRowArray()['t'] ?? '0'))->toFixed(2);
    }

    /**
     * Movimientos de fondos, los más recientes primero, con la corrección que recibió cada uno.
     *
     * @return list<array<string, mixed>>
     */
    public function movimientos(int $limite = 100): array
    {
        return $this->db->query(
            "SELECT TOP ({$limite}) m.*, ui.apellido + ', ' + ui.nombre AS responsable, c.id AS correccion_id "
            . 'FROM dbo.movimientos_fondos m JOIN dbo.usuarios_internos ui ON ui.id = m.usuario_id '
            . 'LEFT JOIN dbo.movimientos_fondos c ON c.movimiento_corregido_id = m.id ORDER BY m.fecha DESC, m.id DESC',
        )->getResultArray();
    }

    /**
     * CU-85 pasos 3 a 7 (A1).
     *
     * @param array<string, mixed> $datos
     */
    public function registrarMovimiento(array $datos, int $usuarioId): int
    {
        $v = ReglasCaja::validarMovimiento($datos, $this->hoyNegocio());
        if ($v['errores'] !== []) {
            throw new ValidacionException($v['errores'], 'RN-71');
        }
        $d = $v['datos'];

        return $this->transaccion(function () use ($d, $usuarioId): int {
            if ($d['caracter'] === 'Egreso' && $d['estado'] === 'Realizado') {
                $this->exigirDisponible((string) $d['importe']);
            }
            $this->db->table('movimientos_fondos')->insert($d + ['usuario_id' => $usuarioId]);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('fondos.registrar_movimiento', 'movimientos_fondos', $id, 'Exitoso', null, $d);

            return $id;
        });
    }

    /** CU-85 A2: corrección con un movimiento inverso vinculado, sin modificar el original (RN-44). */
    public function corregir(int $id, string $motivo, int $usuarioId): int
    {
        $texto = $this->motivo($motivo);

        return $this->transaccion(function () use ($id, $texto, $usuarioId): int {
            $m = $this->bloquear('movimientos_fondos', [$id])[0] ?? null;
            if ($m === null || $m['estado'] !== 'Realizado') {
                throw new ReglaNegocioException('Solo se corrigen movimientos realizados; un movimiento programado se anula.');
            }
            if ($m['movimiento_corregido_id'] !== null || $this->db->table('movimientos_fondos')->where('movimiento_corregido_id', $id)->countAllResults() > 0) {
                throw new ReglaNegocioException('El movimiento ya es una corrección o ya fue corregido.');
            }
            $inverso = $m['caracter'] === 'Ingreso' ? 'Egreso' : 'Ingreso';
            if ($inverso === 'Egreso') {
                $this->exigirDisponible((string) $m['importe']);
            }
            $this->db->table('movimientos_fondos')->insert([
                'tipo' => $m['tipo'], 'caracter' => $inverso, 'importe' => $m['importe'], 'fecha' => $this->hoyNegocio(),
                'concepto' => mb_substr("Corrección del movimiento N.º {$id}: {$m['concepto']}", 0, 200), 'referencia' => $m['referencia'],
                'estado' => 'Realizado', 'usuario_id' => $usuarioId, 'movimiento_corregido_id' => $id, 'motivo' => $texto,
            ]);
            $nuevo = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('fondos.corregir_movimiento', 'movimientos_fondos', $id, 'Exitoso', $texto, ['correccion' => $nuevo]);

            return $nuevo;
        });
    }

    /** CU-85 A3: un movimiento programado cuya fecha se alcanzó se confirma como realizado. */
    public function confirmarProgramado(int $id): void
    {
        $this->transaccion(function () use ($id): void {
            $m = $this->bloquear('movimientos_fondos', [$id])[0] ?? null;
            if ($m === null || $m['estado'] !== 'Programado') {
                throw new ReglaNegocioException('El movimiento no está programado.');
            }
            if (substr((string) $m['fecha'], 0, 10) > $this->hoyNegocio()) {
                throw new ReglaNegocioException('Todavía no llegó la fecha del movimiento: solo se considera en la proyección.');
            }
            if ($m['caracter'] === 'Egreso') {
                $this->exigirDisponible((string) $m['importe']);
            }
            $this->db->table('movimientos_fondos')->where('id', $id)->update(['estado' => 'Realizado']);
            (new AuditoriaService($this->db))->registrar('fondos.confirmar_movimiento', 'movimientos_fondos', $id);
        });
    }

    /** CU-85 A3: anulación de un movimiento programado, con motivo. */
    public function anularProgramado(int $id, string $motivo): void
    {
        $texto = $this->motivo($motivo);
        $this->transaccion(function () use ($id, $texto): void {
            $m = $this->bloquear('movimientos_fondos', [$id])[0] ?? null;
            if ($m === null || $m['estado'] !== 'Programado') {
                throw new ReglaNegocioException('Solo se anulan movimientos programados; uno realizado se corrige.');
            }
            $this->db->table('movimientos_fondos')->where('id', $id)->update(['estado' => 'Anulado', 'motivo' => $texto]);
            (new AuditoriaService($this->db))->registrar('fondos.anular_movimiento', 'movimientos_fondos', $id, 'Exitoso', $texto);
        });
    }

    /** CU-85 A1: un egreso realizado no puede usar reservas, compromisos ni saldos a favor (RN-71). */
    private function exigirDisponible(string $importe): void
    {
        $this->db->query("DECLARE @r INT; EXEC @r = sp_getapplock @Resource = 'sigccr:fondos', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 10000; SELECT @r AS r;");
        $libre = $this->disponibilidad()['libre'];
        if (Decimal::of($libre)->compare($importe) < 0) {
            throw new ValidacionException(['importe' => 'El egreso supera el disponible libre (' . str_replace('.', ',', $libre) . '): no pueden usarse reservas, compromisos ni la reserva mínima (RN-71).'], 'RN-71');
        }
    }

    private function motivo(string $motivo): string
    {
        $texto = Campos::texto($motivo);
        if (($error = Campos::motivo($texto)) !== null) {
            throw new ValidacionException(['motivo' => $error]);
        }

        return mb_substr($texto, 0, 300);
    }
}
