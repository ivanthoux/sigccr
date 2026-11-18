<?php

declare(strict_types=1);

namespace App\Services\Usuarios;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Auditoria\Cambios;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorUsuarioInterno;
use App\Libraries\Seguridad\ReglasUsuarios;
use App\Services\Acceso\AutorizacionService;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;

/**
 * Usuarios internos (SS-01): CU-05 a CU-10 y CU-53. RN-03 a RN-06, RN-08.
 *
 * La versión de fila de la cuenta (cuentas_acceso.version_fila) protege las ediciones concurrentes de los datos del
 * usuario y de su cuenta (RN-24). Incrementar version_seguridad finaliza las sesiones abiertas del usuario.
 */
class UsuarioService extends BaseService
{
    public const TIPOS_DOCUMENTO = Campos::TIPOS_DOCUMENTO;
    public const ESTADOS = [
        'PendienteActivacion' => 'Pendiente de activación',
        'Habilitada' => 'Habilitado',
        'BloqueadaTemporalmente' => 'Bloqueado temporalmente',
        'Desactivada' => 'Desactivado',
    ];

    /** Ruta del formulario para definir la contraseña y de la verificación de correo. */
    private const RUTA_CONTRASENA = 'admin/restablecer';
    private const RUTA_VERIFICACION = 'admin/verificar-correo';

