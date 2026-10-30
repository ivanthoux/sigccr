<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Clientes\ClienteService;
use App\Services\Solicitudes\ComparativaService;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-99 Simular Todas las Líneas de Crédito (D-10). */
class Comparativas extends BaseController
{
    /** Pasos 1 a 3: cliente y datos opcionales del pedido. */
    public function index(): string|RedirectResponse
    {
        $clienteId = (int) $this->request->getGet('cliente');
        $texto = trim((string) $this->request->getGet('texto'));
        if ($clienteId === 0) {
            return view('simulaciones/cliente', [
                'titulo' => 'Simular todas las líneas',
                'texto' => $texto,
                'clientes' => $texto === '' ? [] : array_values(array_filter((new ClienteService())->buscar(['texto' => $texto]), static fn ($c) => $c['estado'] === 'Activo')),
                'destino' => 'simulaciones.comparar',
            ]);
        }
        try {
            $cliente = (new SimulacionService())->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('simulaciones.comparar')->with('error', $e->getMessage());
        }

        return view('comparativas/nueva', ['titulo' => 'Simular todas las líneas', 'cliente' => $cliente]);
    }

    /** Pasos 4 a 10. */
    public function simular(): RedirectResponse
    {
        try {
            $id = (new ComparativaService())->simular(
                (int) $this->request->getPost('cliente_id'),
                (string) $this->request->getPost('importe'),
                (string) $this->request->getPost('cantidad_cuotas'),
                (string) $this->request->getPost('criterio'),
                (int) session('cuenta')['id'],
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/simulaciones/comparar/{$id}"))->with('exito', 'Comparación registrada. Es preliminar: no consulta la Central de Deudores ni genera solicitud ni deuda, y vale por 7 días corridos.');
    }

    /** Pasos 9 y 11: cuadro comparativo. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $c = (new ComparativaService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('simulaciones.comparar')->with('error', $e->getMessage());
        }

        return view('comparativas/ver', ['titulo' => 'Comparación de líneas para ' . $c['denominacion'], 'c' => $c]);
    }
}
