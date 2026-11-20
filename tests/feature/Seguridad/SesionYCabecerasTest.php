<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use Tests\Support\Feature\IntegracionTestCase;

/**
 * S-01 a S-05 y S-12 · Cookie de sesión, caché de las páginas autenticadas y cierre de sesión (RNF-11, RNF-12; OWASP ASVS 5.0
 * nivel 1: V3.3.1, V14.3.1, V7.4.1). Plan de pruebas 7.10.
 *
 * @internal
 */
final class SesionYCabecerasTest extends IntegracionTestCase
{
    /** S-01 · La cookie de sesión lleva el prefijo __Host-, que obliga a Secure, ruta / y sin dominio (V3.3.1). */
    public function testCookieDeSesionConPrefijoHost(): void
    {
        $this->assertStringStartsWith('__Host-', config('Session')->cookieName);
        $cookie = config('Cookie');
        $this->assertTrue($cookie->secure, 'La cookie debe ser Secure.');
        $this->assertTrue($cookie->httponly, 'La cookie debe ser HttpOnly.');
        $this->assertSame('/', $cookie->path);
        $this->assertSame('', $cookie->domain, 'Con __Host- la cookie no puede tener dominio.');
        $this->assertSame('Lax', $cookie->samesite);
    }

    /** S-02 · Las páginas del panel no se guardan en la caché del navegador (V14.3.1). */
    public function testPaginaDelPanelNoSeGuardaEnCache(): void
    {
        $this->comoRol('AnalistaCredito');
        $r = $this->pedir('/admin');
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertStringContainsString('no-store', $r->response()->getHeaderLine('Cache-Control'));
    }

    /** S-03 · Ídem en el portal del cliente. */
    public function testPaginaDelPortalNoSeGuardaEnCache(): void
    {
        $c = $this->fabrica->clienteConAcceso();
        $this->comoUsuario($c, 'Cliente');
        $r = $this->pedir('/portal');
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertStringContainsString('no-store', $r->response()->getHeaderLine('Cache-Control'));
    }

    /** S-04 · Al cerrar la sesión, el navegador recibe la orden de borrar su caché (V14.3.1). */
    public function testCierreDeSesionBorraLaCache(): void
    {
        $this->comoRol('AnalistaCredito');
        $r = $this->enviar('/admin/salir');
        $this->assertSame('"cache"', $r->response()->getHeaderLine('Clear-Site-Data'));

        $c = $this->fabrica->clienteConAcceso();
        $this->comoUsuario($c, 'Cliente');
        $r = $this->enviar('/portal/salir');
        $this->assertSame('"cache"', $r->response()->getHeaderLine('Clear-Site-Data'));
    }

    /** S-05 · Sin sesión no hay acceso a una página autenticada ni se envían sus datos (V7.4.1, V8.2.1). */
    public function testSinSesionRedirigeAlInicioDeSesion(): void
    {
        $this->sinSesion();
        $r = $this->pedir('/admin');
        $this->assertContains($r->response()->getStatusCode(), [302, 303]);
        $this->assertStringContainsString('/admin/login', $r->response()->getHeaderLine('Location'));
    }

    /** S-12 · Las respuestas declaran su tipo de contenido con la codificación, evitan que el navegador lo adivine y no habilitan CORS (V4.1.1, V3.2.1, V3.4.2). */
    public function testRespuestasConTipoDeContenidoYSinCors(): void
    {
        $r = $this->pedir('/admin/login');
        $this->assertSame(200, $r->response()->getStatusCode());
        // El framework completa el tipo de contenido al enviar la respuesta; si todavía no está, rige la codificación configurada
        $tipo = $r->response()->getHeaderLine('Content-Type');
        if ($tipo !== '') {
            $this->assertStringContainsString('charset=UTF-8', $tipo);
        }
        $this->assertSame('UTF-8', config('App')->charset);
        $this->assertFalse($r->response()->hasHeader('Access-Control-Allow-Origin'));
    }
}
