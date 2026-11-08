<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Fondos\FondosService;
use CodeIgniter\CLI\CLI;

/**
 * CU-92 Proyectar Disponibilidad de Fondos. El Temporizador lo ejecuta todos los días a las 06:00 hora local
 * (docker/scheduler/crontab) y registra la proyección con sus supuestos (RN-39).
 *
 *   php spark fondos:proyectar
 */
class ProyectarFondos extends BaseProcesoCommand
{
    protected $name = 'fondos:proyectar';
    protected $description = 'Registra la disponibilidad de fondos proyectada para el período configurado (CU-92).';
    protected $usage = 'fondos:proyectar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $s = new FondosService($this->db);
        $this->procesarElemento('proyección de fondos', function () use ($s): void {
            $r = $s->proyectar($this->name);
            CLI::write("Proyectado a {$r['periodo']} días: {$r['proyectado']} (umbral {$r['umbral']}; campaña: {$r['campana']}).");
        });
    }
}
