<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Menú del panel administrativo (RNF-07). Cada opción declara el permiso que la habilita y el nombre de su ruta;
 * el menú muestra solo las opciones permitidas al rol cuya ruta ya existe, así cada iteración las habilita al
 * construir su caso de uso.
 */
class Menu extends BaseConfig
{
    /**
     * Cada opción tiene su propio ícono, para distinguirlas de un vistazo; el de la sección queda como respaldo.
     *
     * @var list<array{titulo: string, icono: string, opciones: list<array{texto: string, icono?: string, ruta: string, permiso: ?string}>}>
     */
    public array $secciones = [
        ['titulo' => 'Crédito', 'icono' => 'bi-cash-coin', 'opciones' => [
            ['texto' => 'Clientes', 'icono' => 'bi-people', 'ruta' => 'clientes.localizar', 'permiso' => 'clientes.localizar'],
            ['texto' => 'Pre-registros del portal', 'icono' => 'bi-person-plus', 'ruta' => 'pre-registros.consultar', 'permiso' => 'clientes.registrar'],
            ['texto' => 'Solicitudes', 'icono' => 'bi-file-earmark-text', 'ruta' => 'solicitudes.localizar', 'permiso' => 'solicitudes.localizar'],
            ['texto' => 'Derechos del titular', 'icono' => 'bi-shield-check', 'ruta' => 'derechos.atender', 'permiso' => 'derechos.atender'],
            ['texto' => 'Contingencias', 'icono' => 'bi-exclamation-diamond', 'ruta' => 'evaluaciones.contingencia', 'permiso' => 'evaluaciones.registrar-contingencia'],
            ['texto' => 'Resolver solicitudes', 'icono' => 'bi-check2-square', 'ruta' => 'solicitudes.resolver', 'permiso' => 'solicitudes.resolver'],
            ['texto' => 'Contratos', 'icono' => 'bi-file-earmark-check', 'ruta' => 'contratos.registrar-firmado', 'permiso' => 'contratos.registrar-firmado'],
            ['texto' => 'Simulador', 'icono' => 'bi-calculator', 'ruta' => 'solicitudes.simular', 'permiso' => 'solicitudes.simular'],
            ['texto' => 'Comparar líneas', 'icono' => 'bi-layout-split', 'ruta' => 'simulaciones.comparar', 'permiso' => 'solicitudes.simular-comparativa'],
            ['texto' => 'Líneas de crédito', 'icono' => 'bi-sliders', 'ruta' => 'lineas.localizar', 'permiso' => 'lineas.localizar'],
        ]],
        ['titulo' => 'Caja y cobranzas', 'icono' => 'bi-safe', 'opciones' => [
            ['texto' => 'Mi caja', 'icono' => 'bi-safe', 'ruta' => 'cajas.abrir', 'permiso' => 'cajas.abrir'],
            ['texto' => 'Cobro en ventanilla', 'icono' => 'bi-cash-stack', 'ruta' => 'cobros.registrar', 'permiso' => 'cobros.registrar'],
            ['texto' => 'Comprobantes de pago', 'icono' => 'bi-receipt', 'ruta' => 'cobros.emitir-comprobante', 'permiso' => 'cobros.emitir-comprobante'],
            ['texto' => 'Anular cobros', 'icono' => 'bi-x-octagon', 'ruta' => 'cobros.anular', 'permiso' => 'cobros.anular'],
            ['texto' => 'Pagos electrónicos', 'icono' => 'bi-credit-card', 'ruta' => 'pagos-electronicos.consultar-estado', 'permiso' => 'pagos-electronicos.consultar-estado'],
            ['texto' => 'Cancelación anticipada', 'icono' => 'bi-calendar-check', 'ruta' => 'creditos.cancelar-anticipadamente', 'permiso' => 'creditos.cancelar-anticipadamente'],
            ['texto' => 'Aplicar saldo a favor', 'icono' => 'bi-plus-slash-minus', 'ruta' => 'saldos-favor.aplicar', 'permiso' => 'saldos-favor.aplicar'],
            ['texto' => 'Devoluciones', 'icono' => 'bi-arrow-return-left', 'ruta' => 'saldos-favor.ejecutar-devolucion', 'permiso' => 'saldos-favor.ejecutar-devolucion'],
            ['texto' => 'Autorizar devoluciones', 'icono' => 'bi-shield-check', 'ruta' => 'saldos-favor.autorizar-devolucion', 'permiso' => 'saldos-favor.autorizar-devolucion'],
            ['texto' => 'Revocación del préstamo', 'icono' => 'bi-arrow-counterclockwise', 'ruta' => 'creditos.revocar', 'permiso' => 'creditos.revocar'],
            ['texto' => 'Movimientos de caja', 'icono' => 'bi-arrow-left-right', 'ruta' => 'cajas.consultar-movimientos', 'permiso' => 'cajas.consultar-movimientos'],
            ['texto' => 'Liquidar créditos', 'icono' => 'bi-bank', 'ruta' => 'creditos.liquidar', 'permiso' => 'creditos.liquidar'],
            ['texto' => 'Informe de caja diaria', 'icono' => 'bi-clipboard-data', 'ruta' => 'cajas.informe-diario', 'permiso' => 'cajas.informe-diario'],
            ['texto' => 'Cajas', 'icono' => 'bi-boxes', 'ruta' => 'cajas.registrar', 'permiso' => 'cajas.registrar'],
        ]],
        ['titulo' => 'Cartera', 'icono' => 'bi-briefcase', 'opciones' => [
            ['texto' => 'Refinanciaciones', 'icono' => 'bi-arrow-repeat', 'ruta' => 'refinanciaciones.localizar', 'permiso' => 'refinanciaciones.localizar'],
            ['texto' => 'Simular refinanciación', 'icono' => 'bi-sliders2-vertical', 'ruta' => 'refinanciaciones.simular', 'permiso' => 'refinanciaciones.simular'],
            ['texto' => 'Condonar punitorios', 'icono' => 'bi-percent', 'ruta' => 'punitorios.condonar', 'permiso' => 'punitorios.condonar'],
            ['texto' => 'Campañas', 'icono' => 'bi-megaphone', 'ruta' => 'campanas.consultar', 'permiso' => 'campanas.consultar'],
            ['texto' => 'Régimen informativo', 'icono' => 'bi-file-earmark-spreadsheet', 'ruta' => 'regimen.consultar', 'permiso' => 'regimen.consultar'],
        ]],
        ['titulo' => 'Gestión', 'icono' => 'bi-graph-up', 'opciones' => [
            ['texto' => 'Tablero gerencial', 'icono' => 'bi-speedometer2', 'ruta' => 'tablero.consultar', 'permiso' => 'tablero.consultar'],
            ['texto' => 'Reportes', 'icono' => 'bi-bar-chart-line', 'ruta' => 'reportes.index', 'permiso' => 'reportes.cartera'],
            ['texto' => 'Fondos', 'icono' => 'bi-piggy-bank', 'ruta' => 'fondos.movimientos', 'permiso' => 'fondos.registrar-movimiento'],
            ['texto' => 'Parámetros de evaluación', 'icono' => 'bi-clipboard-check', 'ruta' => 'parametros.evaluacion', 'permiso' => 'parametros.evaluacion'],
            ['texto' => 'Parámetros de estructuración', 'icono' => 'bi-diagram-3', 'ruta' => 'parametros.estructuracion', 'permiso' => 'parametros.estructuracion'],
            ['texto' => 'Parámetros de morosidad', 'icono' => 'bi-hourglass-split', 'ruta' => 'parametros.morosidad', 'permiso' => 'parametros.morosidad'],
            ['texto' => 'Parámetros de campañas', 'icono' => 'bi-bullseye', 'ruta' => 'parametros.campanas', 'permiso' => 'parametros.campanas'],
            ['texto' => 'Modelo de contrato', 'icono' => 'bi-file-earmark-text', 'ruta' => 'parametros.contrato', 'permiso' => 'parametros.contrato'],
            ['texto' => 'Calendario operativo', 'icono' => 'bi-calendar3', 'ruta' => 'calendario.actualizar', 'permiso' => 'calendario.actualizar'],
            ['texto' => 'Documentación exigible', 'icono' => 'bi-folder-check', 'ruta' => 'documentacion.configurar', 'permiso' => 'documentacion.configurar'],
            ['texto' => 'Categorías del monotributo', 'icono' => 'bi-tags', 'ruta' => 'monotributo.actualizar', 'permiso' => 'monotributo.actualizar'],
        ]],
        ['titulo' => 'Administración', 'icono' => 'bi-gear', 'opciones' => [
            ['texto' => 'Usuarios', 'icono' => 'bi-person-badge', 'ruta' => 'usuarios.localizar', 'permiso' => 'usuarios.localizar'],
            ['texto' => 'Auditoría', 'icono' => 'bi-journal-text', 'ruta' => 'auditoria.consultar', 'permiso' => 'auditoria.consultar'],
            ['texto' => 'Copias de seguridad', 'icono' => 'bi-database-down', 'ruta' => 'copias.generar', 'permiso' => 'copias.generar'],
        ]],
    ];

