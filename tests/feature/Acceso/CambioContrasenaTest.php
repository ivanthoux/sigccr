<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Services\Acceso\AccesoService;
use App\Services\Acceso\CambioContrasenaService;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-76 · Cambiar contraseña (CU-107; RN-62; ASVS V6.2.2 y V6.2.3), en el panel y en el portal. Plan de pruebas 7.9.
 *
 * @internal
 */
final class CambioContrasenaTest extends IntegracionTestCase
{
    private const NUEVA = 'Cielo.Gris.Montaña.9271';

    /** @return array<string, array{0: string, 1: string, 2: string}> ámbito => [ruta, tipo de cuenta, inicio] */
    public static function ambitos(): array
    {
        return ['panel' => ['/admin/cuenta/contrasena', 'UsuarioInterno', '/admin'], 'portal' => ['/portal/perfil/contrasena', 'Cliente', '/portal']];
    }

    /** @return array{id: int, correo: string, clave: string} */
    private function conSesion(string $tipo): array
    {
        if ($tipo === 'Cliente') {
            $c = $this->fabrica->clienteConAcceso();
            $this->comoUsuario($c, 'Cliente');

            return ['id' => $c['cuenta_id'], 'correo' => $c['correo'], 'clave' => $c['clave']];
        }
        $u = $this->fabrica->usuario();
        $this->comoUsuario($u);

        return ['id' => $u['id'], 'correo' => $u['correo'], 'clave' => $u['clave']];
    }

    /** @dataProvider ambitos */
    public function testElFormularioSeMuestraConSesion(string $ruta, string $tipo): void
    {
        $this->conSesion($tipo);
        $r = $this->pedir($ruta);
        $r->assertOK();
        $r->assertSee('Contraseña actual');
    }

    /** @dataProvider ambitos */
    public function testSinSesionNoSeAccede(string $ruta, string $tipo): void
    {
        $this->sinSesion();
        $this->assertTrue($this->pedir($ruta)->isRedirect());
        $this->assertTrue($this->enviar($ruta, ['actual' => 'x', 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA])->isRedirect());
    }

    /** @dataProvider ambitos */
    public function testCambioCorrecto(string $ruta, string $tipo, string $inicio): void
    {
        $u = $this->conSesion($tipo);
        $antes = $this->db->table('cuentas_acceso')->select('version_seguridad')->where('id', $u['id'])->get()->getRowArray();

        $r = $this->enviar($ruta, ['actual' => $u['clave'], 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA]);
        $this->assertAceptado($r);

        $despues = $this->db->table('cuentas_acceso')->select('version_seguridad, hash_contrasena')->where('id', $u['id'])->get()->getRowArray();
        $this->assertSame((int) $antes['version_seguridad'] + 1, (int) $despues['version_seguridad'], 'Las demás sesiones deben invalidarse');
        $this->assertTrue(password_verify(self::NUEVA, $despues['hash_contrasena']));
        $this->assertStringStartsWith('$argon2id$', $despues['hash_contrasena']);
        // Se inicia sesión con la nueva y no con la anterior
        (new AccesoService($this->db))->autenticar($tipo, $u['correo'], self::NUEVA);
        $this->expectExceptionMessage(AccesoService::MENSAJE_RECHAZO);
        (new AccesoService($this->db))->autenticar($tipo, $u['correo'], $u['clave']);
    }

