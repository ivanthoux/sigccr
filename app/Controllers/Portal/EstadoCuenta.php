<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Portal\PortalService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-47 Consultar Estado de Cuenta. */
class EstadoCuenta extends BasePortal
{
    /** Pasos 1 y 2 (A1, A2). */
    public function index(): string
    {
        return view('portal/estado_cuenta', ['titulo' => 'Estado de cuenta'] + (new PortalService())->estadoCuenta($this->clienteId()));
    }

    /** Pasos 3 y 4 (A4, A6, A7). */
    public function credito(int $id): string|RedirectResponse
    {
        try {
            $p = (new PortalService())->plan($this->clienteId(), $id);
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.estado-cuenta', $e);
        }

        return view('portal/plan', ['titulo' => 'Crédito N.º ' . $p['credito']['numero']] + $p);
    }

    /** Pasos 5 y 6 (A3). */
    public function cuota(int $id, int $numero): string|RedirectResponse
    {
        try {
            $q = (new PortalService())->cuota($this->clienteId(), $id, $numero);
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.estado-cuenta', $e);
        }

        return view('portal/cuota', ['titulo' => 'Cuota ' . $numero . ' del crédito N.º ' . $q['credito']['numero']] + $q);
    }
}
