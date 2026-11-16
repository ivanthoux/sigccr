<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Copias\EstadoRestauracion;
use App\Services\Mantenimiento\MotorCopias;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * CU-61 y CU-63: ejecuta las copias manuales y la restauración pedidas desde el panel (D-45). El Temporizador lo corre
 * cada minuto en el contenedor de procesos. No extiende BaseProcesoCommand porque la restauración
 * reemplaza la base donde esa clase registra sus ejecuciones; el resultado queda en la auditoría, en el registro técnico y
 * en writable/copias.
 *
 *   php spark copias:procesar
 */
class ProcesarCopias extends BaseCommand
{
    protected $group = 'SIG-CCR';
    protected $name = MotorCopias::PROCESO_SOLICITUDES;
    protected $description = 'Genera las copias solicitadas y ejecuta la restauración pedida (CU-61, CU-63).';
    protected $usage = 'copias:procesar';

    public function run(array $params): void
    {
        $motor = new MotorCopias();
        try {
            if (EstadoRestauracion::predeterminado()->activa()) {
                $r = $motor->restaurarPendiente();
                if ($r !== null) {
                    CLI::write($r['mensaje'], $r['exitosa'] ? 'green' : 'red');
                }

                return;
            }
            foreach ($motor->procesarSolicitadas() as $r) {
                CLI::write($r['estado'] === 'Verificada' ? "Copia {$r['id']} generada y verificada." : "Copia {$r['id']} fallida: {$r['detalle']}", $r['estado'] === 'Verificada' ? 'green' : 'red');
            }
        } catch (Throwable $e) {
            CLI::error($this->name . ': ' . $e->getMessage());
            log_message('error', $this->name . ': ' . $e->getMessage());
        }
    }
}
