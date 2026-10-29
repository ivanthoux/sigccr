<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;

/**
 * Alertas para un rol o un usuario (RF-11, CU-83). Se generan en la misma transacción de la operación o del proceso
 * que detecta la situación y se resuelven automáticamente cuando esa situación se resuelve (resolver()); la consulta
 * no las resuelve (postcondición de CU-83).
 */
class AlertaService extends BaseService
{
    public const TIPOS = ['Operativa', 'Vencimiento', 'Integracion', 'Proceso', 'Seguridad'];

    public function generar(
        string $tipo,
        string $causa,
        ?string $accionPendiente,
        ?string $rolDestino,
        ?int $usuarioDestinoId = null,
        ?string $objetoTipo = null,
        int|string|null $objetoId = null,
    ): int {
        $this->db->table('alertas')->insert([
            'tipo' => $tipo,
            'causa' => $causa,
            'accion_pendiente' => $accionPendiente,
            'rol_destino' => $rolDestino,
            'usuario_destino_id' => $usuarioDestinoId,
            'objeto_tipo' => $objetoTipo,
            'objeto_id' => $objetoId === null ? null : (string) $objetoId,
            'fecha_generacion' => $this->ahoraUtc(),
            'estado' => 'Pendiente',
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Resuelve las alertas pendientes de un objeto cuando se resuelve la situación que las originó (RF-11). La llaman
     * las operaciones y los procesos de cada caso de uso, dentro de su transacción.
     *
     * @param string $responsable nombre del usuario o del proceso automático que resolvió la situación
     * @return int cantidad de alertas resueltas
     */
    public function resolver(string $objetoTipo, int|string $objetoId, string $responsable, ?string $tipo = null): int
    {
        $b = $this->db->table('alertas')->where('objeto_tipo', $objetoTipo)->where('objeto_id', (string) $objetoId)->where('estado', 'Pendiente');
        if ($tipo !== null) {
            $b->where('tipo', $tipo);
        }
        $b->update(['estado' => 'Resuelta', 'fecha_resolucion' => $this->ahoraUtc(), 'resuelta_por' => mb_substr($responsable, 0, 150)]);

        return $this->db->affectedRows();
    }

    /** Pendientes dirigidas al usuario o a su rol: indicador del panel (CU-83 paso 1). */
    public function contarPendientes(int $usuarioId, string $rol): int
    {
        return $this->db->table('alertas')->where('estado', 'Pendiente')
            ->groupStart()->where('rol_destino', $rol)->orWhere('usuario_destino_id', $usuarioId)->groupEnd()
            ->countAllResults();
    }

    /**
     * CU-83 pasos 2 y 3: alertas del usuario o de su rol, de la más reciente a la más antigua.
     *
     * @param array<string, string> $filtros estado (Pendiente|Resuelta|Todas), tipo, desde y hasta (dd/mm/aaaa o aaaa-mm-dd)
     * @return list<array<string, mixed>>
     */
    public function listar(int $usuarioId, string $rol, array $filtros): array
    {
        $b = $this->db->table('alertas a')
            ->select('a.id, a.tipo, a.causa, a.accion_pendiente, a.objeto_tipo, a.objeto_id, a.fecha_generacion, a.fecha_resolucion, a.estado, a.resuelta_por, v.fecha_hora AS vista')
            ->join('alertas_vistas v', "v.alerta_id = a.id AND v.usuario_id = {$usuarioId}", 'left')
            ->groupStart()->where('a.rol_destino', $rol)->orWhere('a.usuario_destino_id', $usuarioId)->groupEnd();
        $estado = $filtros['estado'] ?? 'Pendiente';
        if (in_array($estado, ['Pendiente', 'Resuelta'], true)) {
            $b->where('a.estado', $estado);
        }
        if (in_array($filtros['tipo'] ?? '', self::TIPOS, true)) {
            $b->where('a.tipo', $filtros['tipo']);
        }
        $zona = config('Sigccr')->zonaNegocio;
        if (($desde = Campos::fecha($filtros['desde'] ?? '')) !== null) {
            $b->where('a.fecha_generacion >=', \CodeIgniter\I18n\Time::parse($desde . ' 00:00:00', $zona)->setTimezone('UTC')->format('Y-m-d H:i:s.vP'));
        }
        if (($hasta = Campos::fecha($filtros['hasta'] ?? '')) !== null) {
            $b->where('a.fecha_generacion <', \CodeIgniter\I18n\Time::parse($hasta . ' 00:00:00', $zona)->addDays(1)->setTimezone('UTC')->format('Y-m-d H:i:s.vP'));
        }

        return $b->orderBy('a.fecha_generacion', 'DESC')->orderBy('a.id', 'DESC')->get(500)->getResultArray();
    }

    /**
     * CU-83 pasos 4 y 5: detalle de una alerta del usuario o de su rol, que queda registrada como vista por él.
     *
     * @return array<string, mixed>
     */
    public function verDetalle(int $id, int $usuarioId, string $rol): array
    {
        $a = $this->db->table('alertas')
            ->select('id, tipo, causa, accion_pendiente, rol_destino, usuario_destino_id, objeto_tipo, objeto_id, fecha_generacion, fecha_resolucion, estado, resuelta_por')
            ->where('id', $id)->get()->getRowArray();
        if ($a === null || ($a['rol_destino'] !== $rol && (int) ($a['usuario_destino_id'] ?? 0) !== $usuarioId)) {
            // Una alerta de otro rol o de otra persona se trata como inexistente (RN-01)
            throw new ReglaNegocioException('La alerta no existe o no está dirigida a usted.');
        }
        $this->db->query(
            'IF NOT EXISTS (SELECT 1 FROM dbo.alertas_vistas WHERE alerta_id = ? AND usuario_id = ?) INSERT INTO dbo.alertas_vistas (alerta_id, usuario_id, fecha_hora) VALUES (?, ?, ?)',
            [$id, $usuarioId, $id, $usuarioId, $this->ahoraUtc()],
        );

        return $a;
    }
}
