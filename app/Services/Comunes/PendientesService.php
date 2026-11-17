<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Services\BaseService;
use App\Services\Caja\CajaService;

/**
 * Trabajo pendiente que la pantalla principal del panel muestra a cada rol (CU-01 paso 6): solo las colas para
 * las que la cuenta tiene permiso y que tienen al menos un caso. Es una consulta: no modifica ningún estado.
 */
class PendientesService extends BaseService
{
    /**
     * Colas de trabajo: permiso que habilita a atenderla, tabla y estado que la forman, ruta de la pantalla que la
     * atiende (con su filtro, si es un listado general) y el texto en singular y en plural.
     *
     * @var list<array{permiso: string, tabla: string, estado: string, ruta: string, filtro: array<string, string>, singular: string, plural: string}>
     */
    private const COLAS = [
        ['permiso' => 'solicitudes.resolver', 'tabla' => 'solicitudes_credito', 'estado' => 'PendienteResolucion', 'ruta' => 'solicitudes.resolver', 'filtro' => [],
            'singular' => 'solicitud para resolver', 'plural' => 'solicitudes para resolver'],
        ['permiso' => 'creditos.liquidar', 'tabla' => 'solicitudes_credito', 'estado' => 'AprobadaPendienteLiquidacion', 'ruta' => 'creditos.liquidar', 'filtro' => [],
            'singular' => 'crédito aprobado para liquidar', 'plural' => 'créditos aprobados para liquidar'],
        ['permiso' => 'evaluaciones.registrar-contingencia', 'tabla' => 'solicitudes_credito', 'estado' => 'PendienteContingencia', 'ruta' => 'evaluaciones.contingencia', 'filtro' => [],
            'singular' => 'evaluación en contingencia', 'plural' => 'evaluaciones en contingencia'],
        ['permiso' => 'solicitudes.verificar-legajo', 'tabla' => 'solicitudes_credito', 'estado' => 'PendienteVerificacion', 'ruta' => 'solicitudes.localizar', 'filtro' => ['estado' => 'PendienteVerificacion'],
            'singular' => 'solicitud con el legajo por verificar', 'plural' => 'solicitudes con el legajo por verificar'],
        ['permiso' => 'solicitudes.registrar-correccion', 'tabla' => 'solicitudes_credito', 'estado' => 'DevueltaRevision', 'ruta' => 'solicitudes.localizar', 'filtro' => ['estado' => 'DevueltaRevision'],
            'singular' => 'solicitud devuelta para corregir', 'plural' => 'solicitudes devueltas para corregir'],
        ['permiso' => 'clientes.registrar', 'tabla' => 'pre_registros', 'estado' => 'PendienteValidacion', 'ruta' => 'pre-registros.consultar', 'filtro' => [],
            'singular' => 'pre-registro del portal por validar', 'plural' => 'pre-registros del portal por validar'],
        ['permiso' => 'refinanciaciones.resolver', 'tabla' => 'propuestas_refinanciacion', 'estado' => 'PendienteResolucion', 'ruta' => 'refinanciaciones.localizar', 'filtro' => ['estado' => 'PendienteResolucion'],
            'singular' => 'propuesta de refinanciación para resolver', 'plural' => 'propuestas de refinanciación para resolver'],
        ['permiso' => 'saldos-favor.ejecutar-devolucion', 'tabla' => 'autorizaciones_devolucion', 'estado' => 'PendienteEjecucion', 'ruta' => 'saldos-favor.ejecutar-devolucion', 'filtro' => [],
            'singular' => 'devolución de saldo autorizada para ejecutar', 'plural' => 'devoluciones de saldo autorizadas para ejecutar'],
    ];

    /**
     * Trabajo pendiente de la cuenta, en el orden de COLAS. La caja se informa primero porque sin ella abierta el
     * cajero no puede registrar cobros.
     *
     * @param list<string> $permisos
     *
     * @return list<array{texto: string, cantidad: int|null, ruta: string, filtro: array<string, string>}>
     */
    public function deCuenta(int $usuarioId, array $permisos): array
    {
        $pendientes = [];
        if (in_array('cajas.abrir', $permisos, true) && ($caja = $this->caja($usuarioId)) !== null) {
            $pendientes[] = $caja;
        }
        foreach (self::COLAS as $c) {
            if (!in_array($c['permiso'], $permisos, true)) {
                continue;
            }
            $cantidad = $this->db->table($c['tabla'])->where('estado', $c['estado'])->countAllResults();
            if ($cantidad > 0) {
                $pendientes[] = ['texto' => $cantidad === 1 ? $c['singular'] : $c['plural'], 'cantidad' => $cantidad, 'ruta' => $c['ruta'], 'filtro' => $c['filtro']];
            }
        }

        return $pendientes;
    }

    /**
     * Aviso de la caja del cajero: asignada pero sin abrir hoy, o con la jornada de un día anterior sin cerrar.
     *
     * @return array{texto: string, cantidad: null, ruta: string, filtro: array<string, string>}|null
     */
    private function caja(int $usuarioId): ?array
    {
        $s = (new CajaService($this->db))->situacion($usuarioId);
        $texto = match (true) {
            $s['jornada'] !== null && $s['jornada']['anterior'] => 'La jornada de caja de un día anterior sigue abierta: ciérrela antes de operar',
            $s['jornada'] === null && $s['asignacion'] !== null => 'Su caja todavía no está abierta hoy',
            default => null,
        };

        return $texto === null ? null : ['texto' => $texto, 'cantidad' => null, 'ruta' => 'cajas.abrir', 'filtro' => []];
    }
}
