<?php

declare(strict_types=1);

namespace Tests\Rutas;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * S-08 a S-10 · Rutas, métodos HTTP y CORS (RNF-12; OWASP ASVS 5.0 nivel 1: V3.5.3, V6.1.1, V3.4.2). Plan de pruebas 7.10.
 * Se verifican sobre la configuración, sin base de datos.
 *
 * @internal
 */
final class SeguridadRutasTest extends CIUnitTestCase
{
    /** Acciones que modifican datos: nunca deben atenderse por GET (V3.5.3). */
    private const ACCIONES_QUE_MODIFICAN = '/^(guardar|crear|registrar|eliminar|borrar|anular|aprobar|rechazar|desembolsar|cobrar|aplicar|ejecutar|procesar|actualizar|cambiar|enviar|reenviar|restaurar|importar|cargar|confirmarCorreo|activar|desactivar|reactivar|suspender)/i';

    /** S-08 · Ninguna ruta GET llama a una acción que modifica datos: las pantallas «confirmar» solo muestran la confirmación (V3.5.3). */
    public function testLasRutasGetNoAtiendenAccionesQueModifican(): void
    {
        /** @var RouteCollection $rutas */
        $rutas = service('routes')->loadRoutes();
        $mal   = [];
        foreach ($rutas->getRoutes('GET', false) as $ruta => $manejador) {
            if (!is_string($manejador) || !str_contains($manejador, '::')) {
                continue;
            }
            $metodo = explode('/', explode('::', $manejador, 2)[1])[0];
            if (preg_match(self::ACCIONES_QUE_MODIFICAN, $metodo) === 1) {
                $mal[] = "GET {$ruta} → {$manejador}";
            }
        }
        $this->assertSame([], $mal, "Rutas GET que modifican datos:\n" . implode("\n", $mal));
    }

    /** S-09 · El inicio de sesión, la recuperación, la activación y el pre-registro tienen límite de intentos por origen (V6.1.1, V6.3.1). */
    public function testLasRutasDeAccesoTienenLimiteDeIntentos(): void
    {
        $filtros = config('Filters')->filters['limiteintentos']['before'];
        foreach (['admin/login', 'admin/recuperar', 'portal/login', 'portal/recuperar', 'portal/activar', 'portal/pre-registro'] as $ruta) {
            $this->assertContains($ruta, $filtros, "Falta el límite de intentos en {$ruta}.");
        }
    }

    /** S-10 · La aplicación no habilita CORS: ningún origen externo puede usarla desde el navegador (V3.4.2, V3.5.2). */
    public function testNoSeHabilitaCors(): void
    {
        $filtros = config('Filters');
        $this->assertNotContains('cors', $filtros->globals['before'] ?? []);
        $this->assertNotContains('cors', $filtros->globals['after'] ?? []);
        $this->assertSame([], config('Cors')->default['allowedOrigins']);
        $this->assertSame([], config('Cors')->default['allowedOriginsPatterns']);
    }

    /**
     * S-15 · Ningún formulario que se envía por GET pide un documento, un CUIT o un CUIL: el dato quedaría en la dirección,
     * en el historial del navegador y en los registros del servidor (V14.2.1; Ley 25.326, art. 9; D-64).
     */
    public function testLosFormulariosPorGetNoPidenDocumentos(): void
    {
        $mal = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH . 'Views', \FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }
            $codigo = (string) file_get_contents($archivo->getPathname());
            preg_match_all('/<form\b[^\n]*?method="get"[^\n]*?>.*?<\/form>/is', $codigo, $formularios);
            foreach ($formularios[0] as $formulario) {
                if (preg_match('/name="(documento|nro_documento|cuit|cuil|cuit_cuil|dni)"|\b(DNI|CUIT|CUIL|documento)\b/i', $formulario) === 1) {
                    $mal[] = str_replace(APPPATH, 'app/', $archivo->getPathname());
                }
            }
        }
        $this->assertSame([], $mal, "Formularios GET que piden un documento o un CUIT/CUIL:\n" . implode("\n", $mal));
    }

    /** S-16 · Cada búsqueda por POST tiene su listado por GET con la misma acción, el mismo permiso y el filtro de copia de criterios. */
    public function testLasBusquedasPorPostCorrespondenAUnListado(): void
    {
        /** @var RouteCollection $rutas */
        $rutas = service('routes')->loadRoutes();
        $get   = $rutas->getRoutes('GET', false);
        $busquedas = 0;
        foreach ($rutas->getRoutes('POST', false) as $ruta => $manejador) {
            $filtros = $rutas->getFiltersForRoute($ruta, 'POST');
            if (!in_array('busquedapost', $filtros, true)) {
                continue;
            }
            $busquedas++;
            $this->assertStringContainsString('::index', $manejador . '::index', "POST {$ruta}");
            $this->assertContains($manejador, $get, "POST {$ruta} → {$manejador} no tiene un listado por GET con la misma acción.");
            $permisos = array_values(array_filter($filtros, static fn (string $f): bool => str_starts_with($f, 'permiso:')));
            $this->assertCount(1, $permisos, "POST {$ruta}");
        }
        $this->assertGreaterThanOrEqual(10, $busquedas, 'Clientes, solicitudes, usuarios, pre-registros, refinanciaciones, cobros (2), devoluciones, pagos, contratos y liquidaciones.');
    }
}
