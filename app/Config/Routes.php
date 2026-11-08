<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas del SIG-CCR. Cada ruta protegida declara la sesión exigida y el permiso requerido (RNF-01).
 *
 * @var RouteCollection $routes
 */
// Portada pública: orienta a los clientes al portal y ofrece el acceso del personal al panel
$routes->get('/', 'Portada::index', ['as' => 'portada']);

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
    $routes->get('verificar-correo/(:segment)', 'Admin\Acceso::verificarCorreo/$1');
    $routes->post('verificar-correo/(:segment)', 'Admin\Acceso::confirmarCorreo/$1');

    $routes->group('', ['filter' => 'auth:panel'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Admin\Inicio::index', ['as' => 'panel.inicio']);
        // CU-03: confirmación y cierre de sesión
        $routes->get('salir', 'Admin\Acceso::confirmarCierre', ['as' => 'acceso.salir']);
        $routes->post('salir', 'Admin\Acceso::cerrar');

        // SS-01 Usuarios internos (CU-05 a CU-10, CU-53)
        $routes->group('usuarios', static function (RouteCollection $routes): void {
            $routes->get('', 'Admin\Usuarios::index', ['as' => 'usuarios.localizar', 'filter' => 'permiso:usuarios.localizar']);
            $routes->get('nuevo', 'Admin\Usuarios::nuevo', ['as' => 'usuarios.registrar', 'filter' => 'permiso:usuarios.registrar']);
            $routes->post('', 'Admin\Usuarios::registrar', ['filter' => 'permiso:usuarios.registrar']);
            $routes->get('(:num)', 'Admin\Usuarios::ver/$1', ['filter' => 'permiso:usuarios.localizar']);
            $routes->get('(:num)/editar', 'Admin\Usuarios::editar/$1', ['filter' => 'permiso:usuarios.modificar']);
            $routes->post('(:num)/editar', 'Admin\Usuarios::actualizar/$1', ['filter' => 'permiso:usuarios.modificar']);
            $routes->get('(:num)/restablecer', 'Admin\Usuarios::confirmar/$1/restablecer', ['filter' => 'permiso:usuarios.restablecer-credenciales']);
            $routes->post('(:num)/restablecer', 'Admin\Usuarios::restablecer/$1', ['filter' => 'permiso:usuarios.restablecer-credenciales']);
            $routes->get('(:num)/desactivar', 'Admin\Usuarios::confirmar/$1/desactivar', ['filter' => 'permiso:usuarios.desactivar']);
            $routes->post('(:num)/desactivar', 'Admin\Usuarios::desactivar/$1', ['filter' => 'permiso:usuarios.desactivar']);
            $routes->get('(:num)/reactivar', 'Admin\Usuarios::confirmar/$1/reactivar', ['filter' => 'permiso:usuarios.reactivar']);
            $routes->post('(:num)/reactivar', 'Admin\Usuarios::reactivar/$1', ['filter' => 'permiso:usuarios.reactivar']);
            $routes->get('(:num)/rol', 'Admin\Usuarios::confirmar/$1/rol', ['filter' => 'permiso:usuarios.asignar-rol']);
            $routes->post('(:num)/rol', 'Admin\Usuarios::asignarRol/$1', ['filter' => 'permiso:usuarios.asignar-rol']);
        });

        // SS-02 Clientes (CU-11 a CU-15, CU-54)
        $routes->group('clientes', static function (RouteCollection $routes): void {
            $routes->get('', 'Admin\Clientes::index', ['as' => 'clientes.localizar', 'filter' => 'permiso:clientes.localizar']);
            $routes->get('nuevo', 'Admin\Clientes::nuevo', ['as' => 'clientes.registrar', 'filter' => 'permiso:clientes.registrar']);
            $routes->post('', 'Admin\Clientes::registrar', ['filter' => 'permiso:clientes.registrar']);
            // D-18: localidades de una provincia para el formulario del cliente (alta y modificación)
            $routes->get('localidades/(:num)', 'Admin\Clientes::localidades/$1', ['filter' => 'permiso:clientes.registrar']);
            $routes->get('(:num)', 'Admin\Clientes::ver/$1', ['filter' => 'permiso:clientes.consultar-perfil']);
            $routes->get('(:num)/historial', 'Admin\Clientes::historial/$1', ['filter' => 'permiso:clientes.consultar-perfil']);
            $routes->get('(:num)/documentos/(:num)', 'Admin\Clientes::descargarDocumento/$1/$2', ['filter' => 'permiso:clientes.consultar-perfil']);
            $routes->post('(:num)/documentos', 'Admin\Clientes::agregarDocumentos/$1', ['filter' => 'permiso:clientes.modificar']);
            $routes->get('(:num)/editar', 'Admin\Clientes::editar/$1', ['filter' => 'permiso:clientes.modificar']);
            $routes->post('(:num)/editar', 'Admin\Clientes::actualizar/$1', ['filter' => 'permiso:clientes.modificar']);
            $routes->get('(:num)/desactivar', 'Admin\Clientes::confirmar/$1/desactivar', ['filter' => 'permiso:clientes.desactivar']);
            $routes->post('(:num)/desactivar', 'Admin\Clientes::desactivar/$1', ['filter' => 'permiso:clientes.desactivar']);
            $routes->get('(:num)/reactivar', 'Admin\Clientes::confirmar/$1/reactivar', ['filter' => 'permiso:clientes.reactivar']);
            $routes->post('(:num)/reactivar', 'Admin\Clientes::reactivar/$1', ['filter' => 'permiso:clientes.reactivar']);
        });

        // SS-04 Simulación y solicitudes (CU-21 a CU-23, CU-84; localización y consulta de CU-29 y CU-30)
        $routes->get('simulaciones', 'Admin\Simulaciones::index', ['as' => 'solicitudes.simular', 'filter' => 'permiso:solicitudes.simular']);
        $routes->post('simulaciones', 'Admin\Simulaciones::simular', ['filter' => 'permiso:solicitudes.simular']);
        $routes->get('simulaciones/(:num)', 'Admin\Simulaciones::ver/$1', ['filter' => 'permiso:solicitudes.simular']);
        // CU-99 Simular todas las líneas (D-10)
        $routes->get('simulaciones/comparar', 'Admin\Comparativas::index', ['as' => 'simulaciones.comparar', 'filter' => 'permiso:solicitudes.simular-comparativa']);
        $routes->post('simulaciones/comparar', 'Admin\Comparativas::simular', ['filter' => 'permiso:solicitudes.simular-comparativa']);
        $routes->get('simulaciones/comparar/(:num)', 'Admin\Comparativas::ver/$1', ['filter' => 'permiso:solicitudes.simular-comparativa']);
        $routes->get('evaluaciones/contingencia', 'Admin\Evaluaciones::pendientes', ['as' => 'evaluaciones.contingencia', 'filter' => 'permiso:evaluaciones.registrar-contingencia']);
        // SS-05 Cajas y fondos (CU-31, CU-36, CU-72, CU-80, CU-81, CU-85)
        $routes->get('caja', 'Admin\MiCaja::index', ['as' => 'cajas.abrir', 'filter' => 'permiso:cajas.abrir']);
        $routes->post('caja', 'Admin\MiCaja::abrir', ['filter' => 'permiso:cajas.abrir']);
        $routes->get('caja/cierre', 'Admin\MiCaja::cierre', ['as' => 'cajas.cerrar', 'filter' => 'permiso:cajas.cerrar']);
        $routes->post('caja/cierre', 'Admin\MiCaja::cerrar', ['filter' => 'permiso:cajas.cerrar']);
        $routes->get('cobros', 'Admin\Cobros::index', ['as' => 'cobros.registrar', 'filter' => 'permiso:cobros.registrar']);
        $routes->post('cobros', 'Admin\Cobros::registrar', ['filter' => 'permiso:cobros.registrar']);
        $routes->get('cobros/comprobantes', 'Admin\Cobros::comprobantes', ['as' => 'cobros.emitir-comprobante', 'filter' => 'permiso:cobros.emitir-comprobante']);
        $routes->get('cobros/(:num)/comprobante', 'Admin\Cobros::comprobante/$1', ['filter' => 'permiso:cobros.emitir-comprobante']);
        $routes->post('cobros/(:num)/reemitir', 'Admin\Cobros::reemitir/$1', ['filter' => 'permiso:cobros.emitir-comprobante']);
        // SS-06 Refinanciaciones (CU-39 a CU-43, CU-74, CU-96) y condonación de punitorios (CU-44)
        $routes->get('refinanciaciones', 'Admin\Refinanciaciones::index', ['as' => 'refinanciaciones.localizar', 'filter' => 'permiso:refinanciaciones.localizar']);
        $routes->get('refinanciaciones/simular', 'Admin\Refinanciaciones::simular', ['as' => 'refinanciaciones.simular', 'filter' => 'permiso:refinanciaciones.simular']);
        $routes->post('refinanciaciones/simular', 'Admin\Refinanciaciones::crearSimulacion', ['filter' => 'permiso:refinanciaciones.simular']);
        $routes->get('refinanciaciones/simulaciones/(:num)', 'Admin\Refinanciaciones::simulacion/$1', ['filter' => 'permiso:refinanciaciones.simular']);
        $routes->get('refinanciaciones/nueva', 'Admin\Refinanciaciones::nueva', ['as' => 'refinanciaciones.registrar-propuesta', 'filter' => 'permiso:refinanciaciones.registrar-propuesta']);
        $routes->post('refinanciaciones/simulaciones/(:num)/propuesta', 'Admin\Refinanciaciones::registrarPropuesta/$1', ['filter' => 'permiso:refinanciaciones.registrar-propuesta']);
        $routes->get('refinanciaciones/(:num)', 'Admin\Refinanciaciones::ver/$1', ['filter' => 'permiso:refinanciaciones.consultar']);
        $routes->get('refinanciaciones/(:num)/resolver', 'Admin\Refinanciaciones::resolucion/$1', ['filter' => 'permiso:refinanciaciones.resolver']);
        $routes->post('refinanciaciones/(:num)/resolver', 'Admin\Refinanciaciones::resolver/$1', ['filter' => 'permiso:refinanciaciones.resolver']);
        $routes->get('refinanciaciones/(:num)/aplicar', 'Admin\Refinanciaciones::aceptacion/$1', ['filter' => 'permiso:refinanciaciones.aplicar']);
        $routes->post('refinanciaciones/(:num)/aplicar', 'Admin\Refinanciaciones::aplicar/$1', ['filter' => 'permiso:refinanciaciones.aplicar']);
        $routes->get('refinanciaciones/(:num)/cancelar', 'Admin\Refinanciaciones::cancelacion/$1', ['filter' => 'permiso:refinanciaciones.cancelar-propuesta']);
        $routes->post('refinanciaciones/(:num)/cancelar', 'Admin\Refinanciaciones::cancelar/$1', ['filter' => 'permiso:refinanciaciones.cancelar-propuesta']);
        $routes->get('punitorios/condonar', 'Admin\Condonaciones::index', ['as' => 'punitorios.condonar', 'filter' => 'permiso:punitorios.condonar']);
        $routes->post('punitorios/condonar', 'Admin\Condonaciones::condonar', ['filter' => 'permiso:punitorios.condonar']);

        // SS-05 Saldos a favor y devoluciones (CU-76 a CU-78)
        $routes->get('saldos-favor/aplicar', 'Admin\SaldosFavor::aplicacion', ['as' => 'saldos-favor.aplicar', 'filter' => 'permiso:saldos-favor.aplicar']);
        $routes->post('saldos-favor/aplicar', 'Admin\SaldosFavor::aplicar', ['filter' => 'permiso:saldos-favor.aplicar']);
        $routes->get('saldos-favor/devoluciones', 'Admin\SaldosFavor::autorizacion', ['as' => 'saldos-favor.autorizar-devolucion', 'filter' => 'permiso:saldos-favor.autorizar-devolucion']);
        $routes->post('saldos-favor/(:num)/devolucion', 'Admin\SaldosFavor::autorizar/$1', ['filter' => 'permiso:saldos-favor.autorizar-devolucion']);
        $routes->get('saldos-favor/autorizaciones/(:num)', 'Admin\SaldosFavor::ver/$1', ['filter' => 'permiso:saldos-favor.autorizar-devolucion']);
        $routes->post('saldos-favor/autorizaciones/(:num)/anular', 'Admin\SaldosFavor::anular/$1', ['filter' => 'permiso:saldos-favor.autorizar-devolucion']);
        $routes->get('devoluciones', 'Admin\SaldosFavor::devoluciones', ['as' => 'saldos-favor.ejecutar-devolucion', 'filter' => 'permiso:saldos-favor.ejecutar-devolucion']);
        $routes->get('devoluciones/(:num)', 'Admin\SaldosFavor::ver/$1', ['filter' => 'permiso:saldos-favor.ejecutar-devolucion']);
        $routes->post('devoluciones/(:num)/ejecutar', 'Admin\SaldosFavor::ejecutar/$1', ['filter' => 'permiso:saldos-favor.ejecutar-devolucion']);

        // SS-05 Pagos electrónicos (CU-34, CU-38)
        $routes->get('pagos-electronicos/nueva', 'Admin\PagosElectronicos::nueva', ['as' => 'pagos-electronicos.generar-orden', 'filter' => 'permiso:pagos-electronicos.generar-orden']);
        $routes->post('pagos-electronicos/nueva', 'Admin\PagosElectronicos::generar', ['filter' => 'permiso:pagos-electronicos.generar-orden']);
        $routes->get('pagos-electronicos', 'Admin\PagosElectronicos::index', ['as' => 'pagos-electronicos.consultar-estado', 'filter' => 'permiso:pagos-electronicos.consultar-estado']);
        $routes->get('pagos-electronicos/(:num)', 'Admin\PagosElectronicos::ver/$1', ['filter' => 'permiso:pagos-electronicos.consultar-estado']);
        $routes->post('pagos-electronicos/(:num)/consultar', 'Admin\PagosElectronicos::consultar/$1', ['filter' => 'permiso:pagos-electronicos.consultar-estado']);
        $routes->get('cobros/anulaciones', 'Admin\Cobros::comprobantes', ['as' => 'cobros.anular', 'filter' => 'permiso:cobros.anular']);
        $routes->get('cobros/(:num)/anular', 'Admin\Cobros::anulacion/$1', ['filter' => 'permiso:cobros.anular']);
        $routes->post('cobros/(:num)/anular', 'Admin\Cobros::anular/$1', ['filter' => 'permiso:cobros.anular']);
        $routes->get('revocaciones', 'Admin\Revocaciones::index', ['as' => 'creditos.revocar', 'filter' => 'permiso:creditos.revocar']);
        $routes->get('revocaciones/(:num)', 'Admin\Revocaciones::credito/$1', ['filter' => 'permiso:creditos.revocar']);
        $routes->post('revocaciones/(:num)', 'Admin\Revocaciones::registrar/$1', ['filter' => 'permiso:creditos.revocar']);
        $routes->post('revocaciones/(:num)/confirmar', 'Admin\Revocaciones::confirmar/$1', ['filter' => 'permiso:creditos.revocar']);
        $routes->get('cajas/movimientos', 'Admin\Cajas::movimientos', ['as' => 'cajas.consultar-movimientos', 'filter' => 'permiso:cajas.consultar-movimientos']);
        $routes->get('cajas/jornadas/(:num)', 'Admin\Cajas::jornada/$1', ['filter' => 'permiso:cajas.consultar-movimientos']);
        $routes->get('cajas', 'Admin\Cajas::index', ['as' => 'cajas.registrar', 'filter' => 'permiso:cajas.registrar']);
        $routes->post('cajas', 'Admin\Cajas::registrar', ['filter' => 'permiso:cajas.registrar']);
        $routes->post('cajas/(:num)/asignar', 'Admin\Cajas::asignar/$1', ['filter' => 'permiso:cajas.asignar-cajero']);
        $routes->post('cajas/(:num)/finalizar-asignacion', 'Admin\Cajas::finalizarAsignacion/$1', ['filter' => 'permiso:cajas.asignar-cajero']);
        $routes->post('cajas/(:num)/estado', 'Admin\Cajas::cambiarEstado/$1', ['filter' => 'permiso:cajas.cambiar-estado']);
        $routes->get('fondos', 'Admin\Fondos::index', ['as' => 'fondos.movimientos', 'filter' => 'permiso:fondos.registrar-movimiento']);
        $routes->post('fondos', 'Admin\Fondos::registrar', ['filter' => 'permiso:fondos.registrar-movimiento']);
        $routes->post('fondos/(:num)/corregir', 'Admin\Fondos::corregir/$1', ['filter' => 'permiso:fondos.registrar-movimiento']);
        $routes->post('fondos/(:num)/confirmar', 'Admin\Fondos::confirmar/$1', ['filter' => 'permiso:fondos.registrar-movimiento']);
        $routes->post('fondos/(:num)/anular', 'Admin\Fondos::anular/$1', ['filter' => 'permiso:fondos.registrar-movimiento']);
        // CU-82 Calendario operativo (RN-49)
        $routes->get('calendario', 'Admin\Calendario::index', ['as' => 'calendario.actualizar', 'filter' => 'permiso:calendario.actualizar']);
        $routes->post('calendario', 'Admin\Calendario::agregar', ['filter' => 'permiso:calendario.actualizar']);
        $routes->post('calendario/baja', 'Admin\Calendario::baja', ['filter' => 'permiso:calendario.actualizar']);
        // CU-100 y CU-101: catálogo de documentación y categorías del monotributo (D-25, D-26)
        $routes->get('documentacion', 'Admin\Documentacion::index', ['as' => 'documentacion.configurar', 'filter' => 'permiso:documentacion.configurar']);
        $routes->post('documentacion/tipos', 'Admin\Documentacion::registrarTipo', ['filter' => 'permiso:documentacion.configurar']);
        $routes->post('documentacion/tipos/(:num)', 'Admin\Documentacion::modificarTipo/$1', ['filter' => 'permiso:documentacion.configurar']);
        $routes->post('documentacion/sugeridos', 'Admin\Documentacion::guardarSugeridos', ['filter' => 'permiso:documentacion.configurar']);
        $routes->get('monotributo', 'Admin\Monotributo::index', ['as' => 'monotributo.actualizar', 'filter' => 'permiso:monotributo.actualizar']);
        $routes->post('monotributo', 'Admin\Monotributo::registrar', ['filter' => 'permiso:monotributo.actualizar']);
        // CU-26 y CU-27: bandejas del Gerente y del Cajero
        $routes->get('resoluciones', 'Admin\Resoluciones::index', ['as' => 'solicitudes.resolver', 'filter' => 'permiso:solicitudes.resolver']);
        $routes->get('contratos', 'Admin\Contratos::index', ['as' => 'contratos.registrar-firmado', 'filter' => 'permiso:contratos.registrar-firmado']);
        $routes->get('contratos/(:num)', 'Admin\Contratos::ver/$1', ['filter' => 'permiso:contratos.registrar-firmado']);
        $routes->post('contratos/(:num)', 'Admin\Contratos::registrar/$1', ['filter' => 'permiso:contratos.registrar-firmado']);
        $routes->get('liquidaciones', 'Admin\Liquidaciones::index', ['as' => 'creditos.liquidar', 'filter' => 'permiso:creditos.liquidar']);
        $routes->get('liquidaciones/(:num)', 'Admin\Liquidaciones::ver/$1', ['filter' => 'permiso:creditos.liquidar']);
        $routes->post('liquidaciones/(:num)', 'Admin\Liquidaciones::liquidar/$1', ['filter' => 'permiso:creditos.liquidar']);
        $routes->get('liquidaciones/(:num)/constancia', 'Admin\Liquidaciones::constancia/$1', ['filter' => 'permiso:creditos.liquidar']);
        $routes->group('solicitudes', static function (RouteCollection $routes): void {
            $routes->get('', 'Admin\Solicitudes::index', ['as' => 'solicitudes.localizar', 'filter' => 'permiso:solicitudes.localizar']);
            $routes->get('nueva', 'Admin\Solicitudes::nueva', ['as' => 'solicitudes.registrar', 'filter' => 'permiso:solicitudes.registrar']);
            $routes->get('nueva/(:num)', 'Admin\Solicitudes::confirmar/$1', ['filter' => 'permiso:solicitudes.registrar']);
            $routes->post('', 'Admin\Solicitudes::registrar', ['filter' => 'permiso:solicitudes.registrar']);
            $routes->get('(:num)', 'Admin\Solicitudes::ver/$1', ['as' => 'solicitudes.consultar', 'filter' => 'permiso:solicitudes.consultar']);
            $routes->get('(:num)/verificar', 'Admin\Solicitudes::verificacion/$1', ['filter' => 'permiso:solicitudes.verificar-legajo']);
            $routes->post('(:num)/verificar', 'Admin\Solicitudes::verificar/$1', ['filter' => 'permiso:solicitudes.verificar-legajo']);
            $routes->post('(:num)/documentos', 'Admin\Solicitudes::incorporarDocumentos/$1', ['filter' => 'permiso:solicitudes.verificar-legajo']);
            $routes->get('(:num)/corregir', 'Admin\Solicitudes::correccion/$1', ['filter' => 'permiso:solicitudes.registrar-correccion']);
            $routes->post('(:num)/corregir', 'Admin\Solicitudes::corregir/$1', ['filter' => 'permiso:solicitudes.registrar-correccion']);
            // CU-24 y CU-25: evaluación crediticia (la automática, CU-86, la ejecuta el proceso evaluacion:ejecutar)
            $routes->get('(:num)/evaluacion', 'Admin\Evaluaciones::ver/$1', ['filter' => 'permiso:evaluaciones.consultar']);
            $routes->get('(:num)/contingencia', 'Admin\Evaluaciones::contingencia/$1', ['filter' => 'permiso:evaluaciones.registrar-contingencia']);
            $routes->post('(:num)/contingencia', 'Admin\Evaluaciones::registrarContingencia/$1', ['filter' => 'permiso:evaluaciones.registrar-contingencia']);
            // CU-64, CU-79 y CU-87 (regeneración): alternativas de financiación
            $routes->get('(:num)/alternativas', 'Admin\Alternativas::index/$1', ['filter' => 'permiso:alternativas.consultar']);
            $routes->post('(:num)/alternativas/regenerar', 'Admin\Alternativas::regenerar/$1', ['filter' => 'permiso:alternativas.consultar']);
            $routes->get('(:num)/alternativas/(:num)', 'Admin\Alternativas::ver/$1/$2', ['filter' => 'permiso:alternativas.consultar']);
            $routes->post('(:num)/alternativas/(:num)/confirmar', 'Admin\Alternativas::confirmar/$1/$2', ['filter' => 'permiso:alternativas.confirmar']);
            // CU-26 y CU-28
            $routes->get('(:num)/resolver', 'Admin\Resoluciones::ver/$1', ['filter' => 'permiso:solicitudes.resolver']);
            $routes->post('(:num)/resolver', 'Admin\Resoluciones::resolver/$1', ['filter' => 'permiso:solicitudes.resolver']);
            $routes->get('(:num)/cancelar', 'Admin\Resoluciones::cancelacion/$1', ['filter' => 'permiso:solicitudes.cancelar']);
            $routes->post('(:num)/cancelar', 'Admin\Resoluciones::cancelar/$1', ['filter' => 'permiso:solicitudes.cancelar']);
        });

        // SS-10 Alertas (CU-83)
        $routes->get('alertas', 'Admin\Alertas::index', ['as' => 'alertas.consultar', 'filter' => 'permiso:alertas.consultar']);
        $routes->get('alertas/(:num)', 'Admin\Alertas::ver/$1', ['filter' => 'permiso:alertas.consultar']);

        // SS-03 Líneas de crédito (CU-16 a CU-20, CU-73)
        $routes->group('lineas', static function (RouteCollection $routes): void {
            $routes->get('', 'Admin\Lineas::index', ['as' => 'lineas.localizar', 'filter' => 'permiso:lineas.localizar']);
            $routes->get('nueva', 'Admin\Lineas::nuevo', ['as' => 'lineas.registrar', 'filter' => 'permiso:lineas.registrar']);
            $routes->post('', 'Admin\Lineas::registrar', ['filter' => 'permiso:lineas.registrar']);
            $routes->get('(:num)', 'Admin\Lineas::ver/$1', ['filter' => 'permiso:lineas.consultar']);
            $routes->get('(:num)/editar', 'Admin\Lineas::editar/$1', ['filter' => 'permiso:lineas.modificar']);
            $routes->post('(:num)/editar', 'Admin\Lineas::actualizar/$1', ['filter' => 'permiso:lineas.modificar']);
            $routes->get('(:num)/suspender', 'Admin\Lineas::confirmar/$1/suspender', ['filter' => 'permiso:lineas.suspender']);
            $routes->post('(:num)/suspender', 'Admin\Lineas::suspender/$1', ['filter' => 'permiso:lineas.suspender']);
            $routes->get('(:num)/reactivar', 'Admin\Lineas::confirmar/$1/reactivar', ['filter' => 'permiso:lineas.reactivar']);
            $routes->post('(:num)/reactivar', 'Admin\Lineas::reactivar/$1', ['filter' => 'permiso:lineas.reactivar']);
        });

        // SS-10 Parámetros del motor de evaluación (CU-58, D-13)
        $routes->group('parametros', static function (RouteCollection $routes): void {
            $routes->get('evaluacion', 'Admin\Parametros::evaluacion', ['as' => 'parametros.evaluacion', 'filter' => 'permiso:parametros.evaluacion']);
            $routes->post('evaluacion', 'Admin\Parametros::guardarEvaluacion', ['filter' => 'permiso:parametros.evaluacion']);
            $routes->get('estructuracion', 'Admin\Parametros::estructuracion', ['as' => 'parametros.estructuracion', 'filter' => 'permiso:parametros.estructuracion']);
            $routes->post('estructuracion', 'Admin\Parametros::guardarEstructuracion', ['filter' => 'permiso:parametros.estructuracion']);
            $routes->get('morosidad', 'Admin\Parametros::morosidad', ['as' => 'parametros.morosidad', 'filter' => 'permiso:parametros.morosidad']);
            $routes->post('morosidad', 'Admin\Parametros::guardarMorosidad', ['filter' => 'permiso:parametros.morosidad']);
        });

        // Cada caso de uso declara su permiso y el nombre de ruta que usa el menú (Config\Menu), por ejemplo:
        // $routes->get('clientes', 'Admin\Clientes::index', ['as' => 'clientes.localizar', 'filter' => 'permiso:clientes.localizar']);
    });
});

