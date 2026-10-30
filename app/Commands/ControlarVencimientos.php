<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Solicitudes\VencimientoService;

/**
 * CU-89 Controlar Vencimientos. El Temporizador lo ejecuta cada 15 minutos (docker/scheduler/crontab). En la
 * iteración 2 vence simulaciones, alternativas, aprobaciones, evaluaciones, solicitudes inactivas y autorizaciones de
 * acceso; las propuestas de refinanciación, las órdenes de pago y las devoluciones se incorporan en la iteración 3.
 *
 *   php spark vencimientos:controlar
 */
class ControlarVencimientos extends BaseProcesoCommand
{
    protected $name = VencimientoService::PROCESO;
    protected $description = 'Aplica los vencimientos de simulaciones, alternativas, evaluaciones, aprobaciones y solicitudes inactivas (CU-89).';
    protected $usage = 'vencimientos:controlar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $s = new VencimientoService($this->db);
        $this->procesarElemento('simulaciones y alternativas', static function () use ($s): void {
            $s->vencerSimulacionesYAlternativas();
        });
        $this->procesarElemento('autorizaciones de acceso', static function () use ($s): void {
            $s->vencerAutorizaciones();
        });
        // La aprobación prevalece si coincide con el vencimiento de la evaluación (RN-49)
        foreach ($s->aprobacionesVencidas() as $id) {
            $this->procesarElemento("aprobación de la solicitud {$id}", static function () use ($s, $id): void {
                $s->vencerAprobacion($id);
            });
        }
        foreach ($s->evaluacionesVencidas() as $id) {
            $this->procesarElemento("evaluación {$id}", static function () use ($s, $id): void {
                $s->vencerEvaluacion($id);
            });
        }
        foreach ($s->solicitudesInactivas() as $id) {
            $this->procesarElemento("solicitud inactiva {$id}", static function () use ($s, $id): void {
                $s->vencerPorInactividad($id);
            });
        }
    }
}
