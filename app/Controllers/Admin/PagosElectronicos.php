<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Caja\CobroService;
use App\Services\Caja\PagoElectronicoService;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/** CU-34 Generar Orden de Pago Electrónico y CU-38 Consultar Estado de Pago Electrónico (Cajero, ventanilla). */
class PagosElectronicos extends BaseController
{
    /** CU-34 pasos 1 a 3 (A2): cliente y cuotas pendientes. */
    public function nueva(): string|RedirectResponse
    {
        $cliente = $this->clienteSeleccionado('pagos-electronicos.generar-orden', 'Orden de pago electrónico');
        if (!is_array($cliente)) {
            return $cliente;
        }

        return view('pagos/nueva', ['titulo' => 'Orden de pago para ' . $cliente['denominacion'], 'cliente' => $cliente] + (new CobroService())->obligaciones((int) $cliente['id']));
    }

    /** CU-34 pasos 4 a 11. */
    public function generar(): RedirectResponse
    {
        try {
            $r = (new PagoElectronicoService())->generarOrden(
                (int) session('cuenta')['id'],
                $this->enteroPost('cliente_id'),
                (array) $this->request->getPost('cuotas'),
                'Ventanilla',
            );
        } catch (ReglaNegocioException $e) {
            if (str_starts_with((string) $e->regla, 'orden:')) {
                return redirect()->to(site_url('admin/pagos-electronicos/' . substr((string) $e->regla, 6)))->with('error', $e->getMessage());
            }

            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/pagos-electronicos/{$r['id']}"))
            ->with('exito', "Orden N.º {$r['numero']} generada por " . formato_importe($r['importe']) . '. Comparta el enlace o el código QR con el cliente: vence a las 00:00 (CU-34).');
    }

    /** CU-38 pasos 1 a 3 (A4, A5). */
    public function index(): string
    {
        $f = [
            'numero' => trim($this->campoGet('numero')),
            'texto' => trim($this->campoGet('texto')),
            'estado' => $this->campoGet('estado'),
        ];

        return view('pagos/ordenes', ['titulo' => 'Pagos electrónicos', 'filtros' => $f, 'ordenes' => (new PagoElectronicoService())->buscar($f)]);
    }

    /** CU-38 paso 4 (A1 a A3). */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $orden = (new PagoElectronicoService())->orden($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('pagos-electronicos.consultar-estado')->with('error', $e->getMessage());
        }

        return view('pagos/orden', ['titulo' => 'Orden N.º ' . $orden['numero'], 'orden' => $orden]);
    }

    /** CU-38 A2: consulta a la pasarela a pedido del Cajero, con el mismo tratamiento que CU-91. */
    public function consultar(int $id): RedirectResponse
    {
        try {
            $r = (new PagoElectronicoService())->procesarOrden($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Consulta a la pasarela, orden ' . $id . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'La pasarela no respondió; el estado se mantiene y se volverá a consultar automáticamente (A2, RNF-09).');
        }

        return redirect()->back()->with('exito', 'Pasarela consultada: ' . $r . '.');
    }
}