// Portal de autogestión del cliente (SS-07)
$routes->group('portal', static function (RouteCollection $routes): void {
    // Verificación del correo del cliente desde el enlace (sin sesión)
    $routes->get('verificar-correo/(:segment)', 'Portal\Verificacion::mostrar/$1');
    $routes->post('verificar-correo/(:segment)', 'Portal\Verificacion::confirmar/$1');
    // CU-04 Activar o Rehabilitar Acceso del Cliente (sin sesión; límite de intentos por dirección en Config\Filters)
    $routes->get('activar', 'Portal\Activacion::solicitud', ['as' => 'portal.activar']);
    $routes->post('activar', 'Portal\Activacion::solicitar');
    $routes->get('activar/(:segment)', 'Portal\Activacion::contrasena/$1');
    $routes->post('activar/(:segment)', 'Portal\Activacion::guardarContrasena/$1');
    // CU-01 y CU-02 del Cliente (sin sesión; límite de intentos por dirección en Config\Filters)
    $routes->get('login', 'Portal\Acceso::login', ['as' => 'portal.login']);
    $routes->post('login', 'Portal\Acceso::autenticar');
    $routes->get('recuperar', 'Portal\Acceso::recuperar', ['as' => 'portal.recuperar']);
    $routes->post('recuperar', 'Portal\Acceso::solicitarRecuperacion');
    $routes->get('restablecer/(:segment)', 'Portal\Acceso::restablecer/$1');
    $routes->post('restablecer/(:segment)', 'Portal\Acceso::guardarContrasena/$1');

    // Con sesión de cliente (RN-08): 15 minutos de inactividad (RNF-11)
    $routes->group('', ['filter' => 'auth:portal'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Portal\Inicio::index', ['as' => 'portal.inicio']);
        // CU-03 del Cliente
        $routes->get('salir', 'Portal\Acceso::confirmarCierre', ['as' => 'portal.salir']);
        $routes->post('salir', 'Portal\Acceso::cerrar');
        // Iteración 4: CU-45 a CU-49 con sus nombres de ruta de Config\Menu::$portal
    });
});

// Notificaciones de servicios externos (sin sesión ni CSRF; se validan por firma, RNF-08)
$routes->group('api', static function (RouteCollection $routes): void {
    $routes->post('webhooks/pasarela', 'Api\Webhooks\Pasarela::recibir', ['as' => 'webhooks.pasarela']);
});

// Simulador de la pasarela de pagos: solo en desarrollo y pruebas
if (ENVIRONMENT !== 'production') {
    $routes->get('simulador/pagar/(:segment)', 'Simulador\Pasarela::pagar/$1');
    $routes->post('simulador/pagar/(:segment)', 'Simulador\Pasarela::registrar/$1');
    $routes->get('simulador/devoluciones', 'Simulador\Pasarela::devoluciones');
    $routes->post('simulador/devoluciones', 'Simulador\Pasarela::configurarDevoluciones');
}
