<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Solicitudes\CalendarioOperativo;
use App\Services\Solicitudes\CalendarioService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-82 Actualizar Calendario Operativo. */
class Calendario extends BaseController
{
    /** Paso 2: días no hábiles del año elegido. */
    public function index(): string
    {
        $anio = (int) ($this->request->getGet('anio') ?: substr(hoy_negocio(), 0, 4));
        $anio = max(2000, min(2100, $anio));

        return view('calendario/index', ['titulo' => 'Calendario operativo', 'anio' => $anio, 'dias' => (new CalendarioService())->delAnio($anio)]);
    }

    /** Pasos 3 a 7 (A1 a A3). */
    public function agregar(): RedirectResponse
    {
        $tipo = $this->campoPost('tipo');
        $filas = [['fecha' => $this->campoPost('fecha'), 'descripcion' => $this->campoPost('descripcion'), 'tipo' => $tipo]];
        $filas = array_merge($filas, CalendarioOperativo::filasDeTexto($this->campoPost('carga'), $tipo));
        try {
            $r = (new CalendarioService())->agregar($filas);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $redirect = redirect()->route('calendario.actualizar')->with('exito', "Se registraron {$r['agregadas']} día(s) no hábil(es). Las aprobaciones ya registradas conservan su vencimiento calculado (RN-49).");

        return $r['rechazadas'] === [] ? $redirect : $redirect->with('aviso', 'Fechas rechazadas: ' . implode('; ', $r['rechazadas']) . '.');
    }

    /** Paso 3: baja de un día. */
    public function baja(): RedirectResponse
    {
        try {
            (new CalendarioService())->darDeBaja($this->campoPost('fecha'));
        } catch (ReglaNegocioException $e) {
            return redirect()->route('calendario.actualizar')->with('error', $e->getMessage());
        }

        return redirect()->route('calendario.actualizar')->with('exito', 'Día no hábil dado de baja. Las aprobaciones ya registradas conservan su vencimiento (RN-49).');
    }
}
