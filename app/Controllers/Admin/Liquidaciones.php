<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Solicitudes\LiquidacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-27 Liquidar Crédito Aprobado. */
class Liquidaciones extends BaseController
{
    private LiquidacionService $servicio;

    public function __construct()
    {
        $this->servicio = new LiquidacionService();
    }

    /** Pasos 1 y 2. */
    public function index(): string
    {
        $texto = trim($this->campoGet('texto'));

        return view('liquidaciones/index', ['titulo' => 'Liquidar créditos aprobados', 'texto' => $texto, 'solicitudes' => $this->servicio->pendientes($texto)]);
    }

    /** Pasos 3 a 6. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->datos($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('creditos.liquidar')->with('error', $e->getMessage());
        }
        if ($s['liquidacion'] !== null) {
            return redirect()->to(site_url("admin/liquidaciones/{$id}/constancia"));
        }
        if ($s['estado'] !== 'AprobadaPendienteLiquidacion') {
            return redirect()->route('creditos.liquidar')->with('error', 'La solicitud N.º ' . $s['numero'] . ' no está aprobada pendiente de liquidación.');
        }

        return view('liquidaciones/liquidar', ['titulo' => 'Liquidar la solicitud N.º ' . $s['numero'], 's' => $s]);
    }

    /** Pasos 7 a 9 (A1 a A7). */
    public function liquidar(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->liquidar($id, $this->enteroPost('version_fila'), (array) $this->request->getPost(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        if ($r['credito_id'] === null) {
            return redirect()->route('creditos.liquidar')->with('aviso', $r['mensaje']);
        }

        return redirect()->to(site_url("admin/liquidaciones/{$id}/constancia"))->with('exito', $r['mensaje']);
    }

    /** Paso 9: constancia de desembolso con el plan definitivo. */
    public function constancia(int $id): string|RedirectResponse
    {
        try {
            $l = $this->servicio->constancia($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('creditos.liquidar')->with('error', $e->getMessage());
        }

        return view('liquidaciones/constancia', ['titulo' => 'Constancia de desembolso', 'l' => $l]);
    }
}
