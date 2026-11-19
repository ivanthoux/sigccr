<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Caja\CajaService;
use App\Services\Caja\CancelacionAnticipadaService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-97 Registrar Cancelación Anticipada de Crédito (Cajero). */
class CancelacionesAnticipadas extends BaseController
{
    /** CU-97 pasos 1 y 2 (A1, A2). */
    public function index(): string|RedirectResponse
    {
        $cliente = $this->clienteSeleccionado('creditos.cancelar-anticipadamente', 'Cancelación anticipada');
        if (!is_array($cliente)) {
            return $cliente;
        }

        return view('cancelaciones/creditos', [
            'titulo' => 'Cancelación anticipada', 'cliente' => $cliente, 'creditos' => (new CancelacionAnticipadaService())->creditos((int) $cliente['id']),
            'jornada' => (new CajaService())->jornadaOperable((int) session('cuenta')['id']),
        ]);
    }

    /** CU-97 pasos 3 y 4 (A4, A5). */
    public function credito(int $id): string|RedirectResponse
    {
        try {
            $d = (new CancelacionAnticipadaService())->calcular($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('creditos.cancelar-anticipadamente')->with('error', $e->getMessage());
        }

        return view('cancelaciones/credito', ['titulo' => 'Cancelación del crédito N.º ' . $d['credito']['numero'], 'd' => $d, 'jornada' => (new CajaService())->jornadaOperable((int) session('cuenta')['id'])]);
    }

    /** CU-97 pasos 5 a 8 (A1, A3, A4, A6). */
    public function registrar(int $id): RedirectResponse
    {
        try {
            $r = (new CancelacionAnticipadaService())->registrar(
                (int) session('cuenta')['id'],
                $id,
                $this->campoPost('importe'),
                $this->campoPost('medio'),
                $this->campoPost('numero_operacion'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/cobros/{$r['cobro_id']}/comprobante"))->with('exito', 'Crédito cancelado anticipadamente con el cobro N.º ' . $r['numero'] . ' por ' . formato_importe($r['importe']) . '. Todas sus cuotas quedaron pagadas.');
    }
}
