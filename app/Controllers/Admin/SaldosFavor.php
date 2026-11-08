<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Caja\CobroService;
use App\Services\Caja\DevolucionService;
use App\Services\Caja\SaldoFavorService;
use App\Services\Clientes\ClienteService;
use App\Services\Solicitudes\SimulacionService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-76 Aplicar Saldo a Favor a Obligaciones (Cajero), CU-77 Autorizar Devolución (Gerente) y CU-78 Ejecutar
 * Devolución (Cajero).
 */
class SaldosFavor extends BaseController
{
    /** CU-76 pasos 1 a 4 (A1, A2). */
    public function aplicacion(): string|RedirectResponse
    {
        $cliente = $this->cliente('saldos-favor.aplicar', 'Aplicar saldo a favor');
        if (!is_array($cliente)) {
            return $cliente;
        }
        $saldos = new SaldoFavorService();

        return view('saldos/aplicar', [
            'titulo' => 'Aplicar saldo a favor', 'cliente' => $cliente, 'saldos' => $saldos->saldos((int) $cliente['id']),
            'aplicaciones' => $saldos->aplicaciones((int) $cliente['id']),
        ] + (new CobroService())->obligaciones((int) $cliente['id']));
    }

    /** CU-76 pasos 5 a 10 (A3, A5). */
    public function aplicar(): RedirectResponse
    {
        $clienteId = (int) $this->request->getPost('cliente_id');
        try {
            $r = (new SaldoFavorService())->aplicar(
                (int) session('cuenta')['id'],
                $clienteId,
                (array) $this->request->getPost('cuotas'),
                (string) $this->request->getPost('fecha_conformidad'),
                (string) $this->request->getPost('medio_conformidad'),
                (string) $this->request->getPost('observaciones'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url('admin/saldos-favor/aplicar?cliente=' . $clienteId))->with('exito', 'Se aplicaron ' . formato_importe($r['importe']) . " del saldo a favor: {$r['canceladas']} cuota(s) pagada(s)"
            . ($r['parcial'] !== null ? " y la cuota {$r['parcial']} con imputación parcial excepcional" : '') . '. Saldo disponible remanente: ' . formato_importe($r['remanente']) . '.');
    }

    /** CU-77 pasos 1 a 5 (A4, A5). */
    public function autorizacion(): string|RedirectResponse
    {
        $cliente = $this->cliente('saldos-favor.autorizar-devolucion', 'Autorizar devolución de saldo a favor');
        if (!is_array($cliente)) {
            return $cliente;
        }

        return view('saldos/autorizar', [
            'titulo' => 'Autorizar devolución', 'cliente' => $cliente, 'saldos' => (new SaldoFavorService())->saldos((int) $cliente['id']),
            'autorizaciones' => (new DevolucionService())->buscar(['cliente_id' => (int) $cliente['id'], 'estado' => 'todas']),
        ]);
    }

    /** CU-77 pasos 6 a 9 (A1 a A4). */
    public function autorizar(int $saldoId): RedirectResponse
    {
        try {
            $r = (new DevolucionService())->autorizar((int) session('cuenta')['id'], $saldoId, (array) $this->request->getPost());
        } catch (ReglaNegocioException $e) {
            if (str_starts_with((string) $e->regla, 'autorizacion:')) {
                return redirect()->to(site_url('admin/saldos-favor/autorizaciones/' . substr((string) $e->regla, 13)))->with('error', $e->getMessage());
            }

            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/saldos-favor/autorizaciones/{$r['id']}"))->with('exito', "Devolución N.º {$r['id']} autorizada por " . formato_importe($r['importe'])
            . ': el importe quedó reservado hasta su ejecución, que vence en 72 horas. Se avisó a los Cajeros.');
    }

    /** CU-77 A5. */
    public function anular(int $id): RedirectResponse
    {
        try {
            (new DevolucionService())->anular((int) session('cuenta')['id'], $id, (int) $this->request->getPost('version_fila'), (string) $this->request->getPost('motivo_anulacion'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->back()->with('exito', 'Autorización anulada: el importe reservado volvió a estar disponible.');
    }

    /** CU-78 pasos 1 y 2. */
    public function devoluciones(): string
    {
        $f = [];
        foreach (['numero', 'texto', 'fecha'] as $k) {
            $f[$k] = trim((string) $this->request->getGet($k));
        }
        $f['estado'] = (string) ($this->request->getGet('estado') ?: 'activas');

        return view('saldos/devoluciones', ['titulo' => 'Devoluciones de saldo a favor', 'filtros' => $f, 'autorizaciones' => (new DevolucionService())->buscar($f)]);
    }

    /** CU-78 paso 4 y constancia (paso 10); CU-77 paso 9. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $a = (new DevolucionService())->autorizacion($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return view('saldos/devolucion', ['titulo' => 'Devolución N.º ' . $id, 'a' => $a]);
    }

    /** CU-78 pasos 5 a 10 (A1 a A6). */
    public function ejecutar(int $id): RedirectResponse
    {
        try {
            $r = (new DevolucionService())->ejecutar((int) session('cuenta')['id'], $id, (int) $this->request->getPost('version_fila'), [
                'numero_operacion' => (string) $this->request->getPost('numero_operacion'), 'fecha' => (string) $this->request->getPost('fecha'),
                'importe' => (string) $this->request->getPost('importe'), 'cbu' => (string) $this->request->getPost('cbu'),
            ]);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/devoluciones/{$id}"))->with($r['ok'] ? 'exito' : 'error', $r['mensaje']);
    }

    /**
     * Búsqueda del cliente (CU-54).
     *
     * @return array<string, mixed>|string|RedirectResponse
     */
    private function cliente(string $destino, string $titulo): array|string|RedirectResponse
    {
        $clienteId = (int) $this->request->getGet('cliente');
        if ($clienteId === 0) {
            $texto = trim((string) $this->request->getGet('texto'));

            return view('simulaciones/cliente', [
                'titulo' => $titulo, 'texto' => $texto, 'destino' => $destino,
                'clientes' => $texto === '' ? [] : (new ClienteService())->buscar(['texto' => $texto]),
            ]);
        }
        try {
            return (new SimulacionService())->cliente($clienteId);
        } catch (ReglaNegocioException $e) {
            return redirect()->route($destino)->with('error', $e->getMessage());
        }
    }
}
