<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Caja\CajaService;
use App\Services\Cartera\RevocacionService;
use App\Services\Clientes\ClienteService;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-102 Registrar Revocación del Préstamo (RN-79, D-34). */
class Revocaciones extends BaseController
{
    private RevocacionService $servicio;

    public function __construct()
    {
        $this->servicio = new RevocacionService();
    }

    /** Pasos 1 y 2: cliente (CU-54) y sus créditos con el plazo de revocación. */
    public function index(): string|RedirectResponse
    {
        $clienteId = (int) $this->request->getGet('cliente');
        if ($clienteId === 0) {
            $texto = trim((string) $this->request->getGet('texto'));

            return view('simulaciones/cliente', [
                'titulo' => 'Revocación del préstamo', 'texto' => $texto, 'destino' => 'creditos.revocar',
                'clientes' => $texto === '' ? [] : (new ClienteService())->buscar(['texto' => $texto]),
            ]);
        }
        try {
            $cliente = (new SimulacionService())->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('creditos.revocar')->with('error', $e->getMessage());
        }

        return view('revocaciones/creditos', ['titulo' => 'Revocación del préstamo', 'cliente' => $cliente, 'creditos' => $this->servicio->creditos($clienteId)]);
    }

    /** Pasos 3 y 5: plazo, importe y estado de la revocación. */
    public function credito(int $id): string|RedirectResponse
    {
        try {
            $c = $this->servicio->datos($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('creditos.revocar')->with('error', $e->getMessage());
        }

        return view('revocaciones/credito', [
            'titulo' => 'Revocación del crédito N.º ' . $c['numero'], 'c' => $c, 'hoy' => hoy_negocio(),
            'jornada' => (new CajaService())->jornadaOperable((int) session('cuenta')['id']),
        ]);
    }

    /** Pasos 4 y 5 (A1, A4, A5). */
    public function registrar(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->registrar($id, (string) $this->request->getPost('fecha_comunicacion'), (string) $this->request->getPost('medio_comunicacion'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/revocaciones/{$id}"))->with('exito', $r['confirmada']
            ? 'Revocación registrada y confirmada: lo pagado por el cliente cubre el importe a restituir; el crédito quedó revocado.'
            : 'Revocación registrada. El cliente debe transferir ' . formato_importe($r['importe']) . '; el crédito conserva su plan hasta confirmar la restitución.');
    }

    /** Pasos 6 a 8 (A2, A3). */
    public function confirmar(int $id): RedirectResponse
    {
        try {
            $this->servicio->confirmar($id, (string) $this->request->getPost('numero_operacion'), (string) $this->request->getPost('importe'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/revocaciones/{$id}"))->with('exito', 'Restitución confirmada: el crédito quedó revocado y sus cuotas, sin efecto. Puede imprimir la constancia.');
    }
}
