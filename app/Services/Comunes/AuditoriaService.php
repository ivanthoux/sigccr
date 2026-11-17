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
    /** Campos de negocio cuyo nombre contiene una palabra prohibida pero no son secretos (D-48). */
    private const CLAVES_PERMITIDAS = ['codigo', 'codigo_postal', 'es_clave'];

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
     * Registro de una modificación con el valor anterior y el nuevo de cada campo que cambió (D-48). Los cambios se
     * guardan en el detalle bajo la clave «cambios», junto con los datos adicionales.
     *
     * @param array<string, array{anterior: mixed, nuevo: mixed}> $cambios resultado de Cambios::entre() o Cambios::campo()
     * @param array<string, mixed> $datos datos adicionales (versión creada, solicitudes afectadas, etc.)
     */
    public function registrarCambios(string $accion, string $objetoTipo, int|string|null $objetoId, array $cambios, ?string $motivo = null, array $datos = [], ?string $proceso = null): void
    {
        $this->registrar($accion, $objetoTipo, $objetoId, 'Exitoso', $motivo, ['cambios' => $cambios] + $datos, $proceso);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function depurar(array $datos): array
    {
        foreach ($datos as $k => $v) {
            $clave = strtolower((string) $k);
            // En los cambios la clave es la ruta del campo («pesos.situacion_bcra»): se evalúa su último tramo
            $campo = substr($clave, (int) strrpos('.' . $clave, '.'));
            foreach (in_array($campo, self::CLAVES_PERMITIDAS, true) ? [] : self::CLAVES_PROHIBIDAS as $p) {
                if (str_contains($clave, $p)) {
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
