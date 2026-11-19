<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Solicitudes\EstructuracionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Alternativas de financiación de una solicitud: CU-64 Consultar Alternativas Automáticas (con la regeneración de
 * CU-87, A1) y CU-79 Confirmar Alternativa de Financiación.
 */
class Alternativas extends BaseController
{
    private EstructuracionService $servicio;

    public function __construct()
    {
        $this->servicio = new EstructuracionService();
    }

    /** CU-64 pasos 3 a 6 (A1 a A4). */
    public function index(int $solicitudId): string|RedirectResponse
    {
        try {
            $s = (new SolicitudService())->obtener($solicitudId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.localizar')->with('error', $e->getMessage());
        }
        $this->servicio->registrarConsulta($solicitudId);

        return view('alternativas/index', [
            'titulo' => 'Alternativas de la solicitud N.º ' . $s['numero'],
            's' => $s,
            'estructuracion' => $this->servicio->deSolicitud($solicitudId),
            'evaluacion' => $this->servicio->evaluacionVigente($solicitudId),
        ]);
    }

    /** CU-64 paso 8 y CU-79 pasos 4 y 5. */
    public function ver(int $solicitudId, int $alternativaId): string|RedirectResponse
    {
        try {
            $s = (new SolicitudService())->obtener($solicitudId);
            $a = $this->servicio->alternativa($solicitudId, $alternativaId);
        } catch (ReglaNegocioException $e) {
            return redirect()->to(site_url("admin/solicitudes/{$solicitudId}/alternativas"))->with('error', $e->getMessage());
        }

        return view('alternativas/ver', ['titulo' => 'Alternativa de financiación', 's' => $s, 'a' => $a, 'evaluacion' => $this->servicio->evaluacionVigente($solicitudId)]);
    }

    /** CU-79 pasos 6 a 10 (A1 a A6). */
    public function confirmar(int $solicitudId, int $alternativaId): RedirectResponse
    {
        try {
            $r = $this->servicio->confirmar(
                $solicitudId,
                $alternativaId,
                $this->enteroPost('version_fila'),
                (array) $this->request->getPost(['fecha_conformidad', 'medio_conformidad', 'observaciones']),
                (int) session('cuenta')['id'],
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $destino = $r['estado'] === 'PendienteSeleccionPlan' ? "admin/solicitudes/{$solicitudId}/alternativas" : "admin/solicitudes/{$solicitudId}";

        return redirect()->to(site_url($destino))->with($r['estado'] === 'AprobadaPendienteLiquidacion' ? 'exito' : 'aviso', $r['mensaje']);
    }

    /** CU-64 A1 y CU-79 A2: generar nuevas alternativas con la evaluación vigente (CU-87). */
    public function regenerar(int $solicitudId): RedirectResponse
    {
        try {
            $r = $this->servicio->regenerar($solicitudId, $this->enteroPost('version_fila'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return redirect()->to(site_url("admin/solicitudes/{$solicitudId}/alternativas"))->with('error', $e->getMessage());
        }
        if ($r['viables'] === 0) {
            return redirect()->to(site_url("admin/solicitudes/{$solicitudId}"))->with('aviso', 'Tampoco resultaron alternativas viables: la solicitud pasó a resolución del Gerente. ' . $r['motivo']);
        }

        return redirect()->to(site_url("admin/solicitudes/{$solicitudId}/alternativas"))->with('exito', "Se generaron {$r['viables']} alternativas viables, vigentes por 7 días corridos.");
    }
}
