<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Campanas\CampanaService;
use CodeIgniter\CLI\CLI;

/**
 * CU-94 Cerrar Campaña Crediticia. El Temporizador lo ejecuta cada 15 minutos (docker/scheduler/crontab): cierra la
 * campaña activa que alcanzó su fecha de cierre o su capital asignado, deja vencidas las ofertas no respondidas y
 * cancela sus comunicaciones pendientes (RN-43). El cierre por disponibilidad insuficiente lo ejecuta CU-92.
 *
 *   php spark campanas:cerrar
 */
class CerrarCampanas extends BaseProcesoCommand
{
    protected $name = CampanaService::PROCESO_CIERRE;
    protected $description = 'Cierra las campañas crediticias vencidas o con el cupo alcanzado (CU-94).';
    protected $usage = 'campanas:cerrar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $s = new CampanaService($this->db);
        foreach ($s->paraCerrar() as $c) {
            $this->procesarConTransaccionesPropias("campaña {$c['id']}", function () use ($s, $c): void {
                if ($s->cerrar($c['id'], $c['causa'], $this->name)) {
                    CLI::write("Campaña {$c['id']} cerrada ({$c['causa']}).");
                }
            });
        }
    }
}
