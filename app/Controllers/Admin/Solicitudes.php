<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Validacion\Campos;
use App\Services\Clientes\LegajoService;
use App\Services\Solicitudes\EvaluacionService;
use App\Services\Solicitudes\SimulacionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Solicitudes de crédito: CU-22 Registrar, CU-23 Verificar Legajo y CU-84 Registrar Corrección, con la localización
 * (CU-29) y la consulta (CU-30) que necesitan.
 */
class Solicitudes extends BaseController
{
    private SolicitudService $servicio;

    public function __construct()
    {
        $this->servicio = new SolicitudService();
    }

    /** CU-29: listado con filtros; sin filtros, las más recientes. */
    public function index(): string
    {
        $criterios = $this->filtrosBusqueda(['numero', 'texto', 'estado', 'cliente', 'documento', 'linea', 'desde', 'hasta']);
        // A2: rango de fechas válido
        $errores = [];
        $filtro = $criterios;
        foreach (['desde', 'hasta'] as $k) {
            if (trim($criterios[$k] ?? '') !== '') {
                $filtro[$k] = Campos::fecha($criterios[$k]) ?? '';
                if ($filtro[$k] === '') {
                    $errores[$k] = 'Ingrese una fecha válida (dd/mm/aaaa).';
                }
            }
        }
        if ($errores === [] && ($filtro['desde'] ?? '') !== '' && ($filtro['hasta'] ?? '') !== '' && $filtro['desde'] > $filtro['hasta']) {
            $errores['hasta'] = 'La fecha final no puede ser anterior a la inicial.';
        }

        return view('solicitudes/index', [
            'titulo' => 'Solicitudes de crédito',
            'criterios' => $criterios,
            'errores' => $errores,
            'lineas' => $this->servicio->lineas(),
            'solicitudes' => $errores === [] ? $this->servicio->buscar($filtro) : [],
        ]);
    }

    /** CU-22 pasos 1 a 4: cliente y sus simulaciones vigentes. */
    public function nueva(): string|RedirectResponse
    {
        $clienteId = (int) $this->request->getGet('cliente');
        if ($clienteId === 0) {
            return $this->busquedaCliente('solicitudes.registrar', 'Registrar solicitud', true);
        }
        $sim = new SimulacionService();
        try {
            $cliente = $sim->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.registrar')->with('error', $e->getMessage());
        }

        return view('solicitudes/nueva', ['titulo' => 'Registrar solicitud', 'cliente' => $cliente, 'simulaciones' => $sim->vigentesDelCliente($clienteId)]);
    }

