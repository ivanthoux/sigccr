<?php

declare(strict_types=1);

namespace Tests\Feature\Usuarios;

use App\Services\Usuarios\UsuarioService;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-04 · Registrar usuario interno (CU-05). Plan de pruebas 7.9.2 y Anexo B.3.
 *
 * @internal
 */
final class RegistrarUsuarioTest extends IntegracionTestCase
{
    private const RUTA = 'admin/usuarios';

    /** @return array<string, array<string, mixed>> definición de cada campo (Anexo B.3) */
    public static function campos(): array
    {
        $nombre = static fn (string $el): array => [
            'tipo' => 'NOM', 'obligatorio' => true, 'max' => 80,
            'mensajes' => [
                'obligatorio' => "Complete {$el}.",
                'formato' => ucfirst($el) . ' solo admite letras, espacios, apóstrofos y guiones.',
                'largo' => ucfirst($el) . ' admite hasta 80 caracteres.',
            ],
        ];

        return [
            'nombre' => $nombre('el nombre'),
            'apellido' => $nombre('el apellido'),
            'tipo_documento' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['DNI', 'LC', 'LE', 'Pasaporte'],
                'mensajes' => ['obligatorio' => 'Complete el tipo de documento.', 'formato' => 'Seleccione un tipo de documento válido.'],
            ],
            'nro_documento' => [
                'tipo' => 'DOC', 'obligatorio' => true, 'admite_pasaporte' => true,
                'mensajes' => ['obligatorio' => 'Complete el número de documento.', 'formato' => 'El documento debe tener entre 6 y 8 dígitos, sin puntos.'],
            ],
            'correo' => [
                'tipo' => 'COR', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => 'Complete el correo electrónico.', 'formato' => 'Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.'],
            ],
            'rol' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['Administrador', 'Gerente', 'AnalistaCredito', 'Cajero'],
                'mensajes' => ['obligatorio' => 'Seleccione el rol.', 'formato' => 'Seleccione un rol válido.'],
            ],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casos(): array
    {
        return self::casosDelCatalogo('IN-04', self::campos());
    }

    /**
     * @dataProvider casos
     * @param array<string, mixed> $otros
     */
    public function testCampoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Administrador');
        $antes = $this->ultimoId('cuentas_acceso');
        $auditoriasAntes = $this->contar('registros_auditoria', ['accion' => 'usuarios.registrar']);

        $r = $this->enviar(self::RUTA, self::conValor(Fabrica::datosUsuario(), $campo, $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            $usuario = (new UsuarioService($this->db))->obtener($this->idDeLaRedireccion($r));
            $this->assertValorGuardado($esperado->guardado, $usuario[$campo], $campo);
            $this->assertSame('PendienteActivacion', $usuario['estado']);
            $this->assertSame($auditoriasAntes + 1, $this->contar('registros_auditoria', ['accion' => 'usuarios.registrar']));

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame($antes, $this->ultimoId('cuentas_acceso'), 'Un rechazo no debe crear la cuenta');
        $this->assertSame($auditoriasAntes, $this->contar('registros_auditoria', ['accion' => 'usuarios.registrar']));
    }

    /** COR-09: correo de otro usuario interno, en mayúsculas. El de un cliente del portal se admite (RN-08). */
    public function testCorreoDuplicado(): void
    {
        $this->comoRol('Administrador');
        $otro = $this->fabrica->usuario();
        $datos = Fabrica::datosUsuario();
        $datos['correo'] = mb_strtoupper($otro['correo']);

        $r = $this->enviar(self::RUTA, $datos);

        $this->assertCampoRechazado($r, 'correo', 'El correo electrónico ya está registrado para otro usuario.', 'COR-09');
        $this->assertSame(1, $this->contar('cuentas_acceso', ['correo' => $otro['correo'], 'tipo_cuenta' => 'UsuarioInterno']));
    }

    public function testCorreoDeUnClienteSeAdmite(): void
    {
        $this->comoRol('Administrador');
        $cliente = $this->fabrica->cliente();
        $datos = Fabrica::datosUsuario();
        $datos['correo'] = $cliente['datos']['correo'];

        $this->assertAceptado($this->enviar(self::RUTA, $datos), 'COR-09 en otro ámbito');
    }

    /** DOC-11: el mismo tipo y número de otro usuario se rechaza; el mismo número con otro tipo se acepta. */
    public function testDocumentoDuplicado(): void
    {
        $this->comoRol('Administrador');
        $primero = Fabrica::datosUsuario();
        $this->assertAceptado($this->enviar(self::RUTA, $primero));

        $segundo = ['nro_documento' => $primero['nro_documento']] + Fabrica::datosUsuario();
        $this->assertCampoRechazado($this->enviar(self::RUTA, $segundo), 'nro_documento', 'El documento ya está registrado para otro usuario.', 'DOC-11');

        $tercero = ['nro_documento' => $primero['nro_documento'], 'tipo_documento' => 'LC'] + Fabrica::datosUsuario();
        $this->assertAceptado($this->enviar(self::RUTA, $tercero), 'DOC-11 con otro tipo');
    }

    /** IN-C10: los campos extra se ignoran. */
    public function testCamposExtraSeIgnoran(): void
    {
        $this->comoRol('Administrador');
        $datos = Fabrica::datosUsuario('Cajero') + ['estado' => 'Habilitada', 'id' => '1', 'rol_id' => '1', 'version_fila' => '999', 'hash_contrasena' => 'x'];

        $r = $this->enviar(self::RUTA, $datos);

        $this->assertAceptado($r, 'IN-C10');
        $u = (new UsuarioService($this->db))->obtener($this->idDeLaRedireccion($r));
        $this->assertSame('PendienteActivacion', $u['estado']);
        $this->assertSame('Cajero', $u['rol']);
        $this->assertSame(1, (int) $u['version_fila']);
        $this->assertSame(0, (int) $u['tiene_contrasena']);
    }

    /** IN-C12: el mismo formulario enviado dos veces deja un solo usuario. */
    public function testDobleEnvio(): void
    {
        $this->comoRol('Administrador');
        $datos = Fabrica::datosUsuario();

        $this->assertAceptado($this->enviar(self::RUTA, $datos));
        $this->assertCampoRechazado($this->enviar(self::RUTA, $datos), 'correo', 'El correo electrónico ya está registrado para otro usuario.', 'IN-C12');
        $this->assertSame(1, $this->contar('cuentas_acceso', ['correo' => $datos['correo']]));
    }

    /** IN-C14: el registro queda auditado, sin contraseñas ni códigos del enlace de activación. */
    public function testAuditoriaSinDatosSensibles(): void
    {
        $this->comoRol('Administrador');
        $r = $this->enviar(self::RUTA, Fabrica::datosUsuario());
        $id = $this->idDeLaRedireccion($r);

        $fila = $this->db->table('registros_auditoria')->where('accion', 'usuarios.registrar')->where('objeto_id', (string) $id)->get()->getRowArray();
        $this->assertNotNull($fila);
        $this->assertSame('Exitoso', $fila['resultado']);
        $hash = (string) $this->db->query('SELECT TOP 1 hash_codigo FROM dbo.autorizaciones_temporales WHERE cuenta_acceso_id = ?', [$id])->getRowArray()['hash_codigo'];
        $this->assertNotSame('', trim($hash));
        $this->assertStringNotContainsStringIgnoringCase(trim($hash), json_encode($fila, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('hash_contrasena', (string) ($fila['detalle'] ?? ''));
    }

    private function idDeLaRedireccion(TestResponse $r): int
    {
        $destino = $r->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('#/admin/usuarios/(\d+)$#', $destino);
        preg_match('#/admin/usuarios/(\d+)$#', $destino, $m);

        return (int) $m[1];
    }
}
