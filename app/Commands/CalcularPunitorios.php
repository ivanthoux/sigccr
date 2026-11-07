<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Cartera\PunitorioService;
use CodeIgniter\I18n\Time;

/**
 * CU-88 Calcular Intereses Punitorios. El Temporizador lo ejecuta a las 00:05 hora local (docker/scheduler/crontab)
 * y procesa el día anterior; si hay días sin procesar, los procesa en orden (A1). Cada cuota es un elemento con su
 * propia transacción (A4) y la clave única (cuota, fecha) evita duplicar tramos (A2, RN-53).
 *
 *   php spark cartera:punitorios
 */
class CalcularPunitorios extends BaseProcesoCommand
{
    protected $name = PunitorioService::PROCESO;
    protected $description = 'Registra los intereses punitorios del día de las cuotas vencidas y marca las cuotas vencidas (CU-88).';
    protected $usage = 'cartera:punitorios';

    protected function porFecha(): bool
    {
        return true;
    }

    /**
     * Días anteriores a hoy sin una ejecución finalizada. La primera vez procesa ayer: los días de atraso anteriores
     * de cada cuota se completan en el mismo cálculo.
     *
     * @return list<string>
     */
    protected function fechasPendientes(bool $incluirHoy = false): array
    {
        $fechas = parent::fechasPendientes(false);
        if ($fechas === [] && $this->db->table('ejecuciones_proceso')->where('proceso', $this->name)->countAllResults() === 0) {
            $fechas = [Time::now(config('Sigccr')->zonaNegocio)->subDays(1)->format('Y-m-d')];
        }

        return $fechas;
    }

    protected function ejecutar(?string $fechaReferencia): void
    {
        $fecha = (string) $fechaReferencia;
        $s = new PunitorioService($this->db);
        $vencidas = 0;
        $this->procesarElemento("cuotas vencidas al {$fecha}", static function () use ($s, $fecha, &$vencidas): void {
            $vencidas = $s->marcarVencidas($fecha);
        });
        $cuotas = $s->cuotasEnAtraso($fecha);
        $errores = 0;
        foreach ($cuotas as $id) {
            $antes = $this->erroresRegistrados();
            $this->procesarElemento("cuota {$id}", static function () use ($s, $id, $fecha): void {
                $s->devengar($id, $fecha);
            });
            $errores += $this->erroresRegistrados() - $antes;
        }
        $s->auditar($fecha, count($cuotas), $vencidas, $errores);
    }
}
