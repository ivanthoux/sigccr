<?php

declare(strict_types=1);

namespace Tests\Feature\Usuarios;

use App\Exceptions\ConflictoConcurrenciaException;
use App\Services\Usuarios\UsuarioService;
use Tests\Support\Feature\ClasesDeDatos;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-05 a IN-08 · Modificar, restablecer, desactivar, reactivar, asignar rol y localizar usuarios internos
 * (CU-06 a CU-10, CU-53). Plan de pruebas 7.9.2 y Anexo B.3.
 *
 * @internal
 */
final class OperacionesUsuarioTest extends IntegracionTestCase
{
    // ── IN-05 Modificar datos ────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosModificar(): array
    {
        $campos = RegistrarUsuarioTest::campos();
        unset($campos['rol']);

        return self::casosDelCatalogo('IN-05', $campos);
    }

    /**
     * @dataProvider casosModificar
     * @param array<string, mixed> $otros
     */
    public function testModificarCampoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario('Cajero');
        $servicio = new UsuarioService($this->db);
        $antes = $servicio->obtener($u['id']);
        // Otro campo cambia siempre, para que un valor igual al actual no responda «No hay cambios para guardar.»
        $otroCampo = $campo === 'apellido' ? 'nombre' : 'apellido';
        $datos = self::conValor([
            'nombre' => $antes['nombre'], 'apellido' => $antes['apellido'], 'correo' => $antes['correo'],
            'tipo_documento' => $antes['tipo_documento'], 'nro_documento' => $antes['nro_documento'],
            'version_fila' => (string) $antes['version_fila'], $otroCampo => 'Cambiado',
        ], $campo, $valor, $otros);

