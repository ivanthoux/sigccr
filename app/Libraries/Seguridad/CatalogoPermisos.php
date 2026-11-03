<?php

declare(strict_types=1);

namespace App\Libraries\Seguridad;

/**
 * Catálogo de permisos del panel y su asignación a los roles internos (RN-01, RN-06, RNF-01).
 *
 * Un permiso por caso de uso del panel; la asignación reproduce la matriz de participación por actor (3.6.2).
 * CU-01 a CU-03 no requieren permiso (cualquier cuenta habilitada) y los casos de uso exclusivos del Cliente o del
 * Temporizador no tienen permiso de panel: el portal se controla por el tipo de cuenta y la pertenencia (RNF-01).
 * Las rutas declaran el código: ['filter' => 'permiso:clientes.registrar'].
 */
final class CatalogoPermisos
{
    /** @var array<int, array{0: string, 1: string}> caso de uso => [código, nombre del caso de uso] */
    public const PERMISOS = [
        5 => ['usuarios.registrar', 'Registrar Usuario Interno'],
        6 => ['usuarios.modificar', 'Modificar Datos de Usuario'],
        7 => ['usuarios.restablecer-credenciales', 'Restablecer Credenciales de Usuario'],
        8 => ['usuarios.desactivar', 'Desactivar Usuario'],
        9 => ['usuarios.reactivar', 'Reactivar Usuario'],
        10 => ['usuarios.asignar-rol', 'Asignar Rol'],
        11 => ['clientes.registrar', 'Registrar Cliente'],
        12 => ['clientes.modificar', 'Modificar Datos del Cliente'],
        13 => ['clientes.desactivar', 'Desactivar Cliente'],
        14 => ['clientes.reactivar', 'Reactivar Cliente'],
        15 => ['clientes.consultar-perfil', 'Consultar Perfil e Historial Crediticio'],
        16 => ['lineas.registrar', 'Registrar Línea de Crédito'],
        17 => ['lineas.modificar', 'Modificar Línea de Crédito'],
        18 => ['lineas.suspender', 'Suspender Línea de Crédito'],
        19 => ['lineas.reactivar', 'Reactivar Línea de Crédito'],
        20 => ['lineas.localizar', 'Localizar Línea de Crédito'],
        21 => ['solicitudes.simular', 'Simular Crédito'],
        22 => ['solicitudes.registrar', 'Registrar Solicitud de Crédito'],
        23 => ['solicitudes.verificar-legajo', 'Verificar Legajo de Solicitud'],
        24 => ['evaluaciones.consultar', 'Consultar Evaluación Crediticia'],
        25 => ['evaluaciones.registrar-contingencia', 'Registrar Evaluación de Contingencia'],
        26 => ['solicitudes.resolver', 'Resolver Solicitud de Crédito Convencional'],
        27 => ['creditos.liquidar', 'Liquidar Crédito Aprobado'],
        28 => ['solicitudes.cancelar', 'Cancelar Solicitud de Crédito'],
        29 => ['solicitudes.localizar', 'Localizar Solicitud de Crédito'],
        30 => ['solicitudes.consultar', 'Consultar Solicitud de Crédito'],
        31 => ['cajas.abrir', 'Registrar Apertura de Caja'],
        32 => ['cajas.consultar-movimientos', 'Consultar Movimientos de Caja'],
        33 => ['cobros.registrar', 'Registrar Cobro en Ventanilla'],
        34 => ['pagos-electronicos.generar-orden', 'Generar Orden de Pago Electrónico'],
        35 => ['cobros.emitir-comprobante', 'Emitir Comprobante de Pago'],
        36 => ['cajas.cerrar', 'Registrar Cierre de Caja'],
        37 => ['cobros.anular', 'Anular Cobro'],
        38 => ['pagos-electronicos.consultar-estado', 'Consultar Estado de Pago Electrónico'],
        39 => ['refinanciaciones.simular', 'Simular Plan de Refinanciación'],
        40 => ['refinanciaciones.registrar-propuesta', 'Registrar Propuesta de Refinanciación'],
        41 => ['refinanciaciones.resolver', 'Resolver Propuesta de Refinanciación'],
        42 => ['refinanciaciones.localizar', 'Localizar Propuesta de Refinanciación'],
        43 => ['refinanciaciones.consultar', 'Consultar Propuesta de Refinanciación'],
        44 => ['punitorios.condonar', 'Condonar Intereses Punitorios'],
        50 => ['campanas.consultar', 'Consultar Campaña Crediticia'],
        51 => ['parametros.campanas', 'Configurar Parámetros de Campañas Crediticias'],
        53 => ['usuarios.localizar', 'Localizar Usuario Interno'],
        54 => ['clientes.localizar', 'Localizar Cliente'],
        55 => ['tablero.consultar', 'Consultar Tablero Gerencial'],
        56 => ['cajas.informe-diario', 'Consultar Informe de Caja Diaria'],
        57 => ['auditoria.consultar', 'Consultar Registro de Auditoría'],
        58 => ['parametros.evaluacion', 'Configurar Parámetros de Evaluación Crediticia'],
        59 => ['parametros.estructuracion', 'Configurar Parámetros de Estructuración Automática'],
        60 => ['parametros.morosidad', 'Configurar Parámetros de Morosidad'],
        61 => ['copias.generar', 'Generar Copia de Seguridad'],
        62 => ['copias.descargar', 'Descargar Copia de Seguridad'],
        63 => ['copias.restaurar', 'Restaurar Copia de Seguridad'],
        64 => ['alternativas.consultar', 'Consultar Alternativas Automáticas de Financiación'],
        65 => ['reportes.cartera', 'Generar Reporte de Cartera Crediticia'],
        66 => ['reportes.morosidad', 'Generar Reporte de Morosidad'],
        67 => ['reportes.cobranzas', 'Generar Reporte de Cobranzas y Recupero'],
        68 => ['reportes.solicitudes', 'Generar Reporte de Solicitudes y Otorgamientos'],
        69 => ['reportes.refinanciaciones', 'Generar Reporte de Refinanciaciones'],
        70 => ['reportes.proyeccion-fondos', 'Generar Reporte de Proyección de Ingresos y Disponibilidad de Fondos'],
        71 => ['reportes.campanas', 'Generar Reporte de Resultados de Campañas Crediticias'],
        72 => ['cajas.asignar-cajero', 'Asignar Caja a Cajero'],
        73 => ['lineas.consultar', 'Consultar Línea de Crédito'],
        74 => ['refinanciaciones.aplicar', 'Registrar Aceptación y Aplicar Refinanciación'],
        76 => ['saldos-favor.aplicar', 'Aplicar Saldo a Favor a Obligaciones'],
        77 => ['saldos-favor.autorizar-devolucion', 'Autorizar Devolución de Saldo a Favor'],
        78 => ['saldos-favor.ejecutar-devolucion', 'Ejecutar Devolución de Saldo a Favor'],
        79 => ['alternativas.confirmar', 'Confirmar Alternativa de Financiación'],
        80 => ['cajas.registrar', 'Registrar Caja'],
        81 => ['cajas.cambiar-estado', 'Cambiar Estado de Caja'],
        82 => ['calendario.actualizar', 'Actualizar Calendario Operativo'],
        83 => ['alertas.consultar', 'Consultar Alertas'],
        84 => ['solicitudes.registrar-correccion', 'Registrar Corrección de Solicitud'],
        85 => ['fondos.registrar-movimiento', 'Registrar Movimiento de Fondos'],
        96 => ['refinanciaciones.cancelar-propuesta', 'Cancelar Propuesta de Refinanciación'],
        97 => ['creditos.cancelar-anticipadamente', 'Registrar Cancelación Anticipada de Crédito'],
        99 => ['solicitudes.simular-comparativa', 'Simular Todas las Líneas de Crédito'],
        // D-25 y D-26 (presentación del 02/11/2026)
        100 => ['documentacion.configurar', 'Configurar Catálogo de Documentación'],
        101 => ['monotributo.actualizar', 'Actualizar Categorías del Monotributo'],
    ];

