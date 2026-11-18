<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Libraries\Seguridad\PoliticaContrasena;
use App\Services\Acceso\AutorizacionService;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-01 a IN-03 (panel) e IN-15 a IN-17 (portal) · Inicio de sesión, recuperación, activación y definición de la
 * contraseña (CU-01, CU-02, CU-04). Plan de pruebas 7.9.2 y Anexos B.2 y B.5.
 *
 * @internal
 */
final class AccesoTest extends IntegracionTestCase
{
    private const VACIOS = 'Ingrese el correo electrónico y la contraseña.';
    private const RECHAZO = 'Usuario o contraseña incorrectos.';
    private const ENLACE_PANEL = 'El enlace expiró o ya no es válido. Solicite uno nuevo desde "¿Olvidó su contraseña?".';
    private const ENLACE_PORTAL = 'El enlace expiró o ya no es válido. Solicite uno nuevo desde «Activar o rehabilitar acceso».';

    /** @return array<string, array{0: string, 1: string}> ámbito => [prefijo, tipo de cuenta] */
    public static function ambitos(): array
    {
        return ['panel' => ['admin', 'UsuarioInterno'], 'portal' => ['portal', 'Cliente']];
    }

    /** @return array{correo: string, clave: string, id: int} */
    private function cuenta(string $tipo, string $proposito = 'Recuperacion'): array
    {
        if ($proposito === 'Activacion') {
            // La activación solo se completa sobre una cuenta pendiente (CU-04)
            $c = $this->fabrica->cliente();
            $id = (int) $this->db->table('clientes')->select('cuenta_cliente_id')->where('id', $c['id'])->get()->getRowArray()['cuenta_cliente_id'];

            return ['correo' => $c['datos']['correo'], 'clave' => '', 'id' => $id];
        }
        if ($tipo === 'Cliente') {
            $c = $this->fabrica->clienteConAcceso();

            return ['correo' => $c['correo'], 'clave' => $c['clave'], 'id' => $c['cuenta_id']];
        }

        return $this->fabrica->usuario();
    }

    // ── IN-01 e IN-16: inicio de sesión ──────────────────────────────────────────────────────────────────────────

    /**
     * Campos vacíos, ausentes o enviados como lista (COM-01, COM-02): el mensaje de datos incompletos.
     *
     * @dataProvider ambitos
     */
    public function testCamposVaciosAusentesOComoLista(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        $casos = [
            'ambos vacíos' => ['correo' => '', 'contrasena' => ''],
            'correo vacío' => ['correo' => '', 'contrasena' => $u['clave']],
            'correo con espacios' => ['correo' => '   ', 'contrasena' => $u['clave']],
            'contraseña vacía' => ['correo' => $u['correo'], 'contrasena' => ''],
            'sin campos' => [],
            'correo como lista' => ['correo' => [$u['correo']], 'contrasena' => $u['clave']],
            'contraseña como lista' => ['correo' => $u['correo'], 'contrasena' => [$u['clave']]],
        ];
        foreach ($casos as $caso => $datos) {
            $r = $this->enviar("{$p}/login", $datos);
            $this->assertTrue($r->isRedirect(), $caso);
            $this->assertSame(self::VACIOS, $_SESSION['error'] ?? null, $caso);
            $this->assertArrayNotHasKey('cuenta', $_SESSION, $caso);
        }
    }

    /**
     * COR-02 y COR-03: el correo se compara en minúsculas y sin espacios en los extremos.
     *
     * @dataProvider ambitos
     */
    public function testCorreoEnMayusculasYConEspacios(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        foreach ([mb_strtoupper($u['correo']), "  {$u['correo']}  "] as $correo) {
            $r = $this->enviar("{$p}/login", ['correo' => $correo, 'contrasena' => $u['clave']]);
            $r->assertRedirectTo(site_url($p === 'admin' ? 'admin' : 'portal'));
            $this->assertSame($tipo, $_SESSION['cuenta']['tipo'] ?? null, $correo);
        }
    }

    /**
     * PWD-06: la contraseña no se recorta; con espacios agregados, o en mayúsculas, no coincide.
     *
     * @dataProvider ambitos
     */
    public function testLaContrasenaNoSeNormaliza(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        foreach ([" {$u['clave']}", "{$u['clave']} ", mb_strtoupper($u['clave'])] as $clave) {
            $this->enviar("{$p}/login", ['correo' => $u['correo'], 'contrasena' => $clave]);
            $this->assertSame(self::RECHAZO, $_SESSION['error'] ?? null, var_export($clave, true));
        }
    }