        $r = $this->enviar("admin/usuarios/{$u['id']}/editar", $datos);
        $despues = $servicio->obtener($u['id']);

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            if ($campo === 'correo') {
                // A4: el correo nuevo queda pendiente de verificación; hasta confirmarlo rige el anterior
                $this->assertSame($antes['correo'], $despues['correo']);
                $this->assertValorGuardado($esperado->guardado, $despues['correo_pendiente'], 'correo pendiente');
            } else {
                $this->assertValorGuardado($esperado->guardado, $despues[$campo], $campo);
            }
            $this->assertSame((int) $antes['version_fila'] + 1, (int) $despues['version_fila']);

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame((int) $antes['version_fila'], (int) $despues['version_fila'], 'Un rechazo no debe modificar el usuario');
        $this->assertSame($antes['apellido'], $despues['apellido']);
    }

    public function testModificarSinCambios(): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario();
        $a = (new UsuarioService($this->db))->obtener($u['id']);

        $this->enviar("admin/usuarios/{$u['id']}/editar", array_intersect_key($a, array_flip(['nombre', 'apellido', 'correo', 'tipo_documento', 'nro_documento', 'version_fila'])));

        $this->assertSame('No hay cambios para guardar.', $_SESSION['error'] ?? null);
    }

    /** IN-C06 e IN-C07: dos modificaciones con la misma versión; versión ausente, vacía, de texto o como lista. */
    public function testVersionDeFilaViejaOAusente(): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario();
        $a = (new UsuarioService($this->db))->obtener($u['id']);
        $base = array_intersect_key($a, array_flip(['nombre', 'apellido', 'correo', 'tipo_documento', 'nro_documento']));
        $leida = (string) $a['version_fila'];

        $this->assertAceptado($this->enviar("admin/usuarios/{$u['id']}/editar", ['apellido' => 'Primera'] + $base + ['version_fila' => $leida]));
        foreach (['vieja' => $leida, 'vacía' => '', 'texto' => 'abc', 'como lista' => [$leida], 'ausente' => Esperado::AUSENTE] as $caso => $version) {
            $this->enviar("admin/usuarios/{$u['id']}/editar", self::conValor(['apellido' => 'Segunda'] + $base, 'version_fila', $version));
            $this->assertSame(ConflictoConcurrenciaException::MENSAJE, $_SESSION['error'] ?? null, "IN-C06/C07 {$caso}");
        }
        $this->assertSame('Primera', (new UsuarioService($this->db))->obtener($u['id'])['apellido']);
        $this->assertStringNotContainsString('cuentas_acceso', ConflictoConcurrenciaException::MENSAJE);
    }

    /** IN-C08: un usuario inexistente vuelve a la búsqueda con el mensaje. */
    public function testUsuarioInexistente(): void
    {
        $this->comoRol('Administrador');
        foreach (['', '/editar', '/desactivar', '/reactivar', '/rol', '/restablecer'] as $sufijo) {
            $r = $this->pedir("admin/usuarios/999999999{$sufijo}");
            $r->assertRedirectTo(site_url('admin/usuarios'));
            $this->assertSame('El usuario no existe.', $_SESSION['error'] ?? null, $sufijo);
        }
        foreach (['/editar', '/desactivar', '/reactivar', '/rol'] as $sufijo) {
            $this->enviar("admin/usuarios/999999999{$sufijo}", ['nombre' => 'Ana', 'apellido' => 'Prueba', 'motivo' => 'Prueba de inexistente', 'rol' => 'Cajero', 'version_fila' => '1']);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), "POST {$sufijo}");
        }
    }

    // ── IN-06 Desactivar, reactivar y restablecer ───────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosMotivo(): array
    {
        $r = [];
        foreach (ClasesDeDatos::para(['tipo' => 'MOT', 'obligatorio' => true, 'mensajes' => ['obligatorio' => 'Indique el motivo de la operación (al menos 5 caracteres).']]) as $c) {
            $r["IN-06 · motivo · {$c[0]} {$c[1]}"] = ['motivo', $c[2], $c[3], []];
        }

        return $r;
    }

    /**
     * MOT-01 a MOT-03 al desactivar: un motivo válido desactiva (baja lógica) y queda en la auditoría.
     *
     * @dataProvider casosMotivo
     * @param array<string, mixed> $otros
     */
    public function testDesactivarConCadaClaseDeMotivo(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario();
        $version = (string) (new UsuarioService($this->db))->obtener($u['id'])['version_fila'];

        $r = $this->enviar("admin/usuarios/{$u['id']}/desactivar", self::conValor(['version_fila' => $version], 'motivo', $valor));
        $despues = (new UsuarioService($this->db))->obtener($u['id']);

        if ($esperado->acepta) {
            $this->assertAceptado($r, "motivo: {$esperado}");
            $this->assertSame('Desactivada', $despues['estado']);
            $this->assertSame(1, $this->contar('usuarios_internos', ['id' => $u['id']]), 'La baja es lógica: la fila se conserva');
            $fila = $this->db->table('registros_auditoria')->where('accion', 'usuarios.desactivar')->where('objeto_id', (string) $u['id'])->get()->getRowArray();
            $this->assertValorGuardado($esperado->guardado, $fila['motivo'], 'motivo en la auditoría');

            return;
        }
        $this->assertCampoRechazado($r, 'motivo', $esperado->mensaje, "motivo: {$esperado}");
        $this->assertSame('Habilitada', $despues['estado']);
    }

    /** RN-04: al desactivar, la sesión abierta del usuario deja de ser válida y no puede volver a ingresar. */
    public function testDesactivarCierraLasSesionesYImpideIngresar(): void
    {
        $admin = $this->comoRol('Administrador');
        $u = $this->fabrica->usuario('Cajero');
        $sesionDelCajero = $this->comoUsuario($u);
        $this->comoUsuario($admin);
        $version = (string) (new UsuarioService($this->db))->obtener($u['id'])['version_fila'];

        $this->assertAceptado($this->enviar("admin/usuarios/{$u['id']}/desactivar", ['motivo' => 'Fin de la relación laboral', 'version_fila' => $version]));

        // La sesión que el Cajero tenía abierta ya no sirve
        $this->sinSesion();
        $this->cambiarSesion(['cuenta' => $sesionDelCajero]);
        $this->pedir('admin')->assertRedirectTo(site_url('admin/login'));
        $this->assertSame('La sesión ya no es válida. Ingrese nuevamente.', $_SESSION['aviso'] ?? null);

        // Y no puede volver a iniciar sesión: el rechazo es el genérico (RN-62)
        $this->sinSesion();
        $this->enviar('admin/login', ['correo' => $u['correo'], 'contrasena' => $u['clave']]);
        $this->assertSame('Usuario o contraseña incorrectos.', $_SESSION['error'] ?? null);
    }

    /** Reglas de estado (IN-C11, RN-04, RN-05): propia cuenta, último Administrador, ya desactivado, no desactivado. */
    public function testReglasDeEstado(): void
    {
        $admin = $this->comoRol('Administrador');
        $servicio = new UsuarioService($this->db);
        $v = static fn (array $u): string => (string) $u['version_fila'];

        $this->enviar("admin/usuarios/{$admin['id']}/desactivar", ['motivo' => 'Intento propio', 'version_fila' => $v($servicio->obtener($admin['id']))]);
        $this->assertStringContainsString('No puede desactivar su propia cuenta.', (string) ($_SESSION['error'] ?? json_encode($_SESSION['errores'] ?? [])));

        $u = $this->fabrica->usuario();
        $this->enviar("admin/usuarios/{$u['id']}/reactivar", ['motivo' => 'No corresponde', 'version_fila' => $v($servicio->obtener($u['id']))]);
        $this->assertSame('El usuario no está desactivado.', $_SESSION['error'] ?? null);

        $this->assertAceptado($this->enviar("admin/usuarios/{$u['id']}/desactivar", ['motivo' => 'Licencia prolongada', 'version_fila' => $v($servicio->obtener($u['id']))]));
        $this->enviar("admin/usuarios/{$u['id']}/desactivar", ['motivo' => 'Otra vez', 'version_fila' => $v($servicio->obtener($u['id']))]);
        $this->assertStringContainsString('El usuario ya está desactivado.', (string) ($_SESSION['error'] ?? ''));
        $this->enviar("admin/usuarios/{$u['id']}/restablecer", []);
        $this->assertSame('El usuario está desactivado: reactívelo antes de restablecer sus credenciales.', $_SESSION['error'] ?? null);
        $this->enviar("admin/usuarios/{$u['id']}/editar", ['nombre' => 'Otro', 'apellido' => 'Nombre', 'correo' => $u['correo'], 'tipo_documento' => 'DNI', 'nro_documento' => (string) Fabrica::dni(), 'version_fila' => $v($servicio->obtener($u['id']))]);
        $this->assertSame('El usuario está desactivado: reactívelo antes de modificar sus datos.', $_SESSION['error'] ?? null);

        // Reactivar: vuelve a Habilitada porque tiene contraseña (CU-09)
        $this->assertAceptado($this->enviar("admin/usuarios/{$u['id']}/reactivar", ['motivo' => 'Regreso de la licencia', 'version_fila' => $v($servicio->obtener($u['id']))]));
        $this->assertSame('Habilitada', $servicio->obtener($u['id'])['estado']);
    }

    // ── IN-07 Asignar rol ────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosRol(): array
    {
        return self::casosDelCatalogo('IN-07', ['rol' => [
            'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['Administrador', 'Gerente', 'AnalistaCredito'],
            'mensajes' => ['obligatorio' => 'Seleccione un rol válido.', 'formato' => 'Seleccione un rol válido.'],
        ]]);
    }

    /**
     * Un Cajero sin jornada ni caja asignada pasa a otro rol; su sesión anterior deja de ser válida (RN-06).
     *
     * @dataProvider casosRol
     * @param array<string, mixed> $otros
     */
    public function testAsignarRolConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario('Cajero');
        $servicio = new UsuarioService($this->db);
        $antes = $servicio->obtener($u['id']);
        $seguridad = (int) $this->db->table('cuentas_acceso')->select('version_seguridad')->where('id', $u['id'])->get()->getRowArray()['version_seguridad'];

        $r = $this->enviar("admin/usuarios/{$u['id']}/rol", self::conValor(['motivo' => 'Cambio de funciones', 'version_fila' => (string) $antes['version_fila']], 'rol', $valor));
        $despues = $servicio->obtener($u['id']);
        $seguridadDespues = (int) $this->db->table('cuentas_acceso')->select('version_seguridad')->where('id', $u['id'])->get()->getRowArray()['version_seguridad'];

        if ($esperado->acepta) {
            $this->assertAceptado($r, "rol: {$esperado}");
            $this->assertSame($esperado->guardado, $despues['rol']);
            $this->assertGreaterThan($seguridad, $seguridadDespues, 'La sesión anterior debe invalidarse');

            return;
        }
        $this->assertCampoRechazado($r, 'rol', $esperado->mensaje, "rol: {$esperado}");
        $this->assertSame('Cajero', $despues['rol']);
        $this->assertSame($seguridad, $seguridadDespues);
    }

    public function testAsignarElMismoRol(): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario('Cajero');
        $version = (string) (new UsuarioService($this->db))->obtener($u['id'])['version_fila'];

        $this->enviar("admin/usuarios/{$u['id']}/rol", ['rol' => 'Cajero', 'motivo' => 'Sin cambios', 'version_fila' => $version]);

        $this->assertSame('El usuario ya tiene ese rol: no hay cambios por aplicar.', $_SESSION['aviso'] ?? null);
        $this->assertSame((int) $version, (int) (new UsuarioService($this->db))->obtener($u['id'])['version_fila']);
    }

    // ── IN-08 Localizar ──────────────────────────────────────────────────────────────────────────────────────────

    /** FIL-01 a FIL-05: la búsqueda responde sin error con cualquier filtro y escapa lo ingresado. */
    public function testBusquedaConCadaClaseDeFiltro(): void
    {
        $this->comoRol('Administrador');
        $u = $this->fabrica->usuario();
        $casos = [
            'sin filtros' => [],
            'texto como lista' => ['texto' => ['x']],
            'todos como lista' => ['texto' => ['x'], 'documento' => ['1'], 'correo' => ['a'], 'rol' => ['Cajero'], 'estado' => ['Habilitada']],
            'comodín %' => ['texto' => '%'],
            'comodín _' => ['texto' => '_'],
            'corchetes' => ['texto' => '[a]'],
            'rol inexistente' => ['rol' => 'Inexistente'],
            'estado inexistente' => ['estado' => 'Inexistente'],
            'HTML' => ['texto' => '<script>alert(1)</script>'],
            'SQL' => ['texto' => "' OR 1=1 --"],
        ];
        foreach ($casos as $caso => $filtros) {
            $r = $this->pedir('admin/usuarios', $filtros);
            $r->assertOK();
            $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $r->response()->getBody(), $caso);
        }
        // Los comodines se buscan como texto: «%» no devuelve a todos los usuarios
        $this->assertStringNotContainsString($u['correo'], (string) $this->pedir('admin/usuarios', ['texto' => '%'])->response()->getBody());
        $this->assertStringContainsString($u['correo'], (string) $this->pedir('admin/usuarios', ['correo' => $u['correo']])->response()->getBody());
    }
}
