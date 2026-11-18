<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Libraries\Seguridad\CatalogoPermisos;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-C01 a IN-C04 e IN-C09 · Controles comunes de las rutas del panel de la iteración 1 (plan de pruebas 7.9.1).
 *
 * Las rutas se toman de Config\Routes: cada ruta de usuarios, clientes, líneas y alertas se pide de verdad, por GET y
 * por POST, sin sesión, por AJAX, con una sesión del portal y con un rol al que la matriz (3.6.2) no le da el permiso.
 * Así una ruta nueva de esos módulos queda controlada sin escribir otra prueba.
 *
 * @internal
 */
final class ControlesComunesTest extends IntegracionTestCase
{
    /** Prefijos de las rutas de la iteración 1 en el panel. */
    private const MODULOS = [
        'admin/usuarios', 'admin/clientes', 'admin/pre-registros', 'admin/lineas', 'admin/alertas',
        // Iteración 2, bloque A: fondos, cajas, jornada de caja y calendario
        'admin/fondos', 'admin/cajas', 'admin/caja', 'admin/calendario',
    ];

    /** @return array<string, array{0: string, 1: string, 2: string}> «VERBO ruta» => [verbo, ruta con ids, permiso] */
    public static function rutas(): array
    {
        $coleccion = service('routes')->loadRoutes();
        $r = [];
        foreach (['GET', 'POST'] as $verbo) {
            foreach (array_keys($coleccion->getRoutes($verbo, false)) as $ruta) {
                $modulo = array_filter(self::MODULOS, static fn (string $m): bool => $ruta === $m || str_starts_with($ruta, "{$m}/"));
                if ($modulo === []) {
                    continue;
                }
                $permisos = array_values(array_filter($coleccion->getFiltersForRoute($ruta, $verbo), static fn (string $f): bool => str_starts_with($f, 'permiso:')));
                $concreta = str_replace(['([0-9]+)', '(:num)', '([^/]+)', '(:segment)'], ['999999999', '999999999', 'x', 'x'], $ruta);
                $r["{$verbo} {$ruta}"] = [$verbo, $concreta, substr($permisos[0] ?? 'permiso:', strlen('permiso:'))];
            }
        }

        return $r;
    }

    /**
     * IN-C01: sin sesión, cada ruta redirige al inicio de sesión del panel y no ejecuta la operación.
     *
     * @dataProvider rutas
     */
    public function testSinSesion(string $verbo, string $ruta, string $permiso): void
    {
        $antes = $this->ultimoId('registros_auditoria');

        $r = $verbo === 'GET' ? $this->pedir($ruta) : $this->enviar($ruta, ['motivo' => 'Prueba sin sesión', 'version_fila' => '1']);

        $r->assertRedirectTo(site_url('admin/login'));
        $this->assertSame($antes, $this->ultimoId('registros_auditoria'), 'Sin sesión no debe registrarse ninguna operación');
    }

    /**
     * IN-C02: sin sesión por AJAX, 401 con el mensaje en JSON.
     *
     * @dataProvider rutas
     */
    public function testSinSesionPorAjax(string $verbo, string $ruta, string $permiso): void
    {
        $r = $verbo === 'GET' ? $this->pedir($ruta, [], true) : $this->enviar($ruta, [], true, true);

        $r->assertStatus(401);
        $r->assertJSONExact(['mensaje' => 'Sesión requerida.']);
    }

    /**
     * IN-C03: una sesión del portal no entra al panel (RN-08).
     *
     * @dataProvider rutas
     */
    public function testSesionDelPortal(string $verbo, string $ruta, string $permiso): void
    {
        $this->cambiarSesion(['cuenta' => [
            'id' => 1, 'tipo' => 'Cliente', 'permisos' => array_column(CatalogoPermisos::PERMISOS, 0),
            'version_seguridad' => 1, 'inicio' => time(), 'ultima_actividad' => time(),
        ]]);

        $r = $verbo === 'GET' ? $this->pedir($ruta) : $this->enviar($ruta, ['motivo' => 'Prueba de ámbito', 'version_fila' => '1']);

        $r->assertRedirectTo(site_url('admin/login'));
    }

