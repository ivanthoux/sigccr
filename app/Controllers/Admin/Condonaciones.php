<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Cartera\CondonacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-44 Condonar Intereses Punitorios (Gerente). */
class Condonaciones extends BaseController
{
    /** CU-44 pasos 1 a 5 (A3). */
    public function index(): string|RedirectResponse
    {
        $cliente = $this->clienteSeleccionado('punitorios.condonar', 'Condonar punitorios');
        if (!is_array($cliente)) {
            return $cliente;
        }

        return view('condonaciones/nueva', ['titulo' => 'Condonar punitorios', 'cliente' => $cliente] + (new CondonacionService())->obligaciones((int) $cliente['id']));
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
                $this->campoPost('importe'),
                $this->campoPost('fundamento'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url('admin/punitorios/condonar?cliente=' . $clienteId))->with('exito', 'Condonación registrada por ' . formato_importe($r['importe']) . " sobre {$r['cuotas']} cuota(s), con su IVA. El capital adeudado no se redujo."
            . ($r['propuestas'] > 0 ? " {$r['propuestas']} propuesta(s) de refinanciación vigente(s) quedaron Observadas (RN-36)." : ''));
    }
}
