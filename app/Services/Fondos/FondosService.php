<?php

declare(strict_types=1);

namespace App\Services\Fondos;

use App\Libraries\Finanzas\Decimal;
use App\Services\BaseService;
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
}
