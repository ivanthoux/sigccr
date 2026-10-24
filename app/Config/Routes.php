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
    // SS-01: acceso sin sesión (CU-01, CU-02). El límite por dirección de origen se aplica en Config\Filters (RN-62).
    $routes->get('login', 'Admin\Acceso::login', ['as' => 'acceso.login']);
    $routes->post('login', 'Admin\Acceso::autenticar');
    $routes->get('recuperar', 'Admin\Acceso::recuperar', ['as' => 'acceso.recuperar']);
    $routes->post('recuperar', 'Admin\Acceso::solicitarRecuperacion');
    $routes->get('restablecer/(:segment)', 'Admin\Acceso::restablecer/$1');
    $routes->post('restablecer/(:segment)', 'Admin\Acceso::guardarContrasena/$1');

    $routes->group('', ['filter' => 'auth:panel'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\Inicio::index', ['as' => 'panel.inicio']);
        // CU-03: confirmación y cierre de sesión
        $routes->get('salir', 'Admin\Acceso::confirmarCierre', ['as' => 'acceso.salir']);
        $routes->post('salir', 'Admin\Acceso::cerrar');

        // Cada caso de uso declara su permiso y el nombre de ruta que usa el menú (Config\Menu), por ejemplo:
        // $routes->get('clientes', 'Admin\Clientes::index', ['as' => 'clientes.localizar', 'filter' => 'permiso:clientes.localizar']);
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
