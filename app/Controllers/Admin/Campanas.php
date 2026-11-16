<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Campanas\CampanaService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-50 Consultar Campaña Crediticia (Gerente y Analista de Crédito). Solo lectura. */
class Campanas extends BaseController
{
    /** Pasos 1 a 4 (A1, A5). */
    public function index(): string
    {
        $f = [
            'numero' => trim((string) $this->request->getGet('numero')), 'linea' => (string) $this->request->getGet('linea'),
            'estado' => (string) $this->request->getGet('estado'), 'desde' => (string) $this->request->getGet('desde'),
            'hasta' => (string) $this->request->getGet('hasta'), 'canal' => (string) $this->request->getGet('canal'),
        ];

        return view('campanas/index', [
            'titulo' => 'Campañas crediticias', 'f' => $f, 'campanas' => (new CampanaService())->buscar($f),
            'lineas' => db_connect()->table('lineas_credito')->select('id, nombre')->orderBy('nombre')->get()->getResultArray(),
        ]);
    }

    /** Pasos 5 y 6 (A2). */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $c = (new CampanaService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('campanas.consultar')->with('error', $e->getMessage());
        }

        return view('campanas/ver', ['titulo' => 'Campaña N.º ' . $c['numero'], 'c' => $c]);
    }

    /** A4: detalle de una oferta. */
    public function oferta(int $id): string|RedirectResponse
    {
        try {
            $o = (new CampanaService())->oferta($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('campanas.consultar')->with('error', $e->getMessage());
        }

        return view('campanas/oferta', ['titulo' => 'Oferta de la campaña N.º ' . $o['campana_numero'], 'o' => $o]);
    }
}
