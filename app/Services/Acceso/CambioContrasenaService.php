<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Seguridad\ControlIntentos;
use App\Libraries\Seguridad\PoliticaContrasena;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * CU-107 Cambiar Contraseña (RN-62; OWASP ASVS 5.0 V6.2.2 y V6.2.3). La persona con sesión iniciada cambia su
 * contraseña con la actual y la nueva.
 *
 * - La actual se verifica y los fallos cuentan como en el inicio de sesión (RN-62): al quinto, la cuenta se bloquea
 *   temporalmente y la sesión deja de valer. Quien tiene una sesión abierta en un equipo ajeno no puede adivinar la
 *   contraseña sin que el bloqueo lo frene.
 * - La nueva cumple la política (15 a 128 caracteres, fuera de la lista de comprometidas) y es distinta de la actual.
 * - version_seguridad + 1 cierra todas las demás sesiones de la cuenta (RN-04); la sesión actual toma la versión nueva.
 */
class CambioContrasenaService extends BaseService
{
    public const MENSAJE_ACTUAL_INCORRECTA = 'La contraseña actual no es correcta.';
    public const MENSAJE_IGUAL = 'La contraseña nueva debe ser distinta de la actual.';

    /**
     * @return int la nueva version_seguridad, para la sesión actual
     *
     * @throws ReglaNegocioException por la política, por la contraseña actual incorrecta o por una cuenta no habilitada
     */
    public function cambiar(int $cuentaId, string $actual, string $nueva, string $confirmacion): int
    {
        if ($actual === '') {
            throw new ReglaNegocioException('Ingrese su contraseña actual.', 'RN-62');
        }
        $motivos = PoliticaContrasena::validar($nueva, $confirmacion);
        if ($motivos !== []) {
            throw new ReglaNegocioException(implode(' ', $motivos), 'RN-62');
        }
        if (hash_equals($actual, $nueva)) {
            throw new ReglaNegocioException(self::MENSAJE_IGUAL, 'RN-62');
        }

        // Los fallos se confirman antes de rechazar: la transacción no se revierte con el rechazo
        $resultado = $this->transaccion(fn (): int|string => $this->aplicar($cuentaId, $actual, $nueva));
        if (is_string($resultado)) {
            throw new ReglaNegocioException($resultado, 'RN-62');
        }

        return $resultado;
    }

    private function aplicar(int $cuentaId, string $actual, string $nueva): int|string
    {
        $auditoria = new AuditoriaService($this->db);
        $cuenta = $this->db->query(
            'SELECT id, correo, hash_contrasena, estado, intentos_fallidos, inicio_ventana_intentos, version_seguridad '
            . 'FROM dbo.cuentas_acceso WITH (UPDLOCK, ROWLOCK) WHERE id = ?',
            [$cuentaId],
        )->getRowArray();
        if ($cuenta === null || $cuenta['estado'] !== 'Habilitada' || $cuenta['hash_contrasena'] === null) {
            return 'La sesión ya no es válida. Ingrese nuevamente.';
        }

        if (!password_verify($actual, $cuenta['hash_contrasena'])) {
            $cfg = config('Sigccr');
            $control = new ControlIntentos($cfg->intentosPorCuenta, $cfg->ventanaIntentos, $cfg->bloqueoTemporal);
            $ahora = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $inicio = $cuenta['inicio_ventana_intentos'] === null ? null : new DateTimeImmutable((string) $cuenta['inicio_ventana_intentos'], new DateTimeZone('UTC'));
            $f = $control->registrarFallo((int) $cuenta['intentos_fallidos'], $inicio, $ahora);
            $this->db->query(
                'UPDATE dbo.cuentas_acceso SET intentos_fallidos = ?, inicio_ventana_intentos = ?, estado = ?, bloqueada_hasta = ?, version_fila = version_fila + 1 WHERE id = ?',
                [
                    $f['intentos_fallidos'],
                    $f['inicio_ventana_intentos']->format('Y-m-d H:i:s.vP'),
                    $f['bloquear'] ? 'BloqueadaTemporalmente' : 'Habilitada',
                    $f['bloqueada_hasta']?->format('Y-m-d H:i:s.vP'),
                    $cuentaId,
                ],
            );
            $auditoria->registrar('acceso.cambio-contrasena-fallido', 'cuenta_acceso', $cuentaId, 'Rechazado', "Contraseña actual incorrecta (intento {$f['intentos_fallidos']})", null, null, $cuentaId, (string) $cuenta['correo']);
            if ($f['bloquear']) {
                $auditoria->registrar('acceso.bloqueo', 'cuenta_acceso', $cuentaId, 'Exitoso', 'Bloqueo temporal por intentos fallidos (RN-62)', null, null, $cuentaId, 'Sistema');
            }

            return $control->mensajeRechazo(self::MENSAJE_ACTUAL_INCORRECTA, $f['intentos_fallidos'], $f['bloquear']);
        }

        $version = (int) $cuenta['version_seguridad'] + 1;
        $this->db->query(
            'UPDATE dbo.cuentas_acceso SET hash_contrasena = ?, version_seguridad = ?, intentos_fallidos = 0, inicio_ventana_intentos = NULL, '
            . 'actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?',
            [password_hash($nueva, PASSWORD_ARGON2ID), $version, $this->ahoraUtc(), $cuentaId],
        );
        // Los enlaces de restablecimiento o de activación que hubieran quedado vigentes ya no corresponden a esta contraseña
        (new AutorizacionService($this->db))->sustituirVigentes($cuentaId);
        $auditoria->registrar('acceso.cambio-contrasena', 'cuenta_acceso', $cuentaId, 'Exitoso', null, null, null, $cuentaId, (string) $cuenta['correo']);

        return $version;
    }
}
