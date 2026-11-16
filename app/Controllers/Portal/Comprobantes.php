<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Portal\PortalService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-48 Obtener Comprobante de Pago. */
class Comprobantes extends BasePortal
{
    /** Pasos 1 y 2 (A1, A6). */
    public function index(): string
    {
        return view('portal/comprobantes', ['titulo' => 'Comprobantes', 'cobros' => (new PortalService())->comprobantes($this->clienteId())]);
    }

    /** Pasos 3 a 7: consulta en pantalla (A2, A3). */
    public function ver(int $id): string|RedirectResponse
    {
        return $this->mostrar($id, false);
    }

    /** Pasos 5 a 7: descarga o reemisión, que abre la impresión para guardarlo en PDF o imprimirlo (A4). */
    public function descargar(int $id): string|RedirectResponse
    {
        return $this->mostrar($id, true);
    }

    private function mostrar(int $id, bool $reemision): string|RedirectResponse
    {
        try {
            $c = (new PortalService())->comprobante($this->clienteId(), $id, $reemision);
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.comprobantes', $e);
        }

        return view('portal/comprobante', ['titulo' => 'Comprobante N.º ' . $c['comprobante'], 'c' => $c, 'imprimir' => $reemision]);
    }
}
