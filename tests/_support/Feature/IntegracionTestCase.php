<?php

declare(strict_types=1);

namespace Tests\Support\Feature;

use App\Services\Acceso\AccesoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Base de las pruebas de integración (D-49, plan de pruebas 7.9).
 *
 * - Usa la base sigccr_pruebas, que tests/feature/preparar_base.sh recrea en cada ejecución: no migra ni siembra por
 *   prueba ($migrate y $refresh en false) y cada prueba crea sus propios datos con la Fabrica.
 * - Las peticiones pasan por todos los filtros (sesión, permiso, CSRF): comoUsuario() inicia una sesión real con
 *   AccesoService y enviar() agrega el token CSRF de esa sesión, salvo que se pida lo contrario (IN-C05).
 * - Cada respuesta se controla con IN-C13: nunca un error 500 ni detalles técnicos.
 */
abstract class IntegracionTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;
    protected $migrateOnce = false;
    protected $refresh = false;
    protected $seed = '';
    protected $namespace;

    protected Fabrica $fabrica;

    /** @var array<string, mixed> sesión con la que se hacen las peticiones */
    private array $sesionActual = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        // HTTPS: el filtro forcehttps admite la petición de prueba (CodeIgniter reconoce HTTPS=test)
        $_SERVER['HTTPS'] = 'test';
        service('superglobals')->setServer('HTTPS', 'test');
        // CSRF como en producción: un POST sin token válido vuelve al formulario con el mensaje, sin excepción (IN-C05)
        config('Security')->redirect = true;
        // El límite de intentos por dirección (RN-62) usa la caché: cada prueba empieza sin intentos previos
        Services::injectMock('cache', new MockCache());
        $this->fabrica = new Fabrica($this->db);
        $this->sesionActual = [];
    }

    // ── Sesión ───────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Inicia sesión como ese usuario por el mismo camino que CU-01 y la usa en las peticiones siguientes.
     *
     * @param array{correo: string, clave: string} $usuario
     * @return array<string, mixed> los datos de la sesión
     */
    protected function comoUsuario(array $usuario, string $tipo = 'UsuarioInterno'): array
    {
        $cuenta = (new AccesoService($this->db))->autenticar($tipo, $usuario['correo'], $usuario['clave']);
        $this->sesionActual = ['cuenta' => $cuenta];

        return $cuenta;
    }

    /** Usuario nuevo del rol indicado, con su sesión iniciada. @return array{id: int, correo: string, clave: string, rol: string} */
    protected function comoRol(string $rol): array
    {
        $u = $this->fabrica->usuario($rol);
        $this->comoUsuario($u);

        return $u;
    }

    /** Mensaje con que se rechaza un POST sin token CSRF válido (IN-C05). */
    protected const MENSAJE_CSRF = 'El formulario venció o ya se había enviado. Vuelva a cargar la página e intente de nuevo.';

    protected function sinSesion(): void
    {
        $this->sesionActual = [];
    }

    /** @param array<string, mixed> $valores */
    protected function cambiarSesion(array $valores): void
    {
        $this->sesionActual = array_replace_recursive($this->sesionActual, $valores);
    }

    // ── Peticiones ───────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * POST con la sesión actual. Con $token, agrega el token CSRF de la sesión; 'otra' envía un token válido de otra
     * sesión y 'alterado', uno con un carácter cambiado (IN-C05).
     *
     * @param array<string, mixed> $datos
     */
    protected function enviar(string $ruta, array $datos = [], bool|string $token = true, bool $ajax = false): TestResponse
    {
        $sesion = $this->sesionActual;
        if ($token !== false) {
            $hash = bin2hex(random_bytes(16));
            $sesion['csrf_sigccr'] = $hash;
            $datos['csrf_sigccr'] = match ($token) {
                'otra' => bin2hex(random_bytes(16)),
                'alterado' => substr($hash, 0, -1) . ($hash[-1] === 'a' ? 'b' : 'a'),
                default => $hash,
            };
        }
        // El servicio de seguridad lee el token de la sesión al crearse: se vuelve a crear con la sesión de la petición
        Services::resetSingle('security');
        $peticion = $this->withSession($sesion);
        if ($ajax) {
            $peticion = $peticion->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        }

        return $this->controlarRespuesta($peticion->post($ruta, $datos));
    }

    /** @param array<string, mixed> $parametros */
    protected function pedir(string $ruta, array $parametros = [], bool $ajax = false): TestResponse
    {
        Services::resetSingle('security');
        $peticion = $this->withSession($this->sesionActual);
        if ($ajax) {
            $peticion = $peticion->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        }

        return $this->controlarRespuesta($peticion->get($ruta, $parametros === [] ? null : $parametros));
    }

    /** IN-C13: ninguna respuesta es un error 500 ni muestra detalles técnicos (RNF-12). */
    private function controlarRespuesta(TestResponse $r): TestResponse
    {
        $estado = $r->response()->getStatusCode();
        $this->assertLessThan(500, $estado, "IN-C13: la respuesta fue un error {$estado}");
        $cuerpo = (string) $r->response()->getBody();
        foreach (['SQLSTATE', 'sqlsrv', 'Stack trace', '.php:', 'dbo.', 'Exception'] as $tecnico) {
            $this->assertStringNotContainsString($tecnico, $cuerpo, "IN-C13: la respuesta muestra un detalle técnico ({$tecnico})");
        }
        foreach (['error', 'aviso', 'exito'] as $clave) {
            $mensaje = (string) ($_SESSION[$clave] ?? '');
            $this->assertDoesNotMatchRegularExpression('/SQLSTATE|dbo\.|\.php|Exception/', $mensaje, "IN-C13: el mensaje muestra un detalle técnico: {$mensaje}");
        }

        return $r;
    }

    // ── Aserciones ───────────────────────────────────────────────────────────────────────────────────────────────

    /** El formulario volvió con ese mensaje en el campo. */
    protected function assertCampoRechazado(TestResponse $r, string $campo, ?string $mensaje, string $contexto = ''): void
    {
        $errores = (array) ($_SESSION['errores'] ?? []);
        $general = (string) ($_SESSION['error'] ?? '');
        $this->assertTrue($r->isRedirect(), "{$contexto}: se esperaba volver al formulario (estado {$r->response()->getStatusCode()})");
        $this->assertArrayHasKey($campo, $errores, "{$contexto}: se esperaba un error en «{$campo}». Errores: " . json_encode($errores, JSON_UNESCAPED_UNICODE) . " General: {$general}");
        if ($mensaje !== null) {
            $this->assertSame($mensaje, $errores[$campo], "{$contexto}: mensaje del campo «{$campo}»");
        }
    }

    /** La operación terminó con su mensaje de éxito (sin errores de validación). */
    protected function assertAceptado(TestResponse $r, string $contexto = ''): void
    {
        $errores = (array) ($_SESSION['errores'] ?? []);
        $general = (string) ($_SESSION['error'] ?? '');
        $this->assertSame([], $errores, "{$contexto}: no se esperaban errores. " . json_encode($errores, JSON_UNESCAPED_UNICODE) . " General: {$general}");
        $this->assertSame('', $general, "{$contexto}: no se esperaba un error general");
        $this->assertTrue($r->isRedirect(), "{$contexto}: se esperaba la redirección del éxito");
        $this->assertNotSame('', (string) ($_SESSION['exito'] ?? $_SESSION['aviso'] ?? ''), "{$contexto}: falta el mensaje de éxito");
    }

    /** Un valor guardado coincide con el esperado: los numéricos se comparan por valor (DECIMAL devuelve más ceros). */
    protected function assertValorGuardado(?string $esperado, mixed $guardado, string $contexto = ''): void
    {
        if ($esperado === null) {
            return;
        }
        $g = $guardado === null ? null : (string) $guardado;
        if ($g !== null && is_numeric($esperado) && is_numeric($g) && preg_match('/^-?\d+(\.\d+)?$/', $g) === 1 && preg_match('/^-?\d+(\.\d+)?$/', $esperado) === 1) {
            $this->assertSame(0, bccomp($esperado, $g, 12), "{$contexto}: valor guardado {$g}, se esperaba {$esperado}");

            return;
        }
        if ($g !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $esperado) === 1) {
            $g = substr($g, 0, 10);
        }
        $this->assertSame($esperado, $g, "{$contexto}: valor guardado");
    }

    /** Cantidad de filas de una tabla con esa condición. @param array<string, mixed> $donde */
    protected function contar(string $tabla, array $donde = []): int
    {
        return $this->db->table($tabla)->where($donde)->countAllResults();
    }

    /** Último id de una tabla (para verificar que un rechazo no agregó filas). */
    protected function ultimoId(string $tabla): int
    {
        return (int) ($this->db->query("SELECT MAX(id) AS m FROM dbo.{$tabla}")->getRowArray()['m'] ?? 0);
    }

    /**
     * Arma los datos del formulario reemplazando un campo por el valor de la clase (Esperado::AUSENTE lo quita).
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $otros otros campos que la clase necesita cambiar
     * @return array<string, mixed>
     */
    protected static function conValor(array $base, string $campo, mixed $valor, array $otros = []): array
    {
        $datos = $otros + $base;
        if ($valor === Esperado::AUSENTE) {
            unset($datos[$campo]);
        } else {
            $datos[$campo] = $valor;
        }

        return $datos;
    }

    /**
     * Casos de un dataProvider a partir del catálogo: «campo · clase descripción» => [campo, valor, esperado, otros].
     *
     * @param array<string, array<string, mixed>> $campos campo => definición (ClasesDeDatos)
     * @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}>
     */
    protected static function casosDelCatalogo(string $caso, array $campos): array
    {
        $r = [];
        foreach ($campos as $campo => $definicion) {
            foreach (ClasesDeDatos::para($definicion) as $c) {
                $nombre = "{$caso} · {$campo} · {$c[0]} {$c[1]}";
                $i = 2;
                while (isset($r[$nombre])) {
                    $nombre = "{$caso} · {$campo} · {$c[0]} {$c[1]} ({$i})";
                    $i++;
                }
                $r[$nombre] = [$campo, $c[2], $c[3], ($c[4] ?? []) + ($definicion['otros'] ?? [])];
            }
        }

        return $r;
    }
}
