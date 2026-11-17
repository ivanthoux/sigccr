<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Validacion\Campos;
use App\Services\Caja\CajaService;
use App\Services\Caja\CobroService;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\HTTP\RedirectResponse;

/** CU-33 Registrar Cobro en Ventanilla, CU-35 Emitir Comprobante de Pago y CU-37 Anular Cobro. */
class Cobros extends BaseController
{
    private CobroService $servicio;

    public function __construct()
    {
        $this->servicio = new CobroService();
    }

    /** CU-33 pasos 1 a 6 (A2, A3, A4): cliente, obligaciones pendientes y formulario del cobro. */
    public function index(): string|RedirectResponse
    {
        $usuario = (int) session('cuenta')['id'];
        $jornada = (new CajaService())->jornadaOperable($usuario);
        $clienteId = (int) $this->request->getGet('cliente');
        if ($clienteId === 0) {
            return $this->busquedaCliente('cobros.registrar', 'Cobro en ventanilla', false, ['jornada' => $jornada]);
        }
        try {
            $cliente = (new SimulacionService())->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('cobros.registrar')->with('error', $e->getMessage());
        }

        return view('cobros/nuevo', ['titulo' => 'Cobro a ' . $cliente['denominacion'], 'cliente' => $cliente, 'jornada' => $jornada] + $this->servicio->obligaciones($clienteId));
    }

    /** CU-33 pasos 7 a 12. */
    public function registrar(): RedirectResponse
    {
        $medios = [];
        foreach ((array) $this->request->getPost('medios') as $m) {
            $m = (array) $m;
            if (trim((string) ($m['importe'] ?? '')) === '' && trim((string) ($m['numero_operacion'] ?? '')) === '') {
                continue;
            }
            $medios[] = ['medio' => (string) ($m['medio'] ?? ''), 'importe' => (string) (Campos::importe($m['importe'] ?? '') ?? '0'), 'numero_operacion' => (string) ($m['numero_operacion'] ?? '')];
        }
        try {
            $r = $this->servicio->registrar(
                (int) session('cuenta')['id'],
                (int) $this->request->getPost('cliente_id'),
                (array) $this->request->getPost('cuotas'),
                (string) $this->request->getPost('importe'),
                $medios,
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = 'Cobro N.º ' . $r['numero'] . ' registrado por ' . formato_importe($r['importe']) . ': ' . $r['canceladas'] . ' cuota(s) pagada(s)'
            . ($r['parcial'] !== null ? " y la cuota {$r['parcial']} con saldo pendiente (D-12)" : '') . '.';

        return redirect()->to(site_url("admin/cobros/{$r['cobro_id']}/comprobante"))->with('exito', $mensaje);
    }

    /** CU-35 pasos 1 a 3 (A2) y CU-37 pasos 1 y 2. */
    public function comprobantes(): string
    {
        $anular = url_is('admin/cobros/anulaciones*');
        $f = [
            'numero' => preg_replace('/\D/', '', (string) $this->request->getGet('numero')),
            'texto' => trim((string) $this->request->getGet('texto')),
            'fecha' => Campos::fecha((string) $this->request->getGet('fecha')),
        ];
        $buscado = $f['numero'] !== '' || $f['texto'] !== '' || $f['fecha'] !== null;

        return view('cobros/comprobantes', [
            'titulo' => $anular ? 'Anular cobro' : 'Comprobantes de pago', 'anular' => $anular,
            'f' => $f + ['fecha_texto' => (string) $this->request->getGet('fecha')], 'cobros' => $buscado ? $this->servicio->buscar($f) : null,
        ]);
    }

    /** CU-35 pasos 4 a 7 (A1). */
    public function comprobante(int $id): string|RedirectResponse
    {
        try {
            $c = $this->servicio->comprobante($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('cobros.emitir-comprobante')->with('error', $e->getMessage());
        }
        $this->servicio->registrarEmision($id, false);

        return view('cobros/comprobante', ['titulo' => 'Comprobante N.º ' . $c['comprobante'], 'c' => $c, 'imprimir' => $this->request->getGet('imprimir') === '1']);
    }

    /** CU-35 A3: la reemisión conserva la identidad del cobro y se audita (RN-31). */
    public function reemitir(int $id): RedirectResponse
    {
        $this->servicio->registrarEmision($id, true);

        return redirect()->to(site_url("admin/cobros/{$id}/comprobante?imprimir=1"));
    }

    /** CU-37 pasos 3 a 6 (A1, A2). */
    public function anulacion(int $id): string|RedirectResponse
    {
        try {
            $c = $this->servicio->comprobante($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('cobros.anular')->with('error', $e->getMessage());
        }

        return view('cobros/anular', ['titulo' => 'Anular cobro N.º ' . $c['numero'], 'c' => $c, 'dependientes' => $c['estado'] === 'Anulado' ? [] : $this->servicio->dependientes($id)]);
    }

    /** CU-37 pasos 7 a 10 (A4). */
    public function anular(int $id): RedirectResponse
    {
        try {
            $r = $this->servicio->anular($id, (string) $this->request->getPost('motivo'), $this->request->getPost('continuar') === '1', (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/cobros/{$id}/anular"))->with('exito', 'Cobro anulado: ' . $r['cuotas'] . ' cuota(s) recuperaron su saldo y el importe de ' . formato_importe($r['saldo_a_favor'])
            . ' quedó como saldo a favor del cliente (RN-57).' . ($r['dependientes'] > 0 ? " Quedaron identificados {$r['dependientes']} cobro(s) posteriores para su revisión." : ''));
    }
}
