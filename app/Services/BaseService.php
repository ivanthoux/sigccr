<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictoConcurrenciaException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Throwable;

/**
 * Base de los servicios de negocio (4.4.2).
 *
 * - Cada método público de un servicio corresponde a una operación de un caso de uso y se ejecuta con transaccion():
 *   si algo falla, se revierte completa (RN-46, RNF-06).
 * - bloquear() lee filas con UPDLOCK para serializar las decisiones sobre un mismo objeto.
 * - actualizarConVersion() aplica el control optimista de version_fila (RN-24).
 */
abstract class BaseService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    protected function transaccion(callable $operacion): mixed
    {
        $this->db->transException(true)->transBegin();
        try {
            $resultado = $operacion();
            $this->db->transCommit();

            return $resultado;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Lee y bloquea filas hasta el fin de la transacción.
     *
     * @param list<int|string> $ids
     * @return list<array<string, mixed>>
     */
    protected function bloquear(string $tabla, array $ids, string $columnaId = 'id'): array
    {
        if ($ids === []) {
            return [];
        }
        $this->validarIdentificador($tabla);
        $this->validarIdentificador($columnaId);
        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM dbo.{$tabla} WITH (UPDLOCK, ROWLOCK) WHERE {$columnaId} IN ({$marcas}) ORDER BY {$columnaId}";

        return $this->db->query($sql, array_values($ids))->getResultArray();
    }

    /**
     * Actualiza una fila solo si conserva la versión leída; incrementa version_fila.
     *
     * @param array<string, mixed> $datos
     */
    protected function actualizarConVersion(string $tabla, int|string $id, int $versionLeida, array $datos): void
    {
        $this->validarIdentificador($tabla);
        $builder = $this->db->table($tabla);
        $builder->set($datos)->set('version_fila', 'version_fila + 1', false)
            ->where('id', $id)->where('version_fila', $versionLeida)->update();
        if ($this->db->affectedRows() !== 1) {
            throw new ConflictoConcurrenciaException($tabla, $id);
        }
    }

    /** Instante actual en UTC con desplazamiento, para columnas datetimeoffset (3.5.1). */
    protected function ahoraUtc(): string
    {
        return Time::now('UTC')->format('Y-m-d H:i:s.vP');
    }

    /** Fecha local de negocio (Y-m-d). */
    protected function hoyNegocio(): string
    {
        return Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d');
    }

    private function validarIdentificador(string $nombre): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $nombre)) {
            throw new \InvalidArgumentException("Identificador SQL inválido: {$nombre}");
        }
    }
}
