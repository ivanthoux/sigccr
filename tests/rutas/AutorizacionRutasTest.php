<?php

declare(strict_types=1);

namespace Tests\Rutas;

use App\Libraries\Seguridad\CatalogoPermisos;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * C10 · Autorización de cada ruta con cada rol (RN-01, RNF-01).
 *
 * Recorre las rutas declaradas en Config\Routes y verifica, sin base de datos:
 *  - toda ruta del panel, salvo las de acceso sin sesión, exige sesión del panel (auth:panel) y un permiso del catálogo;
 *  - las rutas del portal sin sesión no exigen permisos del panel, y las demás exigen una sesión de cliente (auth:portal);
 *  - para cada rol, las rutas que puede usar coinciden con la matriz de participación por actor (3.6.2).
 *
 * @internal
 */
final class AutorizacionRutasTest extends CIUnitTestCase
{
    /** Rutas del panel que se usan sin sesión (CU-01, CU-02 y enlaces de correo). */
    private const PUBLICAS_PANEL = [
        'admin/login', 'admin/recuperar', 'admin/restablecer/([^/]+)', 'admin/verificar-correo/([^/]+)',
    ];

    /** Rutas del panel que solo exigen sesión (inicio y cierre de sesión, CU-03). */
    private const SOLO_SESION = ['admin', 'admin/salir'];

