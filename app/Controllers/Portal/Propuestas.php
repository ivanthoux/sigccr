<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Exceptions\AccesoAjenoException;
use App\Exceptions\ReglaNegocioException;
use App\Services\Campanas\CampanaService;
use App\Services\Cartera\RefinanciacionService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Refinanciaciones y ofertas del portal: CU-43 Consultar Propuesta de Refinanciación (A5, el cliente consulta sus
 * propias propuestas) y CU-52 Registrar Respuesta a Oferta Crediticia.
 */
class Propuestas extends BasePortal
{
    /** CU-52 pasos 1 y 2; CU-43 pasos 1 y 2 (A5). */
    public function index(): string
    {
        return view('portal/propuestas', [
            'titulo' => 'Refinanciaciones y ofertas',
            'ofertas' => (new CampanaService())->ofertasDelCliente($this->clienteId()),
            'propuestas' => (new RefinanciacionService())->buscar(['cliente_id' => $this->clienteId()]),
        ]);
    }

    /** CU-43 pasos 3 a 5 (A1 a A5). */
    public function propuesta(int $id): string|RedirectResponse
    {
        try {
            $p = (new RefinanciacionService())->propuesta($id, $this->clienteId());
        } catch (ReglaNegocioException) {
            (new AuditoriaService())->registrar('portal.acceso-denegado', 'propuestas_refinanciacion', $id, 'Rechazado', 'Propuesta inexistente o de otro cliente');

            return $this->volverA('portal.propuestas', new AccesoAjenoException());
        }
        (new AuditoriaService())->registrar('portal.consultar-propuesta', 'propuestas_refinanciacion', $id);

        return view('portal/propuesta', ['titulo' => 'Propuesta de refinanciación N.º ' . $p['numero'], 'p' => $p]);
    }

    /** CU-52 pasos 3, 4 y 7. */
    public function oferta(int $id): string|RedirectResponse
    {
        try {
            $o = (new CampanaService())->ofertaParaCliente($this->clienteId(), $id);
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.propuestas', $e);
        }

        return view('portal/oferta', ['titulo' => 'Oferta crediticia', 'o' => $o]);
    }

    /** CU-52 pasos 5 a 10 (A2 a A5, A7); la confirmación del paso 8 la pide el formulario (D-24). */
    public function aceptar(int $id): RedirectResponse
    {
        try {
            $r = (new CampanaService())->aceptar($this->clienteId(), $id, $this->enteroPost('version_fila'), trim($this->campoPost('alternativa')));
        } catch (ReglaNegocioException $e) {
            return $this->volverConErrores($e);
        }

        return redirect()->to(site_url('portal/solicitudes/' . $r['solicitud_id']))->with('exito', "Aceptó la oferta: se generó la solicitud N.º {$r['numero']}. Para continuar el trámite, presente en la sucursal la documentación que exige la línea; la aceptación no implica el desembolso ni genera deuda.");
    }

    /** CU-52 A1. */
    public function rechazar(int $id): RedirectResponse
    {
        try {
            (new CampanaService())->rechazar($this->clienteId(), $id, $this->enteroPost('version_fila'));
        } catch (ReglaNegocioException $e) {
            return $this->volverA('portal.propuestas', $e);
        }

        return redirect()->route('portal.propuestas')->with('exito', 'Rechazó la oferta. No se generó ninguna solicitud.');
    }
}
