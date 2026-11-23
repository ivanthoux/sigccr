<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Clientes\DerechosTitularService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-108 Atender Solicitud de Derechos del Titular, pasos 1 a 3 (portal): el cliente pide el acceso, la rectificación,
 * la actualización o la supresión de sus datos, o la revisión de una decisión automatizada (Ley 25.326, arts. 14, 16 y
 * 20), y sigue su respuesta. El cliente es siempre el de la sesión (RN-01).
 */
class Derechos extends BasePortal
{
    public function index(): string
    {
        $s = new DerechosTitularService();

        return view('portal/derechos', [
            'titulo' => 'Ejercer mis derechos sobre mis datos',
            'solicitudes' => $s->delCliente($this->clienteId()),
            'revisables' => $s->solicitudesRevisables($this->clienteId()),
        ]);
    }

    public function registrar(): RedirectResponse
    {
        try {
            (new DerechosTitularService())->registrar($this->clienteId(), [
                'tipo' => $this->campoPost('tipo'), 'detalle' => $this->campoPost('detalle'), 'solicitud_id' => $this->enteroPost('solicitud_id'),
            ], 'Portal');
        } catch (ReglaNegocioException $e) {
            return $this->volverConErrores($e);
        }

        return redirect()->route('portal.derechos')->with('exito', 'Recibimos su solicitud. La responderemos dentro del plazo legal y verá la respuesta en esta pantalla.');
    }
}
