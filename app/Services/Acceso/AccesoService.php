<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Seguridad\ControlIntentos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Inicio y cierre de sesión en el panel y en el portal (CU-01, CU-03; RN-02, RN-08, RN-10, RN-62).
 *
 * El rechazo es siempre el mismo mensaje genérico: no revela si la cuenta existe ni su estado (RN-62). Cuando la
 * cuenta no existe o no está habilitada se verifica igualmente una contraseña de referencia, para que el tiempo de
 * respuesta no delate la diferencia.
 */
class AccesoService extends BaseService
{
    public const MENSAJE_RECHAZO = 'Usuario o contraseña incorrectos.';

    /** Hash Argon2id de una contraseña aleatoria, solo para igualar el tiempo de respuesta. */
    private const HASH_REFERENCIA = '$argon2id$v=19$m=65536,t=4,p=1$OEd0bU9IYkJGMFlzSEM3dg$i3J5vJ/+kw2Dl3uVWgNK+wlRQrxiOM3GgSbxSUp87q4';

    /**
     * Valida las credenciales y devuelve los datos de la sesión (CU-01 pasos 4 y 5).
     *
     * @param 'UsuarioInterno'|'Cliente' $tipoCuenta panel o portal (RN-08)
     *
     * @return array<string, mixed>
     *
     * @throws ReglaNegocioException con el mensaje genérico
     */
    public function autenticar(string $tipoCuenta, string $correo, string $contrasena): array
    {
        $correo = mb_strtolower(trim($correo));
        // Los intentos fallidos se confirman antes de rechazar: la transacción no se revierte con el rechazo
        $resultado = $this->transaccion(fn () => $this->verificar($tipoCuenta, $correo, $contrasena));
        if ($resultado === null) {
            throw new ReglaNegocioException(self::MENSAJE_RECHAZO, 'RN-62');
        }

        return $resultado;
    }