    /**
     * F-03 y F-04 (RN-62): la misma secuencia de mensajes con una cuenta existente y con un correo inexistente; al
     * quinto fallo la cuenta queda bloqueada aun con la contraseña correcta, y el bloqueo se audita.
     *
     * @dataProvider ambitos
     */
    public function testBloqueoPorIntentosSinRevelarLaCuenta(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        $existente = [];
        $inexistente = [];
        for ($i = 1; $i <= 5; $i++) {
            $this->enviar("{$p}/login", ['correo' => $u['correo'], 'contrasena' => 'Incorrecta.Larga.2026']);
            $existente[] = $_SESSION['error'] ?? null;
            $this->enviar("{$p}/login", ['correo' => 'nadie.' . Fabrica::sufijo() . '@prueba.local', 'contrasena' => 'Incorrecta.Larga.2026']);
            $inexistente[] = $_SESSION['error'] ?? null;
        }

        $this->assertSame(self::RECHAZO, $existente[0]);
        $this->assertSame(self::RECHAZO, $existente[1]);
        $this->assertStringStartsWith(self::RECHAZO . ' Le quedan 2 intentos', (string) $existente[2]);
        $this->assertStringStartsWith(self::RECHAZO . ' Le queda un intento', (string) $existente[3]);
        $this->assertStringStartsWith('Por seguridad, el acceso quedó bloqueado durante 15 minutos', (string) $existente[4]);
        $this->assertSame('BloqueadaTemporalmente', $this->db->table('cuentas_acceso')->select('estado')->where('id', $u['id'])->get()->getRowArray()['estado']);
        $this->assertSame(1, $this->contar('registros_auditoria', ['accion' => 'acceso.bloqueo', 'objeto_id' => (string) $u['id']]));

        $this->enviar("{$p}/login", ['correo' => $u['correo'], 'contrasena' => $u['clave']]);
        $this->assertStringStartsWith('Por seguridad, el acceso quedó bloqueado', (string) ($_SESSION['error'] ?? ''));
        $this->assertArrayNotHasKey('cuenta', $_SESSION);
    }

    /** RN-08 e IN-C03: una cuenta del panel no inicia sesión en el portal ni al revés. */
    public function testCadaCuentaSoloEnSuAmbito(): void
    {
        $usuario = $this->fabrica->usuario();
        $cliente = $this->fabrica->clienteConAcceso();

        $this->enviar('portal/login', ['correo' => $usuario['correo'], 'contrasena' => $usuario['clave']]);
        $this->assertSame(self::RECHAZO, $_SESSION['error'] ?? null);
        $this->enviar('admin/login', ['correo' => $cliente['correo'], 'contrasena' => $cliente['clave']]);
        $this->assertSame(self::RECHAZO, $_SESSION['error'] ?? null);
    }

    /** RN-10: un cliente desactivado recibe el mensaje genérico aunque la contraseña sea correcta. */
    public function testClienteDesactivadoNoIngresaAlPortal(): void
    {
        $c = $this->fabrica->clienteConAcceso();
        $this->db->table('clientes')->where('id', $c['id'])->update(['estado' => 'Desactivado']);

        $this->enviar('portal/login', ['correo' => $c['correo'], 'contrasena' => $c['clave']]);

        $this->assertSame(self::RECHAZO, $_SESSION['error'] ?? null);
        $this->assertArrayNotHasKey('cuenta', $_SESSION);
    }

    /**
     * IN-C05: sin token CSRF el inicio de sesión no se procesa, aun con credenciales correctas.
     *
     * @dataProvider ambitos
     */
    public function testInicioDeSesionSinToken(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        foreach ([false, 'alterado', 'otra'] as $token) {
            $this->enviar("{$p}/login", ['correo' => $u['correo'], 'contrasena' => $u['clave']], $token);
            $this->assertSame(self::MENSAJE_CSRF, $_SESSION['error'] ?? null, var_export($token, true));
            $this->assertArrayNotHasKey('cuenta', $_SESSION);
        }
    }