    /**
     * Matriz de participación por actor (3.6.2), sin CU-01 a CU-03. Rangos [desde, hasta] o casos sueltos.
     *
     * @var array<string, list<int|array{0: int, 1: int}>>
     */
    public const MATRIZ = [
        'Administrador' => [[5, 10], 53, 54, 57, [61, 63], 83],
        'Gerente' => [[13, 20], 24, 26, [28, 30], 32, 37, [41, 44], 50, 51, [54, 60], [65, 73], 77, [80, 83], 85, 96, 100, 101],
        'AnalistaCredito' => [[11, 15], [20, 25], [28, 30], 39, 40, 42, 43, 50, 54, 64, 73, 74, 79, 83, 84, 96, 99],
        'Cajero' => [27, 29, [31, 36], 38, 42, 43, 54, 56, 74, 76, 78, 83, 97],
    ];

    /** @return list<int> casos de uso del rol, ordenados */
    public static function casosDeUso(string $rol): array
    {
        $casos = [];
        foreach (self::MATRIZ[$rol] ?? [] as $item) {
            $casos = [...$casos, ...(is_array($item) ? range($item[0], $item[1]) : [$item])];
        }
        $casos = array_values(array_unique($casos));
        sort($casos);

        return $casos;
    }

    /** @return list<string> códigos de permiso del rol */
    public static function permisosDelRol(string $rol): array
    {
        return array_map(static fn (int $cu) => self::PERMISOS[$cu][0], self::casosDeUso($rol));
    }

    /** Descripción guardada en la tabla permisos: "CU-11 Registrar Cliente". */
    public static function descripcion(int $cu): string
    {
        return sprintf('CU-%02d %s', $cu, self::PERMISOS[$cu][1]);
    }
}
