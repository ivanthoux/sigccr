<?php

declare(strict_types=1);

namespace App\Libraries\Alertas;

/**
 * Operación que resuelve una alerta según el objeto relacionado (CU-83 pasos 6 y 7, A3). Cada entrada indica la
 * pantalla del objeto, el permiso que exige y la ruta del panel que debe existir; las operaciones de iteraciones
 * posteriores se habilitan solas cuando su ruta se declara en Config\Routes.
 */
final class OperacionAlerta
{
    /** @var array<string, array{texto: string, ruta: string, permiso: string}> */
    private const OPERACIONES = [
        'clientes' => ['texto' => 'Ver el cliente', 'ruta' => 'admin/clientes/{id}', 'permiso' => 'clientes.consultar-perfil'],
        'lineas_credito' => ['texto' => 'Ver la línea de crédito', 'ruta' => 'admin/lineas/{id}', 'permiso' => 'lineas.consultar'],
        'usuarios_internos' => ['texto' => 'Ver el usuario', 'ruta' => 'admin/usuarios/{id}', 'permiso' => 'usuarios.localizar'],
        'solicitudes_credito' => ['texto' => 'Ver la solicitud', 'ruta' => 'admin/solicitudes/{id}', 'permiso' => 'solicitudes.consultar'],
        'propuestas_refinanciacion' => ['texto' => 'Ver la propuesta', 'ruta' => 'admin/refinanciaciones/{id}', 'permiso' => 'refinanciaciones.consultar'],
        'cobros' => ['texto' => 'Ver el cobro', 'ruta' => 'admin/cobros/{id}', 'permiso' => 'cobros.emitir-comprobante'],
        'pre_registros' => ['texto' => 'Ver el pre-registro', 'ruta' => 'admin/pre-registros/{id}', 'permiso' => 'clientes.registrar'],
        'campanas' => ['texto' => 'Ver la campaña', 'ruta' => 'admin/campanas/{id}', 'permiso' => 'campanas.consultar'],
        'proyecciones_fondos' => ['texto' => 'Configurar las campañas', 'ruta' => 'admin/parametros/campanas', 'permiso' => 'parametros.campanas'],
    ];

    /** Nombres para mostrar el objeto relacionado de una alerta. */
    public const OBJETOS = [
        'clientes' => 'Cliente',
        'lineas_credito' => 'Línea de crédito',
        'usuarios_internos' => 'Usuario',
        'solicitudes_credito' => 'Solicitud',
        'propuestas_refinanciacion' => 'Propuesta de refinanciación',
        'cobros' => 'Cobro',
        'pre_registros' => 'Pre-registro del portal',
        'campanas' => 'Campaña crediticia',
        'proyecciones_fondos' => 'Proyección de fondos',
        'comunicaciones' => 'Comunicación',
        'ejecuciones_proceso' => 'Proceso automático',
    ];

    /**
     * @param list<string> $permisos   permisos del rol del usuario
     * @param list<string> $rutasPanel rutas GET declaradas, con (:num) para los identificadores (admin/clientes/(:num))
     * @return array{texto: string, url: string}|null null si no hay operación, no está construida o el rol no la permite (A3)
     */
    public static function para(?string $objetoTipo, ?string $objetoId, array $permisos, array $rutasPanel): ?array
    {
        $op = self::OPERACIONES[(string) $objetoTipo] ?? null;
        if ($op === null || $objetoId === null || preg_match('/^\d+$/', $objetoId) !== 1) {
            return null;
        }
        if (!in_array($op['permiso'], $permisos, true) || !in_array(str_replace('{id}', '(:num)', $op['ruta']), $rutasPanel, true)) {
            return null;
        }

        return ['texto' => $op['texto'], 'url' => str_replace('{id}', $objetoId, $op['ruta'])];
    }
}
