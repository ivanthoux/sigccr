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
}
