<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-21 Simular Crédito: cliente (CU-54), línea admitida, importe y cuotas, y plan preliminar. */
class Simulaciones extends BaseController
{
    private SimulacionService $servicio;

    public function __construct()
    {
        $this->servicio = new SimulacionService();
    }

    /** Pasos 1 a 5: búsqueda del cliente, líneas admitidas y condiciones de la línea elegida. */
    public function index(): string|RedirectResponse
    {
        $clienteId = $this->enteroGet('cliente');
        if ($clienteId === 0) {
            return $this->busquedaCliente('solicitudes.simular', 'Simular crédito', true);
        }
        try {
            $cliente = $this->servicio->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.simular')->with('error', $e->getMessage());
        }
        $lineas = $this->servicio->lineasParaCliente($clienteId);
        $lineaId = $this->enteroGet('linea');

        return view('simulaciones/nueva', [
            'titulo' => 'Simular crédito',
            'cliente' => $cliente,
            'perfil' => $this->servicio->perfil($cliente),
            'lineas' => $lineas,
            'linea' => array_values(array_filter($lineas, static fn ($l) => (int) $l['id'] === $lineaId && $l['motivo_no_admitida'] === null))[0] ?? null,
        ]);
    }

    /** Pasos 6 a 10. */
    public function simular(): RedirectResponse
    {
        try {
            $id = $this->servicio->simular(
                $this->enteroPost('cliente_id'),
                $this->enteroPost('linea_id'),
                $this->campoPost('importe'),
                $this->campoPost('cantidad_cuotas'),
                (int) session('cuenta')['id'],
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/simulaciones/{$id}"))->with('exito', 'Simulación registrada. Es preliminar: no genera solicitud ni deuda y vale por 7 días corridos.');
    }

    /** Paso 10: detalle del plan preliminar. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.simular')->with('error', $e->getMessage());
        }

        return view('simulaciones/ver', ['titulo' => 'Simulación de ' . $s['denominacion'], 's' => $s]);
    }
}