    /** @dataProvider ambitos */
    public function testElCambioQuedaAuditadoSinLasContrasenas(string $ruta, string $tipo): void
    {
        $u = $this->conSesion($tipo);
        $this->enviar($ruta, ['actual' => $u['clave'], 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA]);
        $fila = $this->db->table('registros_auditoria')->where('accion', 'acceso.cambio-contrasena')->where('objeto_id', (string) $u['id'])->get()->getRowArray();
        $this->assertNotNull($fila);
        $this->assertStringNotContainsString(self::NUEVA, json_encode($fila, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString($u['clave'], json_encode($fila, JSON_UNESCAPED_UNICODE));
    }

    /** @dataProvider ambitos */
    public function testRechazos(string $ruta, string $tipo): void
    {
        $u = $this->conSesion($tipo);
        $hashInicial = $this->db->table('cuentas_acceso')->select('hash_contrasena')->where('id', $u['id'])->get()->getRowArray()['hash_contrasena'];
        $casos = [
            'sin la actual' => [['actual' => '', 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA], 'Ingrese su contraseña actual.'],
            'actual incorrecta' => [['actual' => 'No.Es.La.Clave.Actual.1', 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA], CambioContrasenaService::MENSAJE_ACTUAL_INCORRECTA],
            'igual a la actual' => [['actual' => $u['clave'], 'contrasena' => $u['clave'], 'confirmacion' => $u['clave']], CambioContrasenaService::MENSAJE_IGUAL],
            '14 caracteres' => [['actual' => $u['clave'], 'contrasena' => str_repeat('a', 13) . 'Z', 'confirmacion' => str_repeat('a', 13) . 'Z'], 'entre 15 y 128'],
            'comprometida' => [['actual' => $u['clave'], 'contrasena' => 'password1234567', 'confirmacion' => 'password1234567'], 'lista de contraseñas comprometidas'],
            'confirmación distinta' => [['actual' => $u['clave'], 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA . 'x'], 'La confirmación no coincide'],
            'como lista' => [['actual' => [$u['clave']], 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA], 'Ingrese su contraseña actual.'],
        ];
        foreach ($casos as $nombre => [$datos, $mensaje]) {
            $r = $this->enviar($ruta, $datos);
            $this->assertTrue($r->isRedirect(), $nombre);
            $this->assertStringContainsString($mensaje, (string) ($_SESSION['error'] ?? ''), $nombre);
            // La cuenta sigue con su contraseña y no se bloquea por los rechazos de la política
            $this->assertSame($hashInicial, $this->db->table('cuentas_acceso')->select('hash_contrasena')->where('id', $u['id'])->get()->getRowArray()['hash_contrasena'], $nombre);
        }
    }

    /** @dataProvider ambitos */
    public function testSinTokenCsrfNoSeCambia(string $ruta, string $tipo): void
    {
        $u = $this->conSesion($tipo);
        $this->enviar($ruta, ['actual' => $u['clave'], 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA], false);
        $this->assertTrue(password_verify($u['clave'], $this->db->table('cuentas_acceso')->select('hash_contrasena')->where('id', $u['id'])->get()->getRowArray()['hash_contrasena']));
    }

    /** La contraseña actual incorrecta cuenta como en el inicio de sesión: al quinto intento, bloqueo temporal (RN-62). */
    public function testLosFallosDeLaActualBloqueanLaCuenta(): void
    {
        $u = $this->conSesion('UsuarioInterno');
        for ($i = 1; $i <= 5; $i++) {
            $this->enviar('/admin/cuenta/contrasena', ['actual' => 'Incorrecta.Numero.' . $i, 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA]);
        }
        $this->assertStringContainsString('bloqueado', (string) ($_SESSION['error'] ?? ''));
        $cuenta = $this->db->table('cuentas_acceso')->select('estado, intentos_fallidos')->where('id', $u['id'])->get()->getRowArray();
        $this->assertSame('BloqueadaTemporalmente', $cuenta['estado']);
        // Con la cuenta bloqueada ya no se puede cambiar, ni siquiera con la contraseña correcta
        $this->enviar('/admin/cuenta/contrasena', ['actual' => $u['clave'], 'contrasena' => self::NUEVA, 'confirmacion' => self::NUEVA]);
        $this->assertTrue(password_verify($u['clave'], $this->db->table('cuentas_acceso')->select('hash_contrasena')->where('id', $u['id'])->get()->getRowArray()['hash_contrasena']));
    }
}
