<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Portal\PortalService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-46 Consultar Estado de Solicitudes. */
class Solicitudes extends BasePortal
{
    /** Pasos 1 y 2 (A1, A5). */
    public function index(): string
    {
        return view('portal/solicitudes', ['titulo' => 'Mis solicitudes', 'solicitudes' => (new PortalService())->solicitudes($this->clienteId())]);
    }

    /** Pasos 3 a 5 (A2, A3). */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = (new PortalService())->solicitud($this->clienteId(), $id);
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.solicitudes', $e);
        }

        return view('portal/solicitud', ['titulo' => 'Solicitud N.º ' . $s['numero'], 's' => $s]);
    }
}
