<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Services\Solicitudes\EvaluacionService;
use App\Services\Solicitudes\ResolucionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-26 Resolver Solicitud de Crédito Convencional y CU-28 Cancelar Solicitud de Crédito. */
class Resoluciones extends BaseController
{
    /** CU-26 pasos 1 y 2: solicitudes pendientes de resolución. */
    public function index(): string
    {
        return view('resoluciones/index', ['titulo' => 'Solicitudes para resolver', 'solicitudes' => (new SolicitudService())->buscar(['estado' => 'PendienteResolucion'])]);
    }

    /** CU-26 pasos 3 y 4. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = (new SolicitudService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.resolver')->with('error', $e->getMessage());
        }
        if ($s['estado'] !== 'PendienteResolucion') {
            return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('error', 'La solicitud no está pendiente de resolución (' . mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado'])) . ').');
        }

        return view('resoluciones/resolver', [
            'titulo' => 'Resolver la solicitud N.º ' . $s['numero'],
            's' => $s,
            'a' => (new ResolucionService())->antecedentes($id),
            'evaluacion' => (new EvaluacionService())->deSolicitud($id)[0] ?? null,
        ]);
    }

    /** CU-26 pasos 5 a 9 (A1 a A8). */
    public function resolver(int $id): RedirectResponse
    {
        try {
            $r = (new ResolucionService())->resolver($id, $this->enteroPost('version_fila'), (array) $this->request->getPost(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/solicitudes/{$id}"))->with(in_array($r['estado'], ['AprobadaPendienteLiquidacion', 'Rechazada', 'DevueltaRevision'], true) ? 'exito' : 'aviso', $r['mensaje']);
    }

    /** CU-28 pasos 3 y 4 (A1, A2). */
    public function cancelacion(int $id): string|RedirectResponse
    {
        try {
            $s = (new SolicitudService())->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.localizar')->with('error', $e->getMessage());
        }
        if (EstadosSolicitud::esFinal((string) $s['estado'])) {
            return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('error', 'La solicitud ya finalizó: no se puede cancelar.');
        }

        return view('resoluciones/cancelar', ['titulo' => 'Cancelar la solicitud N.º ' . $s['numero'], 's' => $s]);
    }

    /** CU-28 pasos 5 a 7. */
    public function cancelar(int $id): RedirectResponse
    {
        try {
            (new ResolucionService())->cancelar($id, $this->enteroPost('version_fila'), $this->campoPost('motivo'), $this->request->getPost('confirma_reserva') !== null, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('exito', 'Solicitud cancelada. Se liberaron sus afectaciones de fondos y ningún proceso pendiente podrá resolverla ni liquidarla.');
    }
}
