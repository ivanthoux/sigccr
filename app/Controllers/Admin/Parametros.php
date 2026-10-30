<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorParametrosEstructuracion as E;
use App\Libraries\Validacion\ValidadorParametrosEvaluacion as V;
use App\Services\Comunes\ParametroService;
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
        if (($m = Campos::motivo($motivo)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-69');
            }
            $r = (new ParametroService())->registrarVersion('Evaluacion', $p, (string) $fecha, $motivo, (int) session('cuenta')['id']);
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
        if (($m = Campos::motivo($motivo)) !== null) {
            $errores['motivo'] = $m;
        }
        try {
            if ($errores !== []) {
                throw new ValidacionException($errores, 'RN-15');
            }
            $r = (new ParametroService())->registrarVersion('Estructuracion', $p, (string) $fecha, $motivo, (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('parametros.estructuracion')->with('exito', "Se registró la versión {$r['numero']} de los parámetros de estructuración, vigente desde el " . formato_fecha_hora($r['vigente_desde']) . '. Las simulaciones y alternativas vigentes conservan sus condiciones (RN-15).');
    }
}
