<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Solicitudes\ModeloContrato;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorParametrosEstructuracion as E;
use App\Libraries\Validacion\ValidadorParametrosCampanas as C;
use App\Libraries\Validacion\ValidadorParametrosMorosidad as M;
use App\Libraries\Validacion\ValidadorParametrosEvaluacion as V;
use App\Services\Comunes\ParametroService;
use App\Services\Solicitudes\ContratoService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-58 Configurar Parámetros de Evaluación Crediticia (RN-69, D-13). El Gerente ajusta pesos, valores, cortes y
 * situaciones excluyentes del motor que usa la Central de Deudores del BCRA; los valores de RN-69 quedan como
 * sugeridos y pueden cargarse otra vez en el formulario.
 */
class Parametros extends BaseController
{
    public function evaluacion(): string
    {
        $servicio = new ParametroService();
        $vigente = $servicio->vigente('Evaluacion');
        $sugeridos = $this->request->getGet('sugeridos') !== null;

        return view('parametros/evaluacion', [
            'titulo' => 'Parámetros de evaluación crediticia',
            'vigente' => $vigente,
            'f' => V::aFormulario($sugeridos ? V::sugeridos() : $vigente['valores'] + ['vigencia_dias' => V::VIGENCIA_SUGERIDA]),
            'sugeridos' => $sugeridos,
            'historial' => $servicio->historial('Evaluacion'),
        ]);
    }

