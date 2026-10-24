<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Services\BaseService;

/** Alertas para un rol o un usuario (RF-11, CU-83). */
class AlertaService extends BaseService
{
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
}
