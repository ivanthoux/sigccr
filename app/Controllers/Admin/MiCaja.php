<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Caja\CajaService;
use CodeIgniter\HTTP\RedirectResponse;

/** Caja del Cajero: CU-31 Registrar Apertura de Caja y CU-36 Registrar Cierre de Caja. */
class MiCaja extends BaseController
{
    /** CU-31 pasos 1 a 3 (A1, A2, A4) y estado de la jornada abierta. */
    public function index(): string
    {
        return view('cajas/mi_caja', ['titulo' => 'Mi caja'] + (new CajaService())->situacion((int) session('cuenta')['id']));
    }

    /** CU-31 pasos 4 a 7. */
    public function abrir(): RedirectResponse
    {
        try {
            (new CajaService())->abrir((int) session('cuenta')['id'], (string) $this->request->getPost('fondo_inicial'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('cajas.abrir')->with('exito', 'Caja abierta: ya puede registrar cobros y desembolsos en efectivo.');
    }

    /** CU-36 pasos 2 y 3 (A1, A5). */
    public function cierre(): string|RedirectResponse
    {
        $s = (new CajaService())->situacion((int) session('cuenta')['id']);
        if ($s['jornada'] === null) {
            return redirect()->route('cajas.abrir')->with('error', 'No tiene una caja abierta.');
        }

        return view('cajas/cierre', ['titulo' => 'Cerrar caja', 'j' => $s['jornada']]);
    }

    /** CU-36 pasos 5 a 10 (A2, A3). */
    public function cerrar(): RedirectResponse
    {
        try {
            $r = (new CajaService())->cerrar((int) session('cuenta')['id'], (int) $this->request->getPost('version_fila'), (string) $this->request->getPost('efectivo_declarado'), (string) $this->request->getPost('explicacion'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = 'Caja cerrada. Efectivo esperado ' . formato_importe($r['esperado']) . ', declarado ' . formato_importe($r['declarado'])
            . ($r['diferencia'] === '0.00' ? ', sin diferencias.' : ', diferencia ' . formato_importe($r['diferencia']) . ' con su explicación.') . ' Su sesión sigue activa (RN-59).';

        return redirect()->route('cajas.abrir')->with('exito', $mensaje);
    }
}
