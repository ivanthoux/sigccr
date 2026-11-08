<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Caja\DevolucionService;
use App\Services\Cartera\RefinanciacionService;
use App\Services\Solicitudes\VencimientoService;

/**
 * CU-89 Controlar Vencimientos. El Temporizador lo ejecuta cada 15 minutos (docker/scheduler/crontab). En la
 * iteración 2 vence simulaciones, alternativas, aprobaciones, evaluaciones, solicitudes inactivas y autorizaciones de
 * acceso. En la iteración 3 se agregan las propuestas de refinanciación (RN-36) y las autorizaciones de devolución sin
 * ejecución iniciada (RN-73); las órdenes de pago las vence CU-91.
 *
 *   php spark vencimientos:controlar
 */
class ControlarVencimientos extends BaseProcesoCommand
{
    protected $name = VencimientoService::PROCESO;
    protected $description = 'Aplica los vencimientos de simulaciones, alternativas, evaluaciones, aprobaciones, solicitudes inactivas, propuestas de refinanciación y autorizaciones de devolución (CU-89).';
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
        $refinanciaciones = new RefinanciacionService($this->db);
        foreach ($refinanciaciones->propuestasVencidas() as $id) {
            $this->procesarElemento("propuesta de refinanciación {$id}", static function () use ($refinanciaciones, $id): void {
                $refinanciaciones->vencerPropuesta($id);
            });
        }
        $devoluciones = new DevolucionService($this->db);
        foreach ($devoluciones->autorizacionesVencidas() as $id) {
            $this->procesarElemento("autorización de devolución {$id}", static function () use ($devoluciones, $id): void {
                $devoluciones->vencerAutorizacion($id);
            });
        }
        foreach ($s->solicitudesInactivas() as $id) {
            $this->procesarElemento("solicitud inactiva {$id}", static function () use ($s, $id): void {
                $s->vencerPorInactividad($id);
            });
        }
    }
}
