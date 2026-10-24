<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Exceptions\ReglaNegocioException;
use App\Services\BaseService;

/**
 * Parámetros de negocio versionados (RN-15, CU-51, CU-58 a CU-60). Cada operación guarda el id de la versión aplicada.
 */
class ParametroService extends BaseService
{
    /**
     * Versión vigente de un grupo en un instante (por defecto, ahora).
     *
     * @return array{id: int, numero: int, valores: array<string, mixed>}
     */
    public function vigente(string $tipo, ?string $instanteUtc = null): array
    {
        $fila = $this->db->table('versiones_parametros')
            ->where('tipo', $tipo)
            ->where('vigente_desde <=', $instanteUtc ?? $this->ahoraUtc())
            ->orderBy('vigente_desde', 'DESC')->orderBy('numero', 'DESC')
            ->get(1)->getRowArray();
        if ($fila === null) {
            throw new ReglaNegocioException("No hay parámetros vigentes de tipo {$tipo}.");
        }

        return [
            'id' => (int) $fila['id'],
            'numero' => (int) $fila['numero'],
            'valores' => json_decode((string) $fila['valores'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}
