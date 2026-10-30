<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Solicitudes\EvaluacionService;

/**
 * CU-86 Ejecutar Evaluación Crediticia Automática. El Temporizador lo ejecuta cada 5 minutos
 * (docker/scheduler/crontab); también se inicia al verificarse un legajo (CU-23). La exclusión mutua de
 * BaseProcesoCommand y el bloqueo de cada solicitud evitan evaluar dos veces la misma.
 *
 * Primero recupera los intentos interrumpidos (A7) y luego evalúa cada solicitud Pendiente de evaluación. Cada
 * evaluación maneja sus propias transacciones para no retener bloqueos mientras espera al servicio externo.
 *
 *   php spark evaluacion:ejecutar
 */
class EjecutarEvaluaciones extends BaseProcesoCommand
{
    protected $name = EvaluacionService::PROCESO;
    protected $description = 'Evalúa las solicitudes pendientes de evaluación crediticia (CU-86).';
    protected $usage = 'evaluacion:ejecutar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $servicio = new EvaluacionService($this->db);
        $servicio->recuperarInterrumpidas($this->name);
        foreach ($servicio->pendientes() as $id) {
            $this->procesarConTransaccionesPropias("solicitud {$id}", static function () use ($servicio, $id): void {
                $servicio->evaluar($id);
            });
        }
    }
}
