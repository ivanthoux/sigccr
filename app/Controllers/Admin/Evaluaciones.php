<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Solicitudes\EvaluacionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Evaluación crediticia: CU-24 Consultar Evaluación Crediticia y CU-25 Registrar Evaluación de Contingencia. La
 * evaluación automática (CU-86) la ejecuta el proceso evaluacion:ejecutar, o se inicia al verificarse un legajo.
 */
class Evaluaciones extends BaseController
{
    private EvaluacionService $servicio;

    public function __construct()
    {
        $this->servicio = new EvaluacionService();
    }

    /** CU-24 pasos 3 a 7 (A1 a A4). */
    public function ver(int $solicitudId): string|RedirectResponse
    {
        try {
            $s = (new SolicitudService())->obtener($solicitudId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.localizar')->with('error', $e->getMessage());
        }
        $this->servicio->registrarConsulta($solicitudId);

        return view('evaluaciones/ver', [
            'titulo' => 'Evaluación de la solicitud N.º ' . $s['numero'],
            's' => $s,
            'evaluaciones' => $this->servicio->deSolicitud($solicitudId),
        ]);
    }

    /** CU-25 pasos 1 y 2: solicitudes pendientes de evaluación de contingencia. */
    public function pendientes(): string
    {
        return view('evaluaciones/pendientes', [
            'titulo' => 'Evaluaciones de contingencia',
            'solicitudes' => (new SolicitudService())->buscar(['estado' => 'PendienteContingencia']),
        ]);
    }

    /** CU-25 pasos 3 y 4 (A1, A3). */
    public function contingencia(int $solicitudId): string|RedirectResponse
    {
        try {
            $s = (new SolicitudService())->obtener($solicitudId);
            if ($s['estado'] !== 'PendienteContingencia') {
                throw new ReglaNegocioException('La solicitud no está pendiente de evaluación de contingencia.');
            }
            $evaluaciones = $this->servicio->deSolicitud($solicitudId);
            $intento = EvaluacionService::intentoParaContingencia($evaluaciones);
        } catch (ReglaNegocioException $e) {
            return redirect()->to(site_url("admin/solicitudes/{$solicitudId}/evaluacion"))->with('error', $e->getMessage());
        }

        return view('evaluaciones/contingencia', [
            'titulo' => 'Evaluación de contingencia',
            's' => $s,
            'intento' => $intento,
            'internos' => $this->servicio->antecedentesDeSolicitud($solicitudId),
        ]);
    }

    /** CU-25 pasos 5 a 9 (A2). */
    public function registrarContingencia(int $solicitudId): RedirectResponse
    {
        $datos = (array) $this->request->getPost(['antecedentes', 'detalle_antecedentes', 'recomendacion', 'justificacion', 'deudas']);
        try {
            $this->servicio->registrarContingencia($solicitudId, $this->enteroPost('version_fila'), $datos, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/solicitudes/{$solicitudId}/evaluacion"))
            ->with('exito', 'Evaluación de contingencia registrada: la solicitud queda pendiente de resolución. La recomendación no aprueba la solicitud; resuelve el Gerente.');
    }
}
