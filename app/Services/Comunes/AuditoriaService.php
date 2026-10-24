<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Services\BaseService;

/**
 * Registro de auditoría (RN-45, SS-10). Solo inserta: la tabla rechaza modificaciones y eliminaciones.
 * No registra contraseñas, autorizaciones temporales ni datos de tarjetas (RNF-14).
 */
class AuditoriaService extends BaseService
{
    private const CLAVES_PROHIBIDAS = ['password', 'contrasena', 'clave', 'token', 'codigo', 'autorizacion', 'tarjeta', 'cvv'];

    /**
     * @param array<string, mixed>|null $detalle estado anterior y nuevo, parámetros aplicados
     */
    public function registrar(
        string $accion,
        string $objetoTipo,
        int|string|null $objetoId,
        string $resultado = 'Exitoso',
        ?string $motivo = null,
        ?array $detalle = null,
        ?string $proceso = null,
        ?int $cuentaAccesoId = null,
        ?string $responsable = null,
    ): void {
        // Sin sesión (inicio de sesión, recuperación) el servicio indica la cuenta y el responsable
        $sesion = session_status() === PHP_SESSION_ACTIVE ? session() : null;
        $cuenta = $sesion?->get('cuenta');
        $this->db->table('registros_auditoria')->insert([
            'fecha_hora' => $this->ahoraUtc(),
            'cuenta_acceso_id' => $cuentaAccesoId ?? $cuenta['id'] ?? null,
            'responsable' => $proceso !== null ? "Proceso {$proceso}" : ($responsable ?? $cuenta['nombre'] ?? 'Sistema'),
            'proceso' => $proceso,
            'accion' => $accion,
            'objeto_tipo' => $objetoTipo,
            'objeto_id' => $objetoId === null ? null : (string) $objetoId,
            'resultado' => $resultado,
            'motivo' => $motivo,
            'detalle' => $detalle === null ? null : json_encode($this->depurar($detalle), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_origen' => is_cli() ? null : service('request')->getIPAddress(),
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function depurar(array $datos): array
    {
        foreach ($datos as $k => $v) {
            foreach (self::CLAVES_PROHIBIDAS as $p) {
                if (str_contains(strtolower((string) $k), $p)) {
                    $datos[$k] = '[omitido]';
                    continue 2;
                }
            }
            if (is_array($v)) {
                $datos[$k] = $this->depurar($v);
            }
        }

        return $datos;
    }
}
