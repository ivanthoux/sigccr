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
            ['texto' => 'Simulador', 'ruta' => 'solicitudes.simular', 'permiso' => 'solicitudes.simular'],
            ['texto' => 'Líneas de crédito', 'ruta' => 'lineas.localizar', 'permiso' => 'lineas.localizar'],
        ]],
        ['titulo' => 'Caja y cobranzas', 'icono' => 'bi-safe', 'opciones' => [
            ['texto' => 'Mi caja', 'ruta' => 'cajas.abrir', 'permiso' => 'cajas.abrir'],
            ['texto' => 'Cobro en ventanilla', 'ruta' => 'cobros.registrar', 'permiso' => 'cobros.registrar'],
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
            ['texto' => 'Parámetros', 'ruta' => 'parametros.evaluacion', 'permiso' => 'parametros.evaluacion'],
        ]],
        ['titulo' => 'Administración', 'icono' => 'bi-gear', 'opciones' => [
            ['texto' => 'Usuarios', 'ruta' => 'usuarios.localizar', 'permiso' => 'usuarios.localizar'],
            ['texto' => 'Auditoría', 'ruta' => 'auditoria.consultar', 'permiso' => 'auditoria.consultar'],
            ['texto' => 'Copias de seguridad', 'ruta' => 'copias.generar', 'permiso' => 'copias.generar'],
        ]],
    ];
}