    /** CU-22 pasos 5 a 8: datos de la solicitud y documentación que exige la línea. */
    public function confirmar(int $simulacionId): string|RedirectResponse
    {
        $sim = new SimulacionService();
        try {
            $s = $sim->obtener($simulacionId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.registrar')->with('error', $e->getMessage());
        }
        if ((int) $s['vigente'] !== 1) {
            return redirect()->to(site_url('admin/solicitudes/nueva?cliente=' . $s['cliente_id']))->with('error', 'La simulación superó su vigencia de 7 días corridos: genere una nueva (RN-48).');
        }
        $cliente = $sim->cliente((int) $s['cliente_id']);
        $legajo = new LegajoService();

        return view('solicitudes/confirmar', [
            'titulo' => 'Registrar solicitud',
            's' => $s,
            'cliente' => $cliente,
            'exigidos' => $this->servicio->exigidos((int) $s['version_condiciones_id'], (string) $cliente['categoria_ingresos']),
            'presentes' => array_keys($legajo->vigentesPorTipo((int) $s['cliente_id'])),
            'tipos' => $legajo->tiposDocumentacion(),
        ]);
    }

    /** CU-22 pasos 9 y 10. */
    public function registrar(): RedirectResponse
    {
        try {
            $r = $this->servicio->registrar((int) $this->request->getPost('simulacion_id'), $this->archivosLegajo(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/solicitudes/{$r['id']}"))->with('exito', "Solicitud N.º {$r['numero']} registrada: queda pendiente de verificación del legajo. No implica otorgamiento ni desembolso.");
    }

    /** CU-30: detalle de la solicitud. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.localizar')->with('error', $e->getMessage());
        }
        $this->servicio->registrarConsulta($id);

        return view('solicitudes/ver', [
            'titulo' => 'Solicitud N.º ' . $s['numero'],
            's' => $s,
            'tipos' => (new LegajoService())->tiposDocumentacion(),
            'evaluacion' => (new EvaluacionService())->deSolicitud($id)[0] ?? null,
        ]);
    }

    /** CU-23 pasos 3 y 4. */
    public function verificacion(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.localizar')->with('error', $e->getMessage());
        }
        if ($s['estado'] !== 'PendienteVerificacion') {
            return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('error', 'La solicitud no está pendiente de verificación.');
        }

        return view('solicitudes/verificar', ['titulo' => 'Verificar legajo', 's' => $s, 'tipos' => (new LegajoService())->tiposDocumentacion()]);
    }

    /** CU-23 pasos 5 a 8. */
    public function verificar(int $id): RedirectResponse
    {
        try {
            $estado = $this->servicio->verificar(
                $id,
                (int) $this->request->getPost('version_fila'),
                $this->campoPost('resultado'),
                (array) $this->request->getPost('verificacion'),
                $this->campoPost('observaciones'),
                $this->campoPost('motivo_rechazo'),
                (int) session('cuenta')['id'],
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        // CU-86 paso 1: la evaluación automática se inicia inmediatamente; si el proceso programado está en curso, la toma él
        $evaluacion = $estado === 'PendienteEvaluacion' ? (new EvaluacionService())->iniciarInmediata($id) : null;
        $mensaje = match ($evaluacion) {
            'Favorable' => $this->servicio->estado($id) === 'PendienteSeleccionPlan'
                ? 'Legajo verificado. Evaluación crediticia favorable: las alternativas de financiación están listas para presentarlas al cliente.'
                : 'Legajo verificado. Evaluación crediticia favorable, pero sin alternativas viables para su capacidad de pago: la solicitud pasó a resolución del Gerente.',
            'RequiereAnalisisGerencial' => 'Legajo verificado. La evaluación crediticia requiere análisis gerencial: la solicitud queda pendiente de resolución del Gerente.',
            'Desfavorable' => 'Legajo verificado. Evaluación crediticia desfavorable: la solicitud fue rechazada por causa crediticia.',
            'NoCompletada' => 'Legajo verificado. La evaluación automática no pudo completarse: la solicitud queda pendiente de evaluación de contingencia.',
            default => null,
        } ?? match ($estado) {
            'PendienteEvaluacion' => 'Legajo verificado: la solicitud queda pendiente de evaluación crediticia, que se realizará en los próximos minutos.',
            'PendienteDocumentacion' => 'Legajo observado: la solicitud queda pendiente de documentación hasta que se registren las correcciones.',
            default => 'Legajo rechazado por causa documental: la solicitud finalizó.',
        };

        return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('exito', $mensaje);
    }

    /** CU-23 A4. */
    public function incorporarDocumentos(int $id): RedirectResponse
    {
        try {
            $n = $this->servicio->incorporarDocumentos($id, $this->archivosLegajo(), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/solicitudes/{$id}/verificar"))->with('exito', "Se incorporaron {$n} documento(s) al legajo; continúe la revisión.");
    }

    /** CU-84 pasos 1 a 3. */
    public function correccion(int $id): string|RedirectResponse
    {
        try {
            $s = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('solicitudes.localizar')->with('error', $e->getMessage());
        }
        if (!in_array($s['estado'], EstadosSolicitud::CORREGIBLES, true)) {
            return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('error', 'La solicitud no admite correcciones en su estado actual.');
        }

        return view('solicitudes/corregir', ['titulo' => 'Registrar corrección', 's' => $s, 'tipos' => (new LegajoService())->tiposDocumentacion()]);
    }

    /** CU-84 pasos 4 a 8. */
    public function corregir(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->registrarCorreccion($id, (int) $this->request->getPost('version_fila'), $this->archivosLegajo(), $this->campoPost('aclaracion'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        if (!$r['cambio']) {
            return redirect()->to(site_url("admin/solicitudes/{$id}/corregir"))
                ->with('aviso', 'Corrección parcial guardada. La solicitud sigue pendiente de documentación hasta completar: ' . implode('; ', $r['pendientes']) . '.');
        }

        return redirect()->to(site_url("admin/solicitudes/{$id}"))->with('exito', 'Corrección registrada: la solicitud pasó a ' . mb_strtolower(EstadosSolicitud::etiqueta($r['estado'])) . '.');
    }
}