    /**
     * IN-C04: cada rol sin el permiso recibe 403 «Acceso no autorizado», el rechazo queda en la auditoría y la
     * operación no se ejecuta; por AJAX, 403 en JSON.
     *
     * @dataProvider rutas
     */
    public function testRolSinPermiso(string $verbo, string $ruta, string $permiso): void
    {
        if ($permiso === '') {
            $this->markTestSkipped("{$ruta} solo exige sesión");
        }
        $sinPermiso = $this->rolesSin($permiso);
        if ($sinPermiso === []) {
            $this->markTestSkipped("Todos los roles tienen {$permiso}");
        }
        foreach ($sinPermiso as $rol) {
            $this->comoRol($rol);
            $antes = $this->contar('registros_auditoria', ['accion' => 'acceso.denegado']);
            $operacionesAntes = $this->contar('registros_auditoria', ['resultado' => 'Exitoso', 'accion !=' => 'acceso.inicio']);

            $r = $verbo === 'GET' ? $this->pedir($ruta) : $this->enviar($ruta, ['motivo' => 'Prueba sin permiso', 'version_fila' => '1']);

            $r->assertStatus(403);
            $this->assertStringContainsString('Acceso no autorizado', (string) $r->response()->getBody(), "{$rol}: cuerpo de la respuesta 403");
            $this->assertSame($antes + 1, $this->contar('registros_auditoria', ['accion' => 'acceso.denegado']), "{$rol}: falta el registro del rechazo");
            $fila = $this->db->table('registros_auditoria')->where('accion', 'acceso.denegado')->orderBy('id', 'DESC')->get(1)->getRowArray();
            $this->assertSame('Rechazado', $fila['resultado']);
            $this->assertStringContainsString($permiso, (string) $fila['motivo']);
            $this->assertSame($operacionesAntes, $this->contar('registros_auditoria', ['resultado' => 'Exitoso', 'accion !=' => 'acceso.inicio']), "{$rol}: la operación no debe ejecutarse");

            $ajax = $verbo === 'GET' ? $this->pedir($ruta, [], true) : $this->enviar($ruta, [], true, true);
            $ajax->assertStatus(403);
            $ajax->assertJSONExact(['mensaje' => 'No tiene permiso para realizar esta operación.']);
        }
    }

    /** IN-C09: un identificador no numérico en la dirección responde 404, sin detalles técnicos. */
    public function testIdentificadorNoNumerico(): void
    {
        $this->comoRol('Administrador');
        foreach (['admin/usuarios/abc', 'admin/usuarios/-1', 'admin/usuarios/1.5', "admin/usuarios/1%27", 'admin/clientes/abc/editar', 'admin/lineas/abc'] as $ruta) {
            try {
                $r = $this->pedir($ruta);
                $this->assertContains($r->response()->getStatusCode(), [400, 404], $ruta);
            } catch (PageNotFoundException | BadRequestException $e) {
                // 404 si la ruta no existe; 400 si la dirección tiene caracteres no permitidos (1')
                $this->assertContains($e->getCode(), [400, 404], $ruta);
                $this->assertDoesNotMatchRegularExpression('/SQL|dbo\.|\.php/', $e->getMessage(), $ruta);
            }
        }
    }

    /** @return list<string> roles internos que no tienen el permiso según los datos semilla */
    private function rolesSin(string $permiso): array
    {
        $filas = $this->db->query(
            'SELECT r.codigo FROM dbo.roles r WHERE NOT EXISTS (SELECT 1 FROM dbo.roles_permisos rp JOIN dbo.permisos p ON p.id = rp.permiso_id '
            . 'WHERE rp.rol_id = r.id AND p.codigo = ?) ORDER BY r.codigo',
            [$permiso],
        )->getResultArray();

        return array_column($filas, 'codigo');
    }
}
