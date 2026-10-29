<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Comunes\ComunicacionService;

/**
 * CU-95 Procesar Comunicaciones Pendientes. El Temporizador lo ejecuta cada minuto (docker/scheduler/crontab):
 * envía las comunicaciones pendientes y los reintentos cuyo horario se cumplió, una por transacción (RNF-10 d).
 * La exclusión mutua de BaseProcesoCommand evita que dos ejecuciones envíen la misma comunicación.
 *
 *   php spark comunicaciones:enviar
 */
class EnviarComunicaciones extends BaseProcesoCommand
{
    protected $name = 'comunicaciones:enviar';
    protected $description = 'Envía las comunicaciones pendientes y los reintentos programados (CU-95).';
    protected $usage = 'comunicaciones:enviar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $servicio = new ComunicacionService();
        foreach ($servicio->pendientes() as $id) {
            $this->procesarElemento("comunicación {$id}", static function () use ($servicio, $id): void {
                $servicio->enviar($id);
            });
        }
    }
}