    /**
     * Funciones del portal de autogestión (RF-18, matriz 3.6.2 para el Cliente). Igual que en el panel, una función
     * aparece como enlace cuando su ruta existe; mientras tanto se muestra como próxima.
     *
     * @var list<array{icono: string, titulo: string, texto: string, ruta: string}>
     */
    public array $portal = [
        ['icono' => 'bi-wallet2', 'titulo' => 'Estado de cuenta', 'texto' => 'Sus créditos, cuotas, deuda y saldo a favor.', 'ruta' => 'portal.estado-cuenta'],
        ['icono' => 'bi-credit-card', 'titulo' => 'Pagar una cuota', 'texto' => 'Pago electrónico de cuotas completas.', 'ruta' => 'portal.pagar'],
        ['icono' => 'bi-receipt', 'titulo' => 'Comprobantes', 'texto' => 'Descarga de los comprobantes de sus pagos.', 'ruta' => 'portal.comprobantes'],
        ['icono' => 'bi-file-earmark-text', 'titulo' => 'Mis solicitudes', 'texto' => 'Estado de sus solicitudes de crédito.', 'ruta' => 'portal.solicitudes'],
        ['icono' => 'bi-arrow-repeat', 'titulo' => 'Refinanciaciones y ofertas', 'texto' => 'Propuestas de refinanciación y ofertas recibidas.', 'ruta' => 'portal.propuestas'],
        ['icono' => 'bi-person-vcard', 'titulo' => 'Mi perfil', 'texto' => 'Sus datos registrados en la entidad.', 'ruta' => 'portal.perfil'],
        ['icono' => 'bi-shield-check', 'titulo' => 'Mis derechos sobre mis datos', 'texto' => 'Pida ver, corregir o suprimir sus datos personales.', 'ruta' => 'portal.derechos'],
    ];
}