    /**
     * IN-C14 y PWD-07: la contraseña nunca queda en la auditoría.
     *
     * @dataProvider ambitos
     */
    public function testLaContrasenaNoQuedaEnLaAuditoria(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        $desde = $this->ultimoId('registros_auditoria');
        $this->enviar("{$p}/login", ['correo' => $u['correo'], 'contrasena' => 'Otra.Clave.Equivocada']);
        $this->enviar("{$p}/login", ['correo' => $u['correo'], 'contrasena' => $u['clave']]);

        $filas = $this->db->table('registros_auditoria')->where('id >', $desde)->get()->getResultArray();
        $this->assertNotEmpty($filas);
        $texto = json_encode($filas, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($u['clave'], $texto);
        $this->assertStringNotContainsString('Otra.Clave.Equivocada', $texto);
        $this->assertStringNotContainsString('$argon2id$', $texto);
    }

    // ── IN-02 e IN-16: recuperación ──────────────────────────────────────────────────────────────────────────────

    /** @dataProvider ambitos */
    public function testRecuperarConCorreoInvalido(string $p, string $tipo): void
    {
        foreach (['', '   ', 'ana', 'ana@', 'ana@prueba', ['x@prueba.local']] as $correo) {
            $this->enviar("{$p}/recuperar", ['correo' => $correo]);
            $this->assertSame('Ingrese un correo electrónico válido.', $_SESSION['error'] ?? null, var_export($correo, true));
        }
    }

    /**
     * El mismo mensaje exista o no la cuenta; solo la existente recibe una autorización (RN-62).
     *
     * @dataProvider ambitos
     */
    public function testRecuperarNoRevelaSiLaCuentaExiste(string $p, string $tipo): void
    {
        $u = $this->cuenta($tipo);
        $antes = $this->contar('autorizaciones_temporales', ['cuenta_acceso_id' => $u['id']]);

        $this->enviar("{$p}/recuperar", ['correo' => $u['correo']]);
        $existe = $_SESSION['exito'] ?? null;
        $this->enviar("{$p}/recuperar", ['correo' => 'nadie.' . Fabrica::sufijo() . '@prueba.local']);
        $noExiste = $_SESSION['exito'] ?? null;

        $this->assertNotNull($existe);
        $this->assertSame($existe, $noExiste);
        $this->assertSame($antes + 1, $this->contar('autorizaciones_temporales', ['cuenta_acceso_id' => $u['id']]));
    }

    // ── IN-03, IN-16 e IN-17: definir la contraseña desde un enlace ─────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function enlaces(): array
    {
        return [
            'panel, recuperación' => ['admin/restablecer', 'UsuarioInterno', 'Recuperacion', self::ENLACE_PANEL],
            'portal, recuperación' => ['portal/restablecer', 'Cliente', 'Recuperacion', self::ENLACE_PANEL],
            'portal, activación' => ['portal/activar', 'Cliente', 'Activacion', self::ENLACE_PORTAL],
        ];
    }

    /**
     * PWD-01 a PWD-05: cada clase de contraseña con un enlace vigente.
     *
     * @dataProvider enlaces
     */
    public function testContrasenaConCadaClase(string $ruta, string $tipo, string $proposito, string $invalido): void
    {
        $u = $this->cuenta($tipo, $proposito);
        $comprometida = 'password1234567';
        $casos = [
            'PWD-01 14 caracteres' => [str_repeat('a', 13) . 'Z', null, 'La contraseña debe tener entre 15 y 128 caracteres.'],
            'PWD-02 129 caracteres' => [str_repeat('Ab1.', 32) . 'x', null, 'La contraseña debe tener entre 15 y 128 caracteres.'],
            'PWD-03 comprometida' => [$comprometida, null, 'La contraseña figura en la lista de contraseñas comprometidas. Elija otra.'],
            'PWD-03 comprometida en mayúsculas' => [mb_strtoupper($comprometida), null, 'La contraseña figura en la lista de contraseñas comprometidas. Elija otra.'],
            'PWD-04 confirmación distinta' => ['Una.Clave.Valida.2026', 'Una.Clave.Valida.2027', 'La confirmación no coincide con la contraseña.'],
            'PWD-04 confirmación vacía' => ['Una.Clave.Valida.2026', '', 'La confirmación no coincide con la contraseña.'],
            'COM-02 como lista' => [['Una.Clave.Valida.2026'], ['Una.Clave.Valida.2026'], 'La contraseña debe tener entre 15 y 128 caracteres.'],
        ];
        foreach ($casos as $caso => [$clave, $confirmacion, $mensaje]) {
            $codigo = (new AutorizacionService($this->db))->emitirSinComunicacion($u['id'], $proposito, $u['correo']);
            $this->pedir("{$ruta}/{$codigo}")->assertOK();
            $this->enviar("{$ruta}/{$codigo}", ['contrasena' => $clave, 'confirmacion' => $confirmacion ?? $clave]);
            $this->assertStringContainsString($mensaje, (string) ($_SESSION['error'] ?? ''), $caso);
            $this->assertNotNull((new AutorizacionService($this->db))->buscarVigente($codigo, [$proposito], false), "{$caso}: el enlace debe seguir vigente");
        }

        // PWD-02 y PWD-05: 15 caracteres con tildes, eñe y emoji, y 128 caracteres, se aceptan; el enlace queda usado
        foreach (['Ñandú.Pingüino😀1', str_repeat('Ab1.', 32)] as $valida) {
            $this->assertSame([], array_values(array_filter(PoliticaContrasena::validar($valida, $valida))));
            $codigo = (new AutorizacionService($this->db))->emitirSinComunicacion($u['id'], $proposito, $u['correo']);
            $r = $this->enviar("{$ruta}/{$codigo}", ['contrasena' => $valida, 'confirmacion' => $valida]);
            $this->assertSame('', (string) ($_SESSION['error'] ?? ''), mb_strlen($valida) . ' caracteres');
            $this->assertTrue($r->isRedirect());
            $this->assertNull((new AutorizacionService($this->db))->buscarVigente($codigo, [$proposito], false), 'El enlace debe quedar usado');
            $hash = (string) $this->db->table('cuentas_acceso')->select('hash_contrasena')->where('id', $u['id'])->get()->getRowArray()['hash_contrasena'];
            $this->assertTrue(password_verify($valida, $hash));
            $this->assertStringStartsWith('$argon2id$', $hash);
            if ($proposito === 'Activacion') {
                // Vuelve a pendiente para probar la contraseña siguiente con otra activación
                $this->db->table('cuentas_acceso')->where('id', $u['id'])->update(['estado' => 'PendienteActivacion']);
            }
        }
    }

    /**
     * TOK-02 a TOK-04: un código alterado, usado, reemplazado o de otro propósito muestra el mensaje de enlace inválido.
     *
     * @dataProvider enlaces
     */
    public function testCodigoNoVigente(string $ruta, string $tipo, string $proposito, string $invalido): void
    {
        $u = $this->cuenta($tipo, $proposito);
        $autorizaciones = new AutorizacionService($this->db);
        $vigente = $autorizaciones->emitirSinComunicacion($u['id'], $proposito, $u['correo']);
        $otroProposito = $autorizaciones->emitirSinComunicacion($u['id'], $proposito === 'Activacion' ? 'Recuperacion' : 'Activacion', $u['correo']);
        $codigos = [
            'alterado' => substr($vigente, 0, -1) . ($vigente[-1] === 'A' ? 'B' : 'A'),
            'de 1 carácter' => 'x',
            'de 500 caracteres' => str_repeat('a', 500),
            'de otro propósito' => $otroProposito,
        ];
        foreach ($codigos as $caso => $codigo) {
            $r = $this->pedir("{$ruta}/{$codigo}");
            $this->assertTrue($r->isRedirect(), $caso);
            $this->assertSame($invalido, $_SESSION['error'] ?? null, "GET {$caso}");
            $this->enviar("{$ruta}/{$codigo}", ['contrasena' => 'Una.Clave.Valida.2026', 'confirmacion' => 'Una.Clave.Valida.2026']);
            $this->assertSame($invalido, $_SESSION['error'] ?? null, "POST {$caso}");
        }

        // Usado: después de definir la contraseña, el mismo código ya no sirve
        $this->enviar("{$ruta}/{$vigente}", ['contrasena' => 'Una.Clave.Valida.2026', 'confirmacion' => 'Una.Clave.Valida.2026']);
        $this->enviar("{$ruta}/{$vigente}", ['contrasena' => 'Otra.Clave.Valida.2026', 'confirmacion' => 'Otra.Clave.Valida.2026']);
        $this->assertSame($invalido, $_SESSION['error'] ?? null, 'usado');

        // Reemplazado: un enlace nuevo deja sin efecto al anterior (RN-03, F-53)
        if ($proposito === 'Recuperacion') {
            $primero = $autorizaciones->emitirSinComunicacion($u['id'], $proposito, $u['correo']);
            $this->enviar(($tipo === 'Cliente' ? 'portal' : 'admin') . '/recuperar', ['correo' => $u['correo']]);
            $this->pedir("{$ruta}/{$primero}");
            $this->assertSame($invalido, $_SESSION['error'] ?? null, 'reemplazado');
        }
    }

    // ── IN-15: activar o rehabilitar el acceso al portal ─────────────────────────────────────────────────────────

    /** Campos vacíos o un correo sin formato: el mensaje de datos incompletos, sin consultar el padrón. */
    public function testActivacionConDatosIncompletos(): void
    {
        $c = $this->fabrica->cliente();
        $dni = $c['datos']['nro_documento'];
        $correo = $c['datos']['correo'];
        $casos = [
            'todo vacío' => ['tipo_documento' => '', 'numero' => '', 'correo' => ''],
            'número vacío' => ['tipo_documento' => 'DNI', 'numero' => '   ', 'correo' => $correo],
            'correo vacío' => ['tipo_documento' => 'DNI', 'numero' => $dni, 'correo' => ''],
            'correo sin dominio' => ['tipo_documento' => 'DNI', 'numero' => $dni, 'correo' => 'ana@'],
            'campos como lista' => ['tipo_documento' => ['DNI'], 'numero' => [$dni], 'correo' => [$correo]],
            'sin campos' => [],
        ];
        foreach ($casos as $caso => $datos) {
            $this->enviar('portal/activar', $datos);
            $this->assertSame('Ingrese su documento (o CUIT) y un correo electrónico válido.', $_SESSION['error'] ?? null, $caso);
        }
    }

    /** Un tipo o número de documento que no identifica a un cliente: el mensaje de no verificado, sin enlace (RN-10). */
    public function testActivacionConIdentificacionInvalida(): void
    {
        $c = $this->fabrica->cliente();
        $dni = $c['datos']['nro_documento'];
        $correo = $c['datos']['correo'];
        $cuentaId = (int) $this->db->table('clientes')->select('cuenta_cliente_id')->where('id', $c['id'])->get()->getRowArray()['cuenta_cliente_id'];
        $casos = [
            'sin tipo' => ['tipo_documento' => '', 'numero' => $dni, 'correo' => $correo],
            'tipo inexistente' => ['tipo_documento' => 'Cédula', 'numero' => $dni, 'correo' => $correo],
            'número de 5 dígitos' => ['tipo_documento' => 'DNI', 'numero' => '12345', 'correo' => $correo],
            'CUIT con verificador incorrecto' => ['tipo_documento' => 'CUIT', 'numero' => substr($c['datos']['cuit_cuil'], 0, 10) . (((int) $c['datos']['cuit_cuil'][10] + 1) % 10), 'correo' => $correo],
            'código HTML' => ['tipo_documento' => 'DNI', 'numero' => '<script>alert(1)</script>', 'correo' => $correo],
        ];
        foreach ($casos as $caso => $datos) {
            $this->enviar('portal/activar', $datos);
            $this->assertSame('No pudimos verificar los datos ingresados. Revíselos o acérquese a la sucursal.', $_SESSION['error'] ?? null, $caso);
        }
        $this->assertSame(0, $this->contar('autorizaciones_temporales', ['cuenta_acceso_id' => $cuentaId, 'proposito' => 'Activacion']));
    }

    public function testActivacionNoRevelaSiElClienteExiste(): void
    {
        $c = $this->fabrica->cliente();
        $cuentaId = (int) $this->db->table('clientes')->select('cuenta_cliente_id')->where('id', $c['id'])->get()->getRowArray()['cuenta_cliente_id'];
        $mensajes = [];
        $datos = [
            'propios con puntos' => ['tipo_documento' => 'DNI', 'numero' => number_format((float) $c['datos']['nro_documento'], 0, '', '.'), 'correo' => mb_strtoupper($c['datos']['correo'])],
            'documento ajeno' => ['tipo_documento' => 'DNI', 'numero' => (string) Fabrica::dni(), 'correo' => $c['datos']['correo']],
            'correo ajeno' => ['tipo_documento' => 'DNI', 'numero' => $c['datos']['nro_documento'], 'correo' => 'otro.' . Fabrica::sufijo() . '@prueba.local'],
        ];
        foreach ($datos as $caso => $d) {
            $this->enviar('portal/activar', $d);
            $mensajes[$caso] = $_SESSION['exito'] ?? $_SESSION['error'] ?? null;
        }
        $this->assertNotNull($mensajes['propios con puntos']);
        $this->assertGreaterThan(0, $this->contar('autorizaciones_temporales', ['cuenta_acceso_id' => $cuentaId, 'proposito' => 'Activacion']));
        // Los datos que no corresponden reciben el mensaje de no verificado, sin indicar cuál dato falló
        $this->assertSame($mensajes['documento ajeno'], $mensajes['correo ajeno']);
    }
}
