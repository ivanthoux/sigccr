<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Portal\PortalService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-49 Solicitar Pago Electrónico de Cuota. */
class Pagos extends BasePortal
{
    /** Pasos 1 a 4 (A1, A3, A7, A8). */
    public function index(): string
    {
        return view('portal/pagar', ['titulo' => 'Pagar una cuota'] + (new PortalService())->obligaciones($this->clienteId()));
    }

    /** Pasos 5 a 10 (A2 a A4, A8). */
    public function generar(): RedirectResponse
    {
        try {
            $r = (new PortalService())->generarOrden($this->clienteId(), (array) $this->request->getPost('cuotas'));
        } catch (ReglaNegocioException $e) {
            // A8: la cuota ya integra una orden vigente: se vuelve a presentar su medio de pago
            if (str_starts_with((string) $e->regla, 'orden:')) {
                return redirect()->to(site_url('portal/pagar/ordenes/' . substr((string) $e->regla, 6)))
                    ->with('error', 'Alguna de las cuotas ya integra esta orden, pendiente y vigente: puede pagarla con el mismo enlace sin generar otra.');
            }

            return $this->volverConErrores($e);
        }

        return redirect()->to(site_url("portal/pagar/ordenes/{$r['id']}"))
            ->with('exito', 'Orden generada. El pago se acreditará cuando la pasarela lo confirme: hasta entonces la cuota sigue pendiente.');
    }

    /** Paso 8 (A5, A6): la orden con su enlace de pago. */
    public function orden(int $id): string|RedirectResponse
    {
        try {
            $o = (new PortalService())->orden($this->clienteId(), $id);
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.pagar', $e);
        }

        return view('portal/orden', ['titulo' => 'Orden N.º ' . $o['numero'], 'o' => $o]);
    }
}
