<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Cartera\RegimenService;
use CodeIgniter\CLI\CLI;

/**
 * CU-105 Generar Régimen Informativo de Deudores. El Temporizador lo ejecuta todos los días a las 7
 * (docker/scheduler/crontab): a partir del primer día hábil del mes genera el mes anterior, una sola vez (A1). Si la
 * generación falla, el período queda sin archivo y se reintenta en la siguiente ejecución (A3).
 *
 *   php spark regimen:generar
 */
class GenerarRegimen extends BaseProcesoCommand
{
    protected $name = RegimenService::PROCESO;
    protected $description = 'Genera el régimen informativo de deudores del mes cerrado (CU-105).';
    protected $usage = 'regimen:generar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $s = new RegimenService($this->db);
        $periodo = $s->periodoPendiente();
        if ($periodo === null) {
            CLI::write('No hay un período a generar.');

            return;
        }
        $this->procesarConTransaccionesPropias("período {$periodo}", function () use ($s, $periodo): void {
            $r = $s->generar($periodo, null, null, $this->name);
            CLI::write("Régimen {$periodo} generado: {$r['deudores']} deudores.");
        });
    }
}
