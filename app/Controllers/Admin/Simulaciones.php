<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Clientes\ClienteService;
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
        $clienteId = (int) $this->request->getGet('cliente');
        $texto = trim((string) $this->request->getGet('texto'));
        if ($clienteId === 0) {
            return view('simulaciones/cliente', [
                'titulo' => 'Simular crédito',
                'texto' => $texto,
                'clientes' => $texto === '' ? [] : array_values(array_filter((new ClienteService())->buscar(['texto' => $texto]), static fn ($c) => $c['estado'] === 'Activo')),
                'destino' => 'solicitudes.simular',
            ]);
        }
        try {
            $cliente = $this->servicio->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.simular')->with('error', $e->getMessage());
        }
        $lineas = $this->servicio->lineasParaCliente($clienteId);
        $lineaId = (int) $this->request->getGet('linea');

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
                (int) $this->request->getPost('cliente_id'),
                (int) $this->request->getPost('linea_id'),
                (string) $this->request->getPost('importe'),
                (string) $this->request->getPost('cantidad_cuotas'),
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