    public function guardarEvaluacion(): RedirectResponse
    {
        $p = V::normalizar((array) $this->request->getPost());
        $errores = V::validar($p);
        $fecha = Campos::fecha($this->request->getPost('fecha_vigencia'));
        if ($fecha === null) {
            $errores['fecha_vigencia'] = 'Ingrese la fecha desde la que rige la nueva configuración.';
        }
        $motivo = Campos::texto($this->request->getPost('motivo'));
        // H-19: la columna motivo de versiones_parametros es NVARCHAR(500); antes se admitían 1000 y respondía error 500
        if (($m = Campos::motivo($motivo, 500)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-69');
            }
            $parametros = new ParametroService();
            if ($this->request->getPost('confirmar_cambio') !== '1' && ($n = $parametros->solicitudesEnTramite()) > 0) {
                throw new ValidacionException(['confirmar_cambio' => "Hay {$n} solicitud(es) en trámite. Las evaluaciones ya iniciadas conservan los parámetros con los que se hicieron (RN-15): confirme el cambio para registrar la nueva versión."], 'RN-15');
            }
            $r = $parametros->registrarVersion('Evaluacion', $p, (string) $fecha, $motivo, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('parametros.evaluacion')->with('exito', "Se registró la versión {$r['numero']} de los parámetros de evaluación, vigente desde el " . formato_fecha_hora($r['vigente_desde']) . '. Las evaluaciones en curso conservan los parámetros con los que se iniciaron.');
    }

    /** CU-59 pasos 1 y 2. */
    public function estructuracion(): string
    {
        $servicio = new ParametroService();
        $vigente = $servicio->vigente('Estructuracion');
        $sugeridos = $this->request->getGet('sugeridos') !== null;

        return view('parametros/estructuracion', [
            'titulo' => 'Parámetros de estructuración automática',
            'vigente' => $vigente,
            'f' => E::aFormulario($sugeridos ? E::SUGERIDOS : $vigente['valores']),
            'sugeridos' => $sugeridos,
            'historial' => $servicio->historial('Estructuracion'),
        ]);
    }

    /** CU-59 pasos 3 a 8 (A1, A2). */
    public function guardarEstructuracion(): RedirectResponse
    {
        $p = E::normalizar((array) $this->request->getPost());
        $errores = E::validar($p);
        $fecha = Campos::fecha($this->request->getPost('fecha_vigencia'));
        if ($fecha === null) {
            $errores['fecha_vigencia'] = 'Ingrese la fecha desde la que rige la nueva configuración.';
        }
        $motivo = Campos::texto($this->request->getPost('motivo'));
        if (($m = Campos::motivo($motivo, 500)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-15');
            }
            $parametros = new ParametroService();
            if ($this->request->getPost('confirmar_cambio') !== '1' && ($n = $parametros->solicitudesEnTramite()) > 0) {
                throw new ValidacionException(['confirmar_cambio' => "Hay {$n} solicitud(es) en trámite. Las alternativas ya generadas conservan sus condiciones (RN-15): confirme el cambio para registrar la nueva versión."], 'RN-15');
            }
            $r = $parametros->registrarVersion('Estructuracion', $p, (string) $fecha, $motivo, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('parametros.estructuracion')->with('exito', "Se registró la versión {$r['numero']} de los parámetros de estructuración, vigente desde el " . formato_fecha_hora($r['vigente_desde']) . '. Las simulaciones y alternativas vigentes conservan sus condiciones (RN-15).');
    }

    /** CU-60 pasos 1 y 2. */
    public function morosidad(): string
    {
        $servicio = new ParametroService();
        $vigente = $servicio->vigente('Morosidad');
        $sugeridos = $this->request->getGet('sugeridos') !== null;

        return view('parametros/morosidad', [
            'titulo' => 'Parámetros de morosidad',
            'vigente' => $vigente,
            'f' => M::aFormulario($sugeridos ? M::SUGERIDOS : $vigente['valores']),
            'sugeridos' => $sugeridos,
            'historial' => $servicio->historial('Morosidad'),
        ]);
    }

    /** CU-60 pasos 3 a 8 (A1 a A3). */
    public function guardarMorosidad(): RedirectResponse
    {
        $p = M::normalizar((array) $this->request->getPost());
        $errores = M::validar($p);
        $fecha = Campos::fecha($this->request->getPost('fecha_vigencia'));
        if ($fecha === null) {
            $errores['fecha_vigencia'] = 'Ingrese la fecha desde la que rige la nueva configuración.';
        }
        $motivo = Campos::texto($this->request->getPost('motivo'));
        if (($m = Campos::motivo($motivo, 500)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-35');
            }
            $r = (new ParametroService())->registrarVersion('Morosidad', $p, (string) $fecha, $motivo, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('parametros.morosidad')->with('exito', "Se registró la versión {$r['numero']} de los parámetros de morosidad, vigente desde el " . formato_fecha_hora($r['vigente_desde'])
            . '. La nueva tasa punitoria rige para todos los créditos desde esa fecha, sin modificar los días ya calculados; la tolerancia nueva se asigna solo a los créditos que se liquiden desde entonces (RN-35).');
    }

    // ── CU-51 Configurar Parámetros de Campañas Crediticias ────────────────────

    /** Pasos 1 y 2 (A2). */
    public function campanas(): string
    {
        $servicio = new ParametroService();
        $vigente = $servicio->vigente('Campanas');

        return view('parametros/campanas', [
            'titulo' => 'Parámetros de campañas crediticias',
            'vigente' => $vigente,
            'f' => C::aFormulario($vigente['valores']),
            'lineas' => $this->lineasParaCampanas(),
            'historial' => $servicio->historial('Campanas'),
        ]);
    }

    /** Pasos 3 a 8 (A1, A3). */
    public function guardarCampanas(): RedirectResponse
    {
        $lineas = $this->lineasParaCampanas();
        $p = C::normalizar((array) $this->request->getPost());
        $errores = C::validar($p, array_map('intval', array_column($lineas, 'id')));
        $fecha = Campos::fecha($this->request->getPost('fecha_vigencia'));
        if ($fecha === null) {
            $errores['fecha_vigencia'] = 'Ingrese la fecha desde la que rige la nueva configuración.';
        }
        $motivo = Campos::texto($this->request->getPost('motivo'));
        if (($m = Campos::motivo($motivo, 500)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-40');
            }
            $r = (new ParametroService())->registrarVersion('Campanas', $p, (string) $fecha, $motivo, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('parametros.campanas')->with('exito', "Se registró la versión {$r['numero']} de los parámetros de campañas, vigente desde el " . formato_fecha_hora($r['vigente_desde'])
            . '. Las campañas activas conservan los parámetros con los que se generaron; las nuevas usarán esta versión (RN-40).');
    }

    /**
     * CU-51 A2: líneas activas que pueden habilitarse para las campañas.
     *
     * @return list<array{id: int|string, nombre: string, codigo: string}>
     */
    private function lineasParaCampanas(): array
    {
        return db_connect()->table('lineas_credito')->select('id, nombre, codigo')->where('estado', 'Activa')->orderBy('nombre')->get()->getResultArray();
    }

    // ── CU-106 Configurar Modelo de Contrato (D-56) ────────────────────────────

    /** Pasos 1 a 3: cláusulas del modelo vigente (o del inicial, A4), variables disponibles y versiones anteriores. */
    public function contrato(): string
    {
        $servicio = new ContratoService();
        $vigente = $servicio->modeloVigente();
        $inicial = $this->request->getGet('inicial') !== null;
        // Al volver con observaciones, el formulario conserva lo escrito (las filas vacías se descartan)
        $escritas = old('texto') !== null ? ModeloContrato::normalizar(['estilo' => old('estilo'), 'titulo' => old('titulo'), 'texto' => old('texto')]) : null;

        return view('parametros/contrato', [
            'titulo' => 'Modelo de contrato',
            'vigente' => $vigente,
            'clausulas' => $escritas ?? ($inicial ? ModeloContrato::INICIAL : $vigente['clausulas']),
            'inicial' => $inicial,
            'historial' => (new ParametroService())->historial('Contrato'),
        ]);
    }

    /** Pasos 4 a 7 (A1 a A3). */
    public function guardarContrato(): RedirectResponse
    {
        $clausulas = ModeloContrato::normalizar((array) $this->request->getPost());
        $errores = ModeloContrato::validar($clausulas);
        $fecha = Campos::fecha($this->request->getPost('fecha_vigencia'));
        if ($fecha === null) {
            $errores['fecha_vigencia'] = 'Ingrese la fecha desde la que rige el nuevo modelo.';
        }
        $motivo = Campos::texto($this->request->getPost('motivo'));
        if (($m = Campos::motivo($motivo, 500)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-78');
            }
            $r = (new ContratoService())->registrarModelo($clausulas, (string) $fecha, $motivo, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('parametros.contrato')->with('exito', "Se registró la versión {$r['numero']} del modelo de contrato, vigente desde el " . formato_fecha_hora($r['vigente_desde'])
            . '. Se aplica a los contratos que se generen desde entonces; los ya generados conservan su texto (RN-15).');
    }
}
