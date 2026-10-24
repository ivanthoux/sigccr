<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas del SIG-CCR. Cada ruta protegida declara la sesión exigida y el permiso requerido (RNF-01).
 *
 * @var RouteCollection $routes
 */
$routes->get('/', static fn () => redirect()->to('admin'));

// Estado de la arquitectura ejecutable (sin datos sensibles): versión, base de datos y hora del servidor
$routes->get('estado', 'Estado::index');

// Panel administrativo
$routes->group('admin', static function (RouteCollection $routes): void {
    // Iteración 1 (SS-01): inicio y cierre de sesión
    // $routes->get('login', 'Admin\Acceso::login');
    // $routes->post('login', 'Admin\Acceso::autenticar');
    // $routes->post('logout', 'Admin\Acceso::cerrar', ['filter' => 'auth:panel']);

    $routes->group('', ['filter' => 'auth:panel'], static function (RouteCollection $routes): void {
        // Ejemplo del patrón de declaración:
        // $routes->get('clientes', 'Admin\Clientes::index', ['filter' => 'permiso:clientes.consultar']);
        // $routes->post('clientes', 'Admin\Clientes::store', ['filter' => 'permiso:clientes.registrar']);
    });
});

// Portal de autogestión (iteración 4)
$routes->group('portal', static function (RouteCollection $routes): void {
    // $routes->get('login', 'Portal\Acceso::login');
});

// Notificaciones de servicios externos (sin sesión ni CSRF; se validan por firma, RNF-08)
$routes->group('api', static function (RouteCollection $routes): void {
    // $routes->post('webhooks/pasarela', 'Api\Webhooks\Pasarela::recibir');
});
