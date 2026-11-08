<?php

declare(strict_types=1);

namespace App\Controllers\Api\Webhooks;

use App\Controllers\BaseController;
use App\Services\Caja\PagoElectronicoService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CU-90 pasos 1 a 3 (A1): recibe la notificación de la pasarela, la registra y responde el acuse sin procesarla; el
 * proceso pagos:notificaciones la procesa en segundo plano (RNF-08). No usa sesión ni CSRF: se valida por firma.
 */
class Pasarela extends BaseController
{
    public function recibir(): ResponseInterface
    {
        $cabeceras = [];
        foreach ($this->request->headers() as $nombre => $cabecera) {
            $cabeceras[strtolower((string) $nombre)] = $cabecera->getValueLine();
        }
        $valida = (new PagoElectronicoService())->recibirNotificacion((string) $this->request->getBody(), $cabeceras);

        return $this->response->setStatusCode($valida ? 200 : 401)->setJSON(['recibida' => $valida]);
    }
}
