<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Caja\PagoElectronicoService;

/**
 * CU-91 Conciliar Órdenes de Pago Pendientes. El Temporizador lo ejecuta cada 30 minutos (docker/scheduler/crontab):
 * consulta a la pasarela las órdenes pendientes de más de 10 minutos, las que están en conciliación y las vencidas el
 * día anterior, y aplica el mismo tratamiento que CU-90; las órdenes vencidas sin pago pasan a Vencida y las que
 * superan 48 horas en conciliación generan una alerta para el Gerente (A2).
 *
 *   php spark pagos:conciliar
 */
class ConciliarPagos extends BaseProcesoCommand
{
    protected $name = 'pagos:conciliar';
    protected $description = 'Concilia con la pasarela las órdenes de pago pendientes (CU-91).';
    protected $usage = 'pagos:conciliar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $s = new PagoElectronicoService($this->db);
        foreach ($s->ordenesAConciliar() as $id) {
            $this->procesarConTransaccionesPropias("orden {$id}", static function () use ($s, $id): void {
                $s->procesarOrden($id);
            });
        }
    }
}
