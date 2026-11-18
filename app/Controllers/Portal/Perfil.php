<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Clientes\ClienteService;
use App\Services\Portal\PortalService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * CU-45 Consultar Perfil del Cliente, CU-98 Gestionar Preferencias de Comunicación y CU-75 Cambiar Correo Electrónico
 * por Autogestión (pasos 1 a 5).
 */
class Perfil extends BasePortal
{
    /** CU-45 pasos 1 a 6 (A1 a A3, A5). */
    public function index(): string
    {
        return view('portal/perfil', ['titulo' => 'Mi perfil', 'c' => (new PortalService())->perfil($this->clienteId())]);
    }

    /** CU-98 pasos 1 y 2. */
    public function preferencias(): string
    {
        return view('portal/preferencias', ['titulo' => 'Preferencias de comunicación', 'c' => (new ClienteService())->obtener($this->clienteId())]);
    }

    /** CU-98 pasos 3 a 7 (A1 a A3); la confirmación del paso 4 la pide el formulario (D-24). */
    public function guardarPreferencias(): RedirectResponse
    {
        $otorgar = $this->request->getPost('comunicaciones') === 'Otorgar';
        try {
            $r = (new ClienteService())->cambiarConsentimiento($this->clienteId(), $otorgar);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('portal.preferencias')->with('error', $e->getMessage());
        }
        $mensaje = $otorgar
            ? 'Consentimiento otorgado: podrá recibir ofertas y novedades comerciales de la entidad.'
            : 'Consentimiento revocado: no recibirá nuevas comunicaciones comerciales. Seguirá recibiendo los avisos necesarios sobre sus créditos y pagos.';
        if ($r['ofertas_vigentes'] > 0) {
            $mensaje .= " Tiene {$r['ofertas_vigentes']} oferta(s) vigente(s): puede responderlas mientras conserven su vigencia (A2).";
        }

        return redirect()->route('portal.preferencias')->with('exito', $mensaje);
    }

    /** CU-75 pasos 1 y 2 (A4). */
    public function correo(): string
    {
        return view('portal/cambio_correo', ['titulo' => 'Cambiar correo electrónico', 'c' => (new ClienteService())->obtener($this->clienteId())]);
    }

    /** CU-75 pasos 3 a 5 (A1, A5). */
    public function solicitarCorreo(): RedirectResponse
    {
        try {
            (new ClienteService())->solicitarCambioCorreo($this->clienteId(), $this->campoPost('correo_nuevo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConErrores($e);
        }

        return redirect()->route('portal.perfil')->with('exito', 'Le enviamos un enlace a su correo actual. Ábralo dentro de los próximos 30 minutos para confirmar el cambio; después le enviaremos otro al correo nuevo para verificarlo. Hasta entonces su correo no cambia.');
    }
}