    /** @return array<string, mixed>|null */
    private function verificar(string $tipoCuenta, string $correo, string $contrasena): ?array
    {
        $auditoria = new AuditoriaService($this->db);
        $cuenta = $this->db->query(
            'SELECT ca.id, ca.tipo_cuenta, ca.correo, ca.hash_contrasena, ca.estado, ca.intentos_fallidos, '
            . 'ca.inicio_ventana_intentos, ca.bloqueada_hasta, ca.version_seguridad, ca.ultimo_acceso, ui.nombre, ui.apellido, ui.rol_id, '
            . 'r.codigo AS rol_codigo, r.nombre AS rol_nombre, c.id AS cliente_id, c.estado AS estado_cliente, '
            . 'COALESCE(ph.nombre + \' \' + ph.apellido, pj.razon_social) AS nombre_cliente '
            . 'FROM dbo.cuentas_acceso ca WITH (UPDLOCK, ROWLOCK) '
            . 'LEFT JOIN dbo.usuarios_internos ui ON ui.id = ca.id LEFT JOIN dbo.roles r ON r.id = ui.rol_id '
            . 'LEFT JOIN dbo.clientes c ON c.cuenta_cliente_id = ca.id '
            . 'LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id '
            . 'WHERE ca.tipo_cuenta = ? AND ca.correo = ?',
            [$tipoCuenta, $correo],
        )->getRowArray();

        if ($cuenta === null) {
            password_verify($contrasena, self::HASH_REFERENCIA);
            $auditoria->registrar('acceso.fallido', 'cuenta_acceso', null, 'Rechazado', 'Credenciales inválidas', ['correo' => $correo, 'ambito' => $tipoCuenta], responsable: $correo);

            return null;
        }

        $id = (int) $cuenta['id'];
        $ahora = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $control = new ControlIntentos(config('Sigccr')->intentosPorCuenta, config('Sigccr')->ventanaIntentos, config('Sigccr')->bloqueoTemporal);
        if ($control->bloqueoVencido($cuenta['estado'], $this->fecha($cuenta['bloqueada_hasta']), $ahora)) {
            $this->db->query("UPDATE dbo.cuentas_acceso SET estado = 'Habilitada', intentos_fallidos = 0, inicio_ventana_intentos = NULL, bloqueada_hasta = NULL, version_fila = version_fila + 1 WHERE id = ?", [$id]);
            $cuenta['estado'] = 'Habilitada';
            $cuenta['intentos_fallidos'] = 0;
            $cuenta['inicio_ventana_intentos'] = null;
        }

        if ($cuenta['estado'] !== 'Habilitada') {
            password_verify($contrasena, self::HASH_REFERENCIA);
            $auditoria->registrar('acceso.fallido', 'cuenta_acceso', $id, 'Rechazado', "Cuenta en estado {$cuenta['estado']}", null, null, $id, $cuenta['correo']);

            return null;
        }

        if ($cuenta['hash_contrasena'] === null || !password_verify($contrasena, $cuenta['hash_contrasena'])) {
            $f = $control->registrarFallo((int) $cuenta['intentos_fallidos'], $this->fecha($cuenta['inicio_ventana_intentos']), $ahora);
            $this->db->query(
                'UPDATE dbo.cuentas_acceso SET intentos_fallidos = ?, inicio_ventana_intentos = ?, estado = ?, bloqueada_hasta = ?, version_fila = version_fila + 1 WHERE id = ?',
                [
                    $f['intentos_fallidos'],
                    $this->texto($f['inicio_ventana_intentos']),
                    $f['bloquear'] ? 'BloqueadaTemporalmente' : 'Habilitada',
                    $f['bloqueada_hasta'] === null ? null : $this->texto($f['bloqueada_hasta']),
                    $id,
                ],
            );
            $auditoria->registrar('acceso.fallido', 'cuenta_acceso', $id, 'Rechazado', "Contraseña incorrecta (intento {$f['intentos_fallidos']})", null, null, $id, $cuenta['correo']);
            if ($f['bloquear']) {
                $auditoria->registrar('acceso.bloqueo', 'cuenta_acceso', $id, 'Exitoso', 'Bloqueo temporal por intentos fallidos (RN-62)', ['hasta' => $this->texto($f['bloqueada_hasta'])], null, $id, 'Sistema');
            }

            return null;
        }

        // RN-10: un cliente desactivado en el padrón no accede al portal aunque su contraseña sea correcta
        if ($tipoCuenta === 'Cliente' && ($cuenta['cliente_id'] === null || $cuenta['estado_cliente'] !== 'Activo')) {
            $auditoria->registrar('acceso.fallido', 'cuenta_acceso', $id, 'Rechazado', 'Cliente desactivado en el padrón (RN-10)', null, null, $id, $cuenta['correo']);

            return null;
        }

        $permisos = $tipoCuenta === 'UsuarioInterno' ? $this->permisosDelRol((int) $cuenta['rol_id']) : [];
        if ($tipoCuenta === 'UsuarioInterno' && $permisos === []) {
            $auditoria->registrar('acceso.fallido', 'cuenta_acceso', $id, 'Rechazado', 'Rol sin permisos para operar', null, null, $id, $cuenta['correo']);

            return null;
        }

        $nombre = ($tipoCuenta === 'Cliente'
            ? trim((string) ($cuenta['nombre_cliente'] ?? ''))
            : trim(($cuenta['nombre'] ?? '') . ' ' . ($cuenta['apellido'] ?? ''))) ?: $cuenta['correo'];
        $ultimoAcceso = $cuenta['ultimo_acceso'];

        $actualizacion = 'UPDATE dbo.cuentas_acceso SET intentos_fallidos = 0, inicio_ventana_intentos = NULL, ultimo_acceso = ?, version_fila = version_fila + 1';
        $parametros = [$this->ahoraUtc()];
        if (password_needs_rehash($cuenta['hash_contrasena'], PASSWORD_ARGON2ID)) {
            $actualizacion .= ', hash_contrasena = ?';
            $parametros[] = password_hash($contrasena, PASSWORD_ARGON2ID);
        }
        $this->db->query($actualizacion . ' WHERE id = ?', [...$parametros, $id]);

        $auditoria->registrar('acceso.inicio', 'cuenta_acceso', $id, 'Exitoso', null, null, null, $id, $nombre);
        $momento = time();

        return [
            'id' => $id,
            'tipo' => $tipoCuenta,
            'correo' => $cuenta['correo'],
            'nombre' => $nombre,
            'rol' => $cuenta['rol_codigo'],
            'rol_nombre' => $cuenta['rol_nombre'],
            'permisos' => $permisos,
            'cliente_id' => $tipoCuenta === 'Cliente' ? (int) $cuenta['cliente_id'] : null,
            'ultimo_acceso_anterior' => $ultimoAcceso,
            'version_seguridad' => (int) $cuenta['version_seguridad'],
            'inicio' => $momento,
            'ultima_actividad' => $momento,
        ];
    }

    /** @return list<string> */
    public function permisosDelRol(int $rolId): array
    {
        $filas = $this->db->query(
            'SELECT p.codigo FROM dbo.roles_permisos rp JOIN dbo.permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ? ORDER BY p.codigo',
            [$rolId],
        )->getResultArray();

        return array_column($filas, 'codigo');
    }

    /** CU-03 paso 2: el usuario tiene una jornada de caja abierta (que seguirá abierta, RN-59). */
    public function tieneJornadaAbierta(int $usuarioId): bool
    {
        return $this->db->table('jornadas_caja')->where('usuario_id', $usuarioId)->where('estado', 'Abierta')->countAllResults() > 0;
    }

    /** CU-03 paso 5: registra el cierre de sesión. */
    public function registrarCierre(int $cuentaId, string $nombre, bool $jornadaAbierta): void
    {
        (new AuditoriaService($this->db))->registrar('acceso.cierre', 'cuenta_acceso', $cuentaId, 'Exitoso', $jornadaAbierta ? 'Jornada de caja abierta' : null, null, null, $cuentaId, $nombre);
    }

    private function fecha(mixed $valor): ?DateTimeImmutable
    {
        return $valor === null || $valor === '' ? null : (new DateTimeImmutable((string) $valor))->setTimezone(new DateTimeZone('UTC'));
    }

    private function texto(DateTimeImmutable $d): string
    {
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.vP');
    }
}
