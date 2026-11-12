<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Mantenimiento\MotorCopias;
use CodeIgniter\CLI\CLI;

/**
 * CU-61 A5 Copia automática diaria (RNF-13). El Temporizador la ejecuta a las 02:00 (docker/scheduler/crontab) en el
 * contenedor de procesos y después depura las copias con más de 30 días.
 *
 *   php spark copias:generar
 */
class GenerarCopia extends BaseProcesoCommand
{
    protected $name = MotorCopias::PROCESO_AUTOMATICA;
    protected $description = 'Genera la copia de seguridad automática diaria y depura las vencidas (CU-61, RNF-13).';
    protected $usage = 'copias:generar';

    protected function ejecutar(?string $fechaReferencia): void
    {
        $motor = new MotorCopias($this->db);
        $this->procesarConTransaccionesPropias('copia automática', static function () use ($motor): void {
            $r = $motor->copiaAutomatica();
            CLI::write($r['estado'] === 'Verificada' ? "Copia {$r['id']} generada y verificada." : "Copia {$r['id']} fallida: {$r['detalle']}", $r['estado'] === 'Verificada' ? 'green' : 'red');
        });
        $this->procesarConTransaccionesPropias('depuración', static function () use ($motor): void {
            CLI::write('Copias depuradas: ' . $motor->depurar() . '.');
        });
    }
}