    private RouteCollection $rutas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rutas = service('routes')->loadRoutes();
    }

    /** @return array<string, list<string>> ruta "VERBO ruta" => filtros */
    private function rutasPanel(): array
    {
        $r = [];
        foreach (['GET', 'POST'] as $verbo) {
            foreach (array_keys($this->rutas->getRoutes($verbo, false)) as $ruta) {
                if ($ruta === 'admin' || str_starts_with($ruta, 'admin/')) {
                    $r["{$verbo} {$ruta}"] = $this->rutas->getFiltersForRoute($ruta, $verbo);
                }
            }
        }

        return $r;
    }

    public function testCadaRutaDelPanelExigeSesionYUnPermisoDelCatalogo(): void
    {
        $codigos = array_column(CatalogoPermisos::PERMISOS, 0);
        $sinProteger = [];
        foreach ($this->rutasPanel() as $clave => $filtros) {
            $ruta = explode(' ', $clave, 2)[1];
            if (in_array($ruta, self::PUBLICAS_PANEL, true)) {
                $this->assertNotContains('auth:panel', $filtros, "{$clave} es de acceso sin sesión");
                continue;
            }
            if (!in_array('auth:panel', $filtros, true)) {
                $sinProteger[] = "{$clave}: falta auth:panel";
                continue;
            }
            if (in_array($ruta, self::SOLO_SESION, true)) {
                continue;
            }
            $permisos = array_values(array_filter($filtros, static fn (string $f): bool => str_starts_with($f, 'permiso:')));
            if (count($permisos) !== 1) {
                $sinProteger[] = "{$clave}: debe tener exactamente un permiso";
                continue;
            }
            $codigo = substr($permisos[0], strlen('permiso:'));
            if (!in_array($codigo, $codigos, true)) {
                $sinProteger[] = "{$clave}: el permiso {$codigo} no está en el catálogo";
            }
        }
        $this->assertSame([], $sinProteger);
    }

    public function testElPortalSinSesionNoUsaPermisosDelPanel(): void
    {
        foreach (['GET', 'POST'] as $verbo) {
            foreach (array_keys($this->rutas->getRoutes($verbo, false)) as $ruta) {
                if (!str_starts_with($ruta, 'portal/')) {
                    continue;
                }
                $filtros = $this->rutas->getFiltersForRoute($ruta, $verbo);
                $this->assertSame([], array_values(array_filter($filtros, static fn (string $f): bool => str_starts_with($f, 'permiso:') || $f === 'auth:panel')), "{$verbo} {$ruta}");
            }
        }
    }

    /** Rutas del portal que se usan sin sesión (CU-01, CU-02, CU-04 y enlaces de correo). */
    private const PUBLICAS_PORTAL = [
        'portal/login', 'portal/recuperar', 'portal/restablecer/([^/]+)', 'portal/activar', 'portal/activar/([^/]+)',
        'portal/verificar-correo/([^/]+)',
        'portal/pre-registro', 'portal/pre-registro/verificar/([^/]+)', 'portal/cambio-correo/([^/]+)',
    ];

    public function testCadaRutaDelPortalConSesionExigeUnaCuentaDeCliente(): void
    {
        $sinProteger = [];
        $hayPrivadas = false;
        foreach (['GET', 'POST'] as $verbo) {
            foreach (array_keys($this->rutas->getRoutes($verbo, false)) as $ruta) {
                if ($ruta !== 'portal' && !str_starts_with($ruta, 'portal/')) {
                    continue;
                }
                $filtros = $this->rutas->getFiltersForRoute($ruta, $verbo);
                if (in_array($ruta, self::PUBLICAS_PORTAL, true)) {
                    $this->assertNotContains('auth:portal', $filtros, "{$verbo} {$ruta} es de acceso sin sesión");
                    continue;
                }
                $hayPrivadas = true;
                if (!in_array('auth:portal', $filtros, true)) {
                    $sinProteger[] = "{$verbo} {$ruta}: falta auth:portal";
                }
            }
        }
        $this->assertTrue($hayPrivadas, 'El portal debe tener rutas con sesión (inicio y cierre de sesión)');
        $this->assertSame([], $sinProteger);
    }

    public function testElPanelNoAceptaLaSesionDelPortal(): void
    {
        foreach ($this->rutasPanel() as $clave => $filtros) {
            $this->assertNotContains('auth:portal', $filtros, $clave);
        }
    }

    /**
     * Rutas que cada rol puede y no puede usar: se derivan de la matriz y se comprueban con ejemplos de cada
     * subsistema construido, para detectar un permiso mal asignado en una ruta.
     *
     * @return array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    public static function casosPorRol(): array
    {
        return [
            'Administrador' => ['Administrador', ['GET admin/usuarios', 'POST admin/usuarios', 'GET admin/clientes', 'GET admin/alertas'], ['POST admin/clientes', 'POST admin/lineas', 'GET admin/parametros/evaluacion']],
            'Gerente' => ['Gerente', ['POST admin/lineas', 'POST admin/lineas/([0-9]+)/suspender', 'GET admin/parametros/evaluacion', 'POST admin/clientes/([0-9]+)/desactivar'], ['POST admin/usuarios', 'POST admin/clientes']],
            'Analista de Crédito' => ['AnalistaCredito', ['POST admin/clientes', 'GET admin/lineas', 'GET admin/lineas/([0-9]+)', 'GET admin/alertas'], ['POST admin/lineas', 'POST admin/lineas/([0-9]+)/editar', 'GET admin/usuarios', 'GET admin/parametros/evaluacion']],
            'Cajero' => ['Cajero', ['GET admin/clientes', 'GET admin/alertas'], ['GET admin/lineas', 'POST admin/clientes', 'GET admin/usuarios', 'POST admin/clientes/([0-9]+)/desactivar']],
        ];
    }

    /**
     * @dataProvider casosPorRol
     *
     * @param list<string> $puede
     * @param list<string> $noPuede
     */
    public function testCadaRolUsaSoloLasRutasDeSuMatriz(string $rol, array $puede, array $noPuede): void
    {
        $permisos = CatalogoPermisos::permisosDelRol($rol);
        $rutas = $this->rutasPanel();
        $permitida = static function (string $clave) use ($rutas, $permisos): bool {
            $requeridos = array_map(static fn (string $f): string => substr($f, strlen('permiso:')), array_filter($rutas[$clave] ?? [], static fn (string $f): bool => str_starts_with($f, 'permiso:')));

            return array_diff($requeridos, $permisos) === [];
        };
        foreach ($puede as $clave) {
            $this->assertArrayHasKey($clave, $rutas, "{$clave} no está declarada");
            $this->assertTrue($permitida($clave), "{$rol} debería poder usar {$clave}");
        }
        foreach ($noPuede as $clave) {
            $this->assertArrayHasKey($clave, $rutas, "{$clave} no está declarada");
            $this->assertFalse($permitida($clave), "{$rol} no debería poder usar {$clave}");
        }
    }
}