    /**
     * CU-53 Localizar Usuario Interno.
     *
     * @param array{texto?: string, documento?: string, correo?: string, rol?: string, estado?: string} $criterios
     * @return list<array<string, mixed>>
     */
    public function buscar(array $criterios): array
    {
        $this->liberarBloqueosVencidos();
        $b = $this->db->table('usuarios_internos ui')
            ->select('ui.id, ui.nombre, ui.apellido, ui.tipo_documento, ui.nro_documento, ca.correo, ca.estado, r.codigo AS rol, r.nombre AS rol_nombre')
            ->join('cuentas_acceso ca', 'ca.id = ui.id')
            ->join('roles r', 'r.id = ui.rol_id');
        // Cada palabra debe aparecer en el nombre o en el apellido ("ana gomez" encuentra a Ana María Gómez)
        foreach (preg_split('/\s+/u', trim($criterios['texto'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $b->groupStart()->like('ui.nombre', Campos::literalLike((string) $palabra))->orLike('ui.apellido', Campos::literalLike((string) $palabra))->groupEnd();
        }
        if (($criterios['documento'] ?? '') !== '') {
            $b->where('ui.nro_documento', Campos::documentoFlexible($criterios['documento']));
        }
        if (($criterios['correo'] ?? '') !== '') {
            $b->like('ca.correo', Campos::literalLike((string) mb_strtolower(trim($criterios['correo']))));
        }
        if (in_array($criterios['rol'] ?? '', ReglasUsuarios::ROLES, true)) {
            $b->where('r.codigo', $criterios['rol']);
        }
        if (array_key_exists($criterios['estado'] ?? '', self::ESTADOS)) {
            $b->where('ca.estado', $criterios['estado']);
        }

        return $b->orderBy('ui.apellido')->orderBy('ui.nombre')->get(500)->getResultArray();
    }

    /** @return list<array{codigo: string, nombre: string}> */
    public function roles(): array
    {
        return $this->db->table('roles')->select('codigo, nombre')->whereIn('codigo', ReglasUsuarios::ROLES)->orderBy('id')->get()->getResultArray();
    }

    /**
     * Detalle del usuario (CU-53 paso 6) con su correo pendiente de verificación y la fecha de desactivación.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $this->liberarBloqueosVencidos();
        $u = $this->db->query(
            'SELECT ui.id, ui.nombre, ui.apellido, ui.tipo_documento, ui.nro_documento, ca.correo, ca.correo_verificado, ca.estado, '
            . 'ca.ultimo_acceso, ca.creado_en, ca.version_fila, CASE WHEN ca.hash_contrasena IS NULL THEN 0 ELSE 1 END AS tiene_contrasena, '
            . 'r.codigo AS rol, r.nombre AS rol_nombre '
            . 'FROM dbo.usuarios_internos ui JOIN dbo.cuentas_acceso ca ON ca.id = ui.id JOIN dbo.roles r ON r.id = ui.rol_id WHERE ui.id = ?',
            [$id],
        )->getRowArray();
        if ($u === null) {
            throw new ReglaNegocioException('El usuario no existe.');
        }
        $u['correo_pendiente'] = $this->db->query(
            "SELECT TOP 1 correo_destino FROM dbo.autorizaciones_temporales WHERE cuenta_acceso_id = ? AND proposito = 'VerificacionCorreo' "
            . "AND estado = 'Vigente' AND vence > SYSDATETIMEOFFSET() ORDER BY id DESC",
            [$id],
        )->getRowArray()['correo_destino'] ?? null;
        $u['desactivado_en'] = $u['estado'] !== 'Desactivada' ? null : ($this->db->query(
            "SELECT TOP 1 fecha_hora FROM dbo.registros_auditoria WHERE accion = 'usuarios.desactivar' AND objeto_tipo = 'usuarios_internos' "
            . "AND objeto_id = ? AND resultado = 'Exitoso' ORDER BY id DESC",
            [(string) $id],
        )->getRowArray()['fecha_hora'] ?? null);

        return $u;
    }

    /**
     * CU-05 Registrar Usuario Interno.
     *
     * @param array<string, mixed> $datos
     */
    public function registrar(array $datos): int
    {
        $d = ValidadorUsuarioInterno::normalizar($datos);
        $this->exigirValidos(ValidadorUsuarioInterno::validar($d, true));
        [$id, $comunicacionId] = $this->transaccion(function () use ($d): array {
            $this->verificarUnicidad($d['correo'], $d['tipo_documento'], $d['nro_documento'], null);
            $rolId = $this->rolId($d['rol']);
            $this->db->table('cuentas_acceso')->insert([
                'tipo_cuenta' => 'UsuarioInterno',
                'correo' => $d['correo'],
                'correo_verificado' => 0,
                'estado' => 'PendienteActivacion',
                'creado_en' => $this->ahoraUtc(),
            ]);
            $id = (int) $this->db->insertID();
            $this->db->table('usuarios_internos')->insert([
                'id' => $id, 'rol_id' => $rolId, 'nombre' => $d['nombre'], 'apellido' => $d['apellido'],
                'tipo_documento' => $d['tipo_documento'], 'nro_documento' => $d['nro_documento'],
            ]);
            // Paso 7: autorización de definición inicial (24 horas)
            $comunicacionId = (new AutorizacionService($this->db))->emitir($id, 'DefinicionInicial', $d['correo'], $d['nombre'], self::RUTA_CONTRASENA);
            (new AuditoriaService($this->db))->registrar('usuarios.registrar', 'usuarios_internos', $id, 'Exitoso', null, [
                'nombre' => $d['nombre'], 'apellido' => $d['apellido'], 'correo' => $d['correo'],
                'documento' => "{$d['tipo_documento']} {$d['nro_documento']}", 'rol' => $d['rol'],
            ]);

            return [$id, $comunicacionId];
        });
        (new ComunicacionService())->enviar($comunicacionId);

        return $id;
    }

    /**
     * CU-06 Modificar Datos de Usuario. El rol y el estado no se modifican acá. Si cambia el correo, la dirección
     * nueva queda pendiente de verificación y la anterior sigue siendo la válida hasta confirmarla (A4).
     *
     * @param array<string, mixed> $datos
     * @return bool true si se envió una verificación de correo
     */
    public function modificar(int $id, int $versionLeida, array $datos): bool
    {
        $d = ValidadorUsuarioInterno::normalizar($datos);
        $this->exigirValidos(ValidadorUsuarioInterno::validar($d, false));
        $comunicacionId = $this->transaccion(function () use ($id, $versionLeida, $d): ?int {
            $u = $this->obtenerBloqueado($id);
            if ($u['estado'] === 'Desactivada') {
                throw new ReglaNegocioException('El usuario está desactivado: reactívelo antes de modificar sus datos.', 'RN-04');
            }
            $correoNuevo = $d['correo'] !== $u['correo'] ? $d['correo'] : null;
            $this->verificarUnicidad($correoNuevo, $d['tipo_documento'], $d['nro_documento'], $id);

            $cambios = Cambios::entre($u, $d, ['nombre', 'apellido', 'tipo_documento', 'nro_documento']);
            if ($cambios === [] && $correoNuevo === null) {
                throw new ReglaNegocioException('No hay cambios para guardar.');
            }
            $this->actualizarConVersion('cuentas_acceso', $id, $versionLeida, ['actualizado_en' => $this->ahoraUtc()]);
            if ($cambios !== []) {
                $this->db->table('usuarios_internos')->where('id', $id)->update(array_map(static fn ($c) => $c['nuevo'], $cambios));
            }
            $comunicacionId = null;
            if ($correoNuevo !== null) {
                $cambios['correo'] = ['anterior' => $u['correo'], 'nuevo' => "{$correoNuevo} (pendiente de verificación)"];
                $comunicacionId = (new AutorizacionService($this->db))->emitir($id, 'VerificacionCorreo', $correoNuevo, $d['nombre'], self::RUTA_VERIFICACION);
            }
            (new AuditoriaService($this->db))->registrarCambios('usuarios.modificar', 'usuarios_internos', $id, $cambios);

            return $comunicacionId;
        });
        if ($comunicacionId !== null) {
            (new ComunicacionService())->enviar($comunicacionId);
        }

        return $comunicacionId !== null;
    }

    /** CU-06 paso 7: el titular confirma el correo nuevo desde el enlace. */
    public function verificarCorreo(string $codigo): void
    {
        $this->transaccion(function () use ($codigo): void {
            $autorizaciones = new AutorizacionService($this->db);
            $a = $autorizaciones->buscarVigente($codigo, ['VerificacionCorreo'], true);
            if ($a === null) {
                throw new ReglaNegocioException('El enlace de verificación expiró o ya no es válido. Pida al Administrador que vuelva a registrar el correo.', 'RN-03');
            }
            $id = (int) $a['cuenta_acceso_id'];
            $correo = (string) $a['correo_destino'];
            $u = $this->obtenerBloqueado($id);
            $this->verificarUnicidad($correo, null, null, $id);
            $this->db->query(
                'UPDATE dbo.cuentas_acceso SET correo = ?, correo_verificado = 1, actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?',
                [$correo, $this->ahoraUtc(), $id],
            );
            $autorizaciones->marcarUtilizada((int) $a['id']);
            (new AuditoriaService($this->db))->registrar('usuarios.verificar_correo', 'usuarios_internos', $id, 'Exitoso', null, ['cambios' => Cambios::campo('correo', $u['correo'], $correo), 'correo_verificado' => true], null, $id, $correo);
        });
    }

    /**
     * CU-07 Restablecer Credenciales de Usuario: el titular define la contraseña nueva desde el enlace.
     *
     * @return string correo al que se envió la autorización
     */
    public function restablecerCredenciales(int $id): string
    {
        [$correo, $comunicacionId] = $this->transaccion(function () use ($id): array {
            $u = $this->obtenerBloqueado($id);
            if ($u['estado'] === 'Desactivada') {
                throw new ReglaNegocioException('El usuario está desactivado: reactívelo antes de restablecer sus credenciales.');
            }
            $pendiente = $u['estado'] === 'PendienteActivacion';
            if (!$pendiente && (int) $u['correo_verificado'] !== 1) {
                throw new ReglaNegocioException('El usuario no tiene un correo verificado. Actualice primero el correo con "Modificar datos".');
            }
            $proposito = $pendiente ? 'DefinicionInicial' : 'Restablecimiento';
            $comunicacionId = (new AutorizacionService($this->db))->emitir($id, $proposito, $u['correo'], $u['nombre'], self::RUTA_CONTRASENA);
            (new AuditoriaService($this->db))->registrar('usuarios.restablecer_credenciales', 'usuarios_internos', $id, 'Exitoso', null, ['proposito' => $proposito]);

            return [$u['correo'], $comunicacionId];
        });
        (new ComunicacionService())->enviar($comunicacionId);

        return $correo;
    }

    /**
     * CU-08 Desactivar Usuario (RN-04, RN-05).
     */
    public function desactivar(int $id, int $versionLeida, string $motivo, int $actuanteId): void
    {
        $motivo = $this->exigirMotivo($motivo);
        $this->transaccion(function () use ($id, $versionLeida, $motivo, $actuanteId): void {
            $u = $this->obtenerBloqueado($id);
            $rechazos = ReglasUsuarios::motivosContraDesactivar(
                $actuanteId,
                $id,
                $u['rol'],
                $u['estado'],
                $this->administradoresActivos(),
                $this->responsabilidadesPendientes($id),
            );
            if ($rechazos !== []) {
                throw new ReglaNegocioException(implode(' ', $rechazos), 'RN-04');
            }
            $this->actualizarConVersion('cuentas_acceso', $id, $versionLeida, ['estado' => 'Desactivada', 'actualizado_en' => $this->ahoraUtc()]);
            $this->db->query('UPDATE dbo.cuentas_acceso SET version_seguridad = version_seguridad + 1 WHERE id = ?', [$id]);
            (new AutorizacionService($this->db))->sustituirVigentes($id);
            (new AuditoriaService($this->db))->registrarCambios('usuarios.desactivar', 'usuarios_internos', $id, Cambios::campo('estado', $u['estado'], 'Desactivada'), $motivo);
        });
    }

    /** CU-09 Reactivar Usuario (RN-06). */
    public function reactivar(int $id, int $versionLeida, string $motivo): string
    {
        $motivo = $this->exigirMotivo($motivo);

        return $this->transaccion(function () use ($id, $versionLeida, $motivo): string {
            $u = $this->obtenerBloqueado($id);
            if ($u['estado'] !== 'Desactivada') {
                throw new ReglaNegocioException('El usuario no está desactivado.');
            }
            if (!in_array($u['rol'], ReglasUsuarios::ROLES, true)) {
                throw new ReglaNegocioException('El usuario no tiene un rol interno asignado: asígnelo antes de reactivarlo.', 'RN-06');
            }
            $estado = ReglasUsuarios::estadoAlReactivar((int) $u['tiene_contrasena'] === 1);
            $this->actualizarConVersion('cuentas_acceso', $id, $versionLeida, [
                'estado' => $estado, 'intentos_fallidos' => 0, 'inicio_ventana_intentos' => null, 'bloqueada_hasta' => null, 'actualizado_en' => $this->ahoraUtc(),
            ]);
            (new AuditoriaService($this->db))->registrarCambios('usuarios.reactivar', 'usuarios_internos', $id, Cambios::campo('estado', $u['estado'], $estado), $motivo);

            return $estado;
        });
    }

    /**
     * CU-10 Asignar Rol (RN-04, RN-05, RN-06). Las sesiones abiertas del usuario se finalizan para que no conserven
     * permisos retirados; al volver a ingresar se cargan los del rol nuevo.
     *
     * @return bool false si el rol elegido es el actual (A3: no hay cambios)
     */
    public function asignarRol(int $id, int $versionLeida, string $rolNuevo, string $motivo): bool
    {
        $motivo = $this->exigirMotivo($motivo);
        // Igual que en el registro (CU-05): el rol elegido se recorta antes de compararlo (D-49, SEL-04)
        $rolNuevo = Campos::texto($rolNuevo);

        return $this->transaccion(function () use ($id, $versionLeida, $rolNuevo, $motivo): bool {
            $u = $this->obtenerBloqueado($id);
            $rechazos = ReglasUsuarios::motivosContraCambiarRol(
                $u['rol'],
                $rolNuevo,
                $u['estado'],
                $this->administradoresActivos(),
                $this->db->table('jornadas_caja')->where('usuario_id', $id)->where('estado', 'Abierta')->countAllResults() > 0,
                $this->asignacionCajaVigente($id),
            );
            if ($rechazos === null) {
                return false;
            }
            if ($rechazos !== []) {
                throw new ValidacionException(['rol' => implode(' ', $rechazos)], 'RN-05');
            }
            $this->actualizarConVersion('cuentas_acceso', $id, $versionLeida, ['actualizado_en' => $this->ahoraUtc()]);
            $this->db->query('UPDATE dbo.cuentas_acceso SET version_seguridad = version_seguridad + 1 WHERE id = ?', [$id]);
            $this->db->table('usuarios_internos')->where('id', $id)->update(['rol_id' => $this->rolId($rolNuevo)]);
            (new AuditoriaService($this->db))->registrarCambios('usuarios.asignar_rol', 'usuarios_internos', $id, Cambios::campo('rol', $u['rol'], $rolNuevo), $motivo);

            return true;
        });
    }

    // ── Auxiliares ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function obtenerBloqueado(int $id): array
    {
        $u = $this->db->query(
            'SELECT ui.id, ui.nombre, ui.apellido, ui.tipo_documento, ui.nro_documento, ca.correo, ca.correo_verificado, ca.estado, '
            . 'CASE WHEN ca.hash_contrasena IS NULL THEN 0 ELSE 1 END AS tiene_contrasena, r.codigo AS rol '
            . 'FROM dbo.cuentas_acceso ca WITH (UPDLOCK, ROWLOCK) JOIN dbo.usuarios_internos ui ON ui.id = ca.id JOIN dbo.roles r ON r.id = ui.rol_id '
            . "WHERE ca.id = ? AND ca.tipo_cuenta = 'UsuarioInterno'",
            [$id],
        )->getRowArray();
        if ($u === null) {
            throw new ReglaNegocioException('El usuario no existe.');
        }

        return $u;
    }

    private function administradoresActivos(): int
    {
        return $this->db->table('usuarios_internos ui')->join('cuentas_acceso ca', 'ca.id = ui.id')->join('roles r', 'r.id = ui.rol_id')
            ->where('r.codigo', 'Administrador')->whereIn('ca.estado', ReglasUsuarios::ESTADOS_ACTIVOS)->countAllResults();
    }

    private function asignacionCajaVigente(int $id): bool
    {
        return $this->db->table('asignaciones_caja')->where('usuario_id', $id)
            ->groupStart()->where('hasta', null)->orWhere('hasta >=', $this->hoyNegocio())->groupEnd()->countAllResults() > 0;
    }

    /**
     * RN-04: responsabilidades que impiden desactivar al usuario.
     *
     * @return list<string>
     */
    private function responsabilidadesPendientes(int $id): array
    {
        $r = [];
        if ($this->db->table('jornadas_caja')->where('usuario_id', $id)->where('estado', 'Abierta')->countAllResults() > 0) {
            $r[] = 'una jornada de caja abierta (CU-36)';
        }
        if ($this->asignacionCajaVigente($id)) {
            $r[] = 'una caja asignada (CU-72)';
        }
        $devoluciones = $this->db->table('ejecuciones_devolucion e')->join('autorizaciones_devolucion a', 'a.id = e.autorizacion_devolucion_id')
            ->where('e.usuario_id', $id)->whereIn('a.estado', ['EnEjecucion', 'EnConciliacion'])->countAllResults();
        if ($devoluciones > 0) {
            $r[] = 'una devolución de saldo a favor en ejecución (CU-78)';
        }

        return $r;
    }

    private function rolId(string $codigo): int
    {
        $fila = $this->db->table('roles')->select('id')->where('codigo', $codigo)->get()->getRowArray();
        if ($fila === null || !in_array($codigo, ReglasUsuarios::ROLES, true)) {
            throw new ReglaNegocioException('Seleccione un rol válido.', 'RN-06');
        }

        return (int) $fila['id'];
    }

    /** RN-08: correo único entre las cuentas del panel; documento único entre los usuarios internos. */
    private function verificarUnicidad(?string $correo, ?string $tipoDoc, ?string $nroDoc, ?int $excluirId): void
    {
        $errores = [];
        if ($correo !== null) {
            $b = $this->db->table('cuentas_acceso')->where('tipo_cuenta', 'UsuarioInterno')->where('correo', $correo);
            if ($excluirId !== null) {
                $b->where('id !=', $excluirId);
            }
            if ($b->countAllResults() > 0) {
                $errores['correo'] = 'El correo electrónico ya está registrado para otro usuario.';
            }
        }
        if ($tipoDoc !== null && $nroDoc !== null) {
            $b = $this->db->table('usuarios_internos')->where('tipo_documento', $tipoDoc)->where('nro_documento', $nroDoc);
            if ($excluirId !== null) {
                $b->where('id !=', $excluirId);
            }
            if ($b->countAllResults() > 0) {
                $errores['nro_documento'] = 'El documento ya está registrado para otro usuario.';
            }
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-08');
        }
    }

    /** @param array<string, string> $errores */
    private function exigirValidos(array $errores): void
    {
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }
    }

    private function exigirMotivo(string $motivo): string
    {
        $motivo = Campos::texto($motivo);
        $error = Campos::motivo($motivo);
        if ($error !== null) {
            throw new ValidacionException(['motivo' => $error]);
        }

        return $motivo;
    }
}
