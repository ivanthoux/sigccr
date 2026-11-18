<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Fondos\FondosService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-85 Registrar Movimiento de Fondos (RN-44, RN-71). */
class Fondos extends BaseController
{
    private FondosService $servicio;

    public function __construct()
    {
        $this->servicio = new FondosService();
    }

    /** Paso 2: disponibilidad y movimientos. */
    public function index(): string
    {
        return view('fondos/index', [
            'titulo' => 'Fondos de la entidad',
            'd' => $this->servicio->disponibilidad(),
            'saldos' => $this->servicio->saldosAFavor(),
            'proyeccion' => $this->servicio->ultimaProyeccion(),
            'movimientos' => $this->servicio->movimientos(),
        ]);
    }

    /** Pasos 3 a 7 (A1). */
    public function registrar(): RedirectResponse
    {
        try {
            $this->servicio->registrarMovimiento((array) $this->request->getPost(['tipo', 'caracter', 'programado', 'importe', 'fecha', 'concepto', 'referencia']), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->route('fondos.movimientos')->with('exito', 'Movimiento registrado. La disponibilidad se actualizó.');
    }

    /** A2: corrección con un movimiento inverso. */
    public function corregir(int $id): RedirectResponse
    {
        return $this->operar(fn () => $this->servicio->corregir($id, $this->campoPost('motivo'), (int) session('cuenta')['id']), 'Se registró el movimiento inverso que corrige el N.º ' . $id . '.', $id);
    }

    /** A3: confirmación de un programado. */
    public function confirmar(int $id): RedirectResponse
    {
        return $this->operar(fn () => $this->servicio->confirmarProgramado($id), 'Movimiento N.º ' . $id . ' confirmado como realizado.', $id);
    }

    /** A3: anulación de un programado. */
    public function anular(int $id): RedirectResponse
    {
        return $this->operar(fn () => $this->servicio->anularProgramado($id, $this->campoPost('motivo')), 'Movimiento N.º ' . $id . ' anulado.', $id);
    }

    private function operar(callable $operacion, string $exito, int $id): RedirectResponse
    {
        try {
            $operacion();
        } catch (ReglaNegocioException $e) {
            return redirect()->route('fondos.movimientos')->with('error', $e->getMessage())->with('errores', $e instanceof \App\Exceptions\ValidacionException ? $e->errores : [])->with('movimiento', $id);
        }

        return redirect()->route('fondos.movimientos')->with('exito', $exito);
    }
}
