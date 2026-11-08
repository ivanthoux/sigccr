<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Caja\PagoElectronicoService;

/**
 * CU-90 Procesar Notificación de Pago Electrónico. El Temporizador lo ejecuta cada minuto (docker/scheduler/crontab):
 * toma las notificaciones válidas pendientes, consulta a la pasarela el estado real de la orden y confirma, rechaza o
 * deja en conciliación cada transacción una sola vez (RN-30, RN-55 a RN-57, RNF-08). La consulta se hace fuera de las
 * transacciones, por eso cada notificación maneja las suyas.
 *
 *   php spark pagos:notificaciones
 */
class ProcesarNotificacionesPago extends BaseProcesoCommand
{
    protected $name = 'pagos:notificaciones';
    protected $description = 'Procesa las notificaciones recibidas de la pasarela de pagos (CU-90).';
    protected $usage = 'pagos:notificaciones';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $s = new PagoElectronicoService($this->db);
        foreach ($s->notificacionesPendientes() as $n) {
            $id = (int) $n['id'];
            $this->procesarConTransaccionesPropias("notificación {$id}", static function () use ($s, $id): void {
                $s->procesarNotificacion($id);
            });
        }
    }
}
