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
     * @var list<array{titulo: string, icono: string, opciones: list<array{texto: string, ruta: string, permiso: ?string}>}>
     */
    public array $secciones = [
        ['titulo' => 'Crédito', 'icono' => 'bi-cash-coin', 'opciones' => [
            ['texto' => 'Clientes', 'ruta' => 'clientes.localizar', 'permiso' => 'clientes.localizar'],
            ['texto' => 'Solicitudes', 'ruta' => 'solicitudes.localizar', 'permiso' => 'solicitudes.localizar'],
            ['texto' => 'Contingencias', 'ruta' => 'evaluaciones.contingencia', 'permiso' => 'evaluaciones.registrar-contingencia'],
            ['texto' => 'Resolver solicitudes', 'ruta' => 'solicitudes.resolver', 'permiso' => 'solicitudes.resolver'],
            ['texto' => 'Simulador', 'ruta' => 'solicitudes.simular', 'permiso' => 'solicitudes.simular'],
            ['texto' => 'Comparar líneas', 'ruta' => 'simulaciones.comparar', 'permiso' => 'solicitudes.simular-comparativa'],
            ['texto' => 'Líneas de crédito', 'ruta' => 'lineas.localizar', 'permiso' => 'lineas.localizar'],
        ]],
        ['titulo' => 'Caja y cobranzas', 'icono' => 'bi-safe', 'opciones' => [
            ['texto' => 'Mi caja', 'ruta' => 'cajas.abrir', 'permiso' => 'cajas.abrir'],
            ['texto' => 'Cobro en ventanilla', 'ruta' => 'cobros.registrar', 'permiso' => 'cobros.registrar'],
            ['texto' => 'Comprobantes de pago', 'ruta' => 'cobros.emitir-comprobante', 'permiso' => 'cobros.emitir-comprobante'],
            ['texto' => 'Anular cobros', 'ruta' => 'cobros.anular', 'permiso' => 'cobros.anular'],
            ['texto' => 'Movimientos de caja', 'ruta' => 'cajas.consultar-movimientos', 'permiso' => 'cajas.consultar-movimientos'],
            ['texto' => 'Liquidar créditos', 'ruta' => 'creditos.liquidar', 'permiso' => 'creditos.liquidar'],
            ['texto' => 'Informe de caja diaria', 'ruta' => 'cajas.informe-diario', 'permiso' => 'cajas.informe-diario'],
            ['texto' => 'Cajas', 'ruta' => 'cajas.registrar', 'permiso' => 'cajas.registrar'],
        ]],
        ['titulo' => 'Cartera', 'icono' => 'bi-briefcase', 'opciones' => [
            ['texto' => 'Refinanciaciones', 'ruta' => 'refinanciaciones.localizar', 'permiso' => 'refinanciaciones.localizar'],
            ['texto' => 'Campañas', 'ruta' => 'campanas.consultar', 'permiso' => 'campanas.consultar'],
        ]],
        ['titulo' => 'Gestión', 'icono' => 'bi-graph-up', 'opciones' => [
            ['texto' => 'Tablero gerencial', 'ruta' => 'tablero.consultar', 'permiso' => 'tablero.consultar'],
            ['texto' => 'Reportes', 'ruta' => 'reportes.cartera', 'permiso' => 'reportes.cartera'],
            ['texto' => 'Fondos', 'ruta' => 'fondos.movimientos', 'permiso' => 'fondos.registrar-movimiento'],
            ['texto' => 'Parámetros de evaluación', 'ruta' => 'parametros.evaluacion', 'permiso' => 'parametros.evaluacion'],
            ['texto' => 'Parámetros de estructuración', 'ruta' => 'parametros.estructuracion', 'permiso' => 'parametros.estructuracion'],
            ['texto' => 'Calendario operativo', 'ruta' => 'calendario.actualizar', 'permiso' => 'calendario.actualizar'],
            ['texto' => 'Documentación exigible', 'ruta' => 'documentacion.configurar', 'permiso' => 'documentacion.configurar'],
            ['texto' => 'Categorías del monotributo', 'ruta' => 'monotributo.actualizar', 'permiso' => 'monotributo.actualizar'],
        ]],
        ['titulo' => 'Administración', 'icono' => 'bi-gear', 'opciones' => [
            ['texto' => 'Usuarios', 'ruta' => 'usuarios.localizar', 'permiso' => 'usuarios.localizar'],
            ['texto' => 'Auditoría', 'ruta' => 'auditoria.consultar', 'permiso' => 'auditoria.consultar'],
            ['texto' => 'Copias de seguridad', 'ruta' => 'copias.generar', 'permiso' => 'copias.generar'],
        ]],
    ];

    /**
     * Funciones del portal de autogestión (RF-18, matriz 3.6.2 para el Cliente). Igual que en el panel, una función
     * aparece como enlace cuando su ruta existe; mientras tanto se muestra como próxima.
     *
     * @var list<array{icono: string, titulo: string, texto: string, ruta: string}>
     */
    public array $portal = [
        ['icono' => 'bi-wallet2', 'titulo' => 'Estado de cuenta', 'texto' => 'Sus créditos, cuotas, deuda y saldo a favor (CU-47).', 'ruta' => 'portal.estado-cuenta'],
        ['icono' => 'bi-credit-card', 'titulo' => 'Pagar una cuota', 'texto' => 'Pago electrónico de cuotas completas (CU-49).', 'ruta' => 'portal.pagar'],
        ['icono' => 'bi-receipt', 'titulo' => 'Comprobantes', 'texto' => 'Descarga de los comprobantes de sus pagos (CU-48).', 'ruta' => 'portal.comprobantes'],
        ['icono' => 'bi-file-earmark-text', 'titulo' => 'Mis solicitudes', 'texto' => 'Estado de sus solicitudes de crédito (CU-46).', 'ruta' => 'portal.solicitudes'],
        ['icono' => 'bi-arrow-repeat', 'titulo' => 'Refinanciaciones y ofertas', 'texto' => 'Propuestas de refinanciación y ofertas recibidas (CU-43, CU-52).', 'ruta' => 'portal.propuestas'],
        ['icono' => 'bi-person-vcard', 'titulo' => 'Mi perfil', 'texto' => 'Sus datos registrados en la entidad (CU-45).', 'ruta' => 'portal.perfil'],
    ];
}
