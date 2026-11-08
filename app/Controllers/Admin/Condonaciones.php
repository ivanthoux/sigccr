<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Cartera\CondonacionService;
use App\Services\Clientes\ClienteService;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-44 Condonar Intereses Punitorios (Gerente). */
class Condonaciones extends BaseController
{
    /** CU-44 pasos 1 a 5 (A3). */
    public function index(): string|RedirectResponse
    {
        $clienteId = (int) $this->request->getGet('cliente');
        if ($clienteId === 0) {
            $texto = trim((string) $this->request->getGet('texto'));

            return view('simulaciones/cliente', [
                'titulo' => 'Condonar punitorios', 'texto' => $texto, 'destino' => 'punitorios.condonar',
                'clientes' => $texto === '' ? [] : (new ClienteService())->buscar(['texto' => $texto]),
            ]);
        }
        try {
            $cliente = (new SimulacionService())->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('punitorios.condonar')->with('error', $e->getMessage());
        }

        return view('condonaciones/nueva', ['titulo' => 'Condonar punitorios', 'cliente' => $cliente] + (new CondonacionService())->obligaciones($clienteId));
    }

    /** CU-44 pasos 6 a 10 (A1, A2). */
    public function condonar(): RedirectResponse
    {
        $clienteId = (int) $this->request->getPost('cliente_id');
        try {
            $r = (new CondonacionService())->condonar(
                (int) session('cuenta')['id'],
                $clienteId,
                (array) $this->request->getPost('cuotas'),
                (string) $this->request->getPost('importe'),
                (string) $this->request->getPost('fundamento'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url('admin/punitorios/condonar?cliente=' . $clienteId))->with('exito', 'Condonación registrada por ' . formato_importe($r['importe']) . " sobre {$r['cuotas']} cuota(s), con su IVA. El capital adeudado no se redujo."
            . ($r['propuestas'] > 0 ? " {$r['propuestas']} propuesta(s) de refinanciación vigente(s) quedaron Observadas (RN-36)." : ''));
    }
}
