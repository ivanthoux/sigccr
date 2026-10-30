<?php

declare(strict_types=1);

namespace App\Adapters\InformacionCrediticia;

use App\Adapters\ServicioNoDisponibleException;

/**
 * Simulador determinístico para desarrollo y pruebas (RNF-05). El último dígito del CUIT/CUIL define la respuesta:
 *   0 sin deudas · 1 situación 1 · 2 situación 2 · 3 situación 3 · 5 situación 5 vigente · 9 servicio no disponible.
 */
class SimuladorInformacionCrediticia implements InformacionCrediticiaInterface
{
    private string $codigo = '';

    public function consultar(string $cuitCuil): array
    {
        $this->codigo = substr($cuitCuil, -1) === '9' ? 'Timeout' : '200';
        $digito = (int) substr($cuitCuil, -1);
        if ($digito === 9) {
            throw new ServicioNoDisponibleException('Simulador: tiempo de espera agotado.');
        }
        if ($digito === 0) {
            return [];
        }
        $situacion = in_array($digito, [1, 2, 3, 5], true) ? $digito : 1;
        $periodo = date('Ym', strtotime('first day of last month'));

        return [['entidad' => 'BANCO SIMULADO S.A.', 'periodo' => $periodo, 'situacion' => $situacion, 'monto' => '250000.00']];
    }

    public function intercambios(): array
    {
        return $this->codigo === '' ? [] : [['operacion' => 'Simulador', 'codigo' => $this->codigo, 'duracion_ms' => 0, 'intento' => 1]];
    }
}
