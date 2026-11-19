<?php

declare(strict_types=1);

namespace App\Controllers\Simulador;

use App\Controllers\BaseController;
use App\Adapters\Pasarela\SimuladorPasarela;
use App\Services\Caja\PagoElectronicoService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use Config\Database;

/**
 * Página de pago del simulador de la pasarela (solo fuera de producción). Permite aprobar, rechazar, dejar pendiente
 * o pagar un importe distinto, con o sin notificación, para probar CU-90 y CU-91 (A2 a A8).
 */
class Pasarela extends BaseController
{
    public function pagar(string $referencia): string
    {
        $orden = $this->orden($referencia);
        $pagos = $this->leer()[$referencia] ?? [];

        return view('simulador/pagar', ['orden' => $orden, 'pagos' => array_values($pagos)]);
    }

    public function registrar(string $referencia): RedirectResponse
    {
        $orden = $this->orden($referencia);
        $estado = $this->campoPost('estado');
        $estado = in_array($estado, ['approved', 'rejected', 'in_process'], true) ? $estado : 'approved';
        $importe = str_replace(',', '.', trim($this->campoPost('importe')));
        $importe = is_numeric($importe) && (float) $importe > 0 ? number_format((float) $importe, 2, '.', '') : (string) $orden['importe'];
        $repetir = $this->campoPost('id_externo');
        $datos = $this->leer();
        $id = $repetir !== '' && isset($datos[$referencia][$repetir]) ? $repetir : 'SIMPAY-' . bin2hex(random_bytes(5));
        $fecha = trim($this->campoPost('fecha_efectiva'));
        $fechaEfectiva = $fecha !== '' ? Time::parse($fecha, config('Sigccr')->zonaNegocio) : Time::now(config('Sigccr')->zonaNegocio);
        $datos[$referencia][$id] = ['id_externo' => $id, 'estado' => $estado, 'importe' => $importe, 'moneda' => 'ARS', 'fecha_efectiva' => $fechaEfectiva->format('c')];
        file_put_contents(WRITEPATH . 'simulador_pasarela.json', json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        $mensaje = "Pago {$id} registrado en el simulador ({$estado}, $ {$importe}).";
        if ($this->request->getPost('notificar') === '1') {
            $cuerpo = (string) json_encode(['id' => 'EVT-' . bin2hex(random_bytes(6)), 'type' => 'payment', 'data' => ['id' => $id], 'referencia_externa' => $referencia]);
            $secreto = config('Sigccr')->pasarelaSecretoNotificaciones ?: 'simulador';
            $firma = $this->request->getPost('firma_invalida') === '1' ? 'firma-invalida' : hash_hmac('sha256', $cuerpo, $secreto);
            $valida = (new PagoElectronicoService())->recibirNotificacion($cuerpo, ['x-signature' => $firma]);
            $mensaje .= $valida ? ' Notificación enviada: la procesará pagos:notificaciones.' : ' Notificación con firma no válida: quedó descartada (CU-90 A1).';
        } else {
            $mensaje .= ' Sin notificación: lo detectará la conciliación pagos:conciliar (CU-91).';
        }

        return redirect()->to(site_url('simulador/pagar/' . $referencia))->with('exito', $mensaje);
    }

    /** Devoluciones (CU-78): modo de respuesta de la pasarela y resolución de las que quedaron sin resultado. */
    public function devoluciones(): string
    {
        return view('simulador/devoluciones', ['datos' => SimuladorPasarela::leerDevoluciones()]);
    }

    public function configurarDevoluciones(): RedirectResponse
    {
        $datos = SimuladorPasarela::leerDevoluciones();
        $modo = $this->campoPost('modo');
        if (in_array($modo, ['approved', 'rejected', 'in_process', 'timeout'], true)) {
            $datos['modo'] = $modo;
        }
        $operacion = $this->campoPost('operacion');
        $estado = $this->campoPost('estado');
        if ($operacion !== '' && isset($datos['devoluciones'][$operacion]) && in_array($estado, ['approved', 'rejected'], true)) {
            $datos['devoluciones'][$operacion]['estado'] = $estado;
            $datos['devoluciones'][$operacion]['id_devolucion'] = $estado === 'approved' ? 'SIMREF-' . bin2hex(random_bytes(4)) : null;
            $datos['devoluciones'][$operacion]['detalle'] = $estado === 'rejected' ? 'Rechazada por el emisor (simulado)' : null;
        }
        SimuladorPasarela::guardarDevoluciones($datos);

        return redirect()->to(site_url('simulador/devoluciones'))->with('exito', 'Simulador actualizado. Las devoluciones resueltas se registran con pagos:conciliar (CU-91).');
    }

    /** @return array<string, mixed> */
    private function orden(string $referencia): array
    {
        $o = Database::connect()->table('ordenes_pago_electronico')->where('referencia_externa', $referencia)->get()->getRowArray();
        if ($o === null) {
            throw PageNotFoundException::forPageNotFound('Orden inexistente en el simulador.');
        }

        return $o;
    }

    /** @return array<string, array<string, array<string, string>>> */
    private function leer(): array
    {
        $archivo = WRITEPATH . 'simulador_pasarela.json';
        $datos = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : [];

        return is_array($datos) ? $datos : [];
    }
}
