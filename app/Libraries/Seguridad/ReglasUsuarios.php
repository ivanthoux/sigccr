<?php

declare(strict_types=1);

namespace App\Libraries\Seguridad;

/**
 * Reglas sobre las cuentas de los usuarios internos (RN-04, RN-05, RN-06), sin acceso a la base: el servicio reúne
 * los datos y estas funciones deciden. Cada una devuelve los motivos de rechazo; vacía si la operación es válida.
 */
final class ReglasUsuarios
{
    /** Roles internos válidos (RN-06). */
    public const ROLES = ['Administrador', 'Gerente', 'AnalistaCredito', 'Cajero'];

    /** Estados en los que un Administrador cuenta para la continuidad de la administración (RN-05). */
    public const ESTADOS_ACTIVOS = ['Habilitada', 'BloqueadaTemporalmente'];

    /**
     * CU-08 pasos 3 y 4.
     *
     * @param list<string> $responsabilidades descripciones de las responsabilidades pendientes del usuario (RN-04)
     * @return list<string>
     */
    public static function motivosContraDesactivar(
        int $actuanteId,
        int $usuarioId,
        string $rolUsuario,
        string $estadoUsuario,
        int $administradoresActivos,
        array $responsabilidades,
    ): array {
        if ($estadoUsuario === 'Desactivada') {
            return ['El usuario ya está desactivado.'];
        }
        if ($actuanteId === $usuarioId) {
            return ['No puede desactivar su propia cuenta.'];
        }
        if (self::esUltimoAdministrador($rolUsuario, $estadoUsuario, $administradoresActivos)) {
            return ['No se puede desactivar al último Administrador habilitado: el sistema quedaría sin administración (RN-05).'];
        }

        return array_map(static fn (string $r) => "Tiene pendiente: {$r}.", $responsabilidades);
    }

    /**
     * CU-10 pasos 4 y 5. Devuelve null si el rol nuevo es igual al actual (A3: no hay cambios por aplicar).
     *
     * @return list<string>|null
     */
    public static function motivosContraCambiarRol(
        string $rolActual,
        string $rolNuevo,
        string $estadoUsuario,
        int $administradoresActivos,
        bool $jornadaAbierta,
        bool $asignacionCajaVigente,
    ): ?array {
        if (!in_array($rolNuevo, self::ROLES, true)) {
            return ['Seleccione un rol válido.'];
        }
        if ($rolNuevo === $rolActual) {
            return null;
        }
        $motivos = [];
        if (self::esUltimoAdministrador($rolActual, $estadoUsuario, $administradoresActivos)) {
            $motivos[] = 'Es el último Administrador habilitado: el sistema quedaría sin administración (RN-05).';
        }
        if ($rolActual === 'Cajero' && $jornadaAbierta) {
            $motivos[] = 'Tiene una jornada de caja abierta; debe cerrarse antes del cambio (CU-36).';
        }
        if ($rolActual === 'Cajero' && $asignacionCajaVigente) {
            $motivos[] = 'Tiene una caja asignada; la asignación debe finalizarse antes del cambio (CU-72).';
        }

        return $motivos;
    }

    /** CU-09: estado al reactivar; sin contraseña definida vuelve a Pendiente de activación. */
    public static function estadoAlReactivar(bool $tieneContrasena): string
    {
        return $tieneContrasena ? 'Habilitada' : 'PendienteActivacion';
    }

    private static function esUltimoAdministrador(string $rol, string $estado, int $administradoresActivos): bool
    {
        return $rol === 'Administrador' && in_array($estado, self::ESTADOS_ACTIVOS, true) && $administradoresActivos <= 1;
    }
}
