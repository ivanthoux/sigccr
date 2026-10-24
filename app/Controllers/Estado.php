<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Config\Database;
use Throwable;

/**
 * Estado de la arquitectura ejecutable: permite comprobar de punta a punta navegador → Nginx → PHP-FPM → SQL Server.
 * No expone datos sensibles ni detalles técnicos en producción (RNF-12 g).
 */
class Estado extends BaseController
{
    public function index(): ResponseInterface
    {
        $base = 'sin conexión';
        try {
            $r = Database::connect()->query('SELECT SYSDATETIMEOFFSET() AS ahora')->getRowArray();
            $base = 'conectada';
        } catch (Throwable $e) {
            log_message('error', 'Estado: ' . $e->getMessage());
        }

        return $this->response->setJSON([
            'sistema' => 'SIG-CCR',
            'entorno' => ENVIRONMENT,
            'base_de_datos' => $base,
            'hora_servidor_utc' => Time::now('UTC')->toDateTimeString(),
            'hora_negocio' => Time::now(config('Sigccr')->zonaNegocio)->toDateTimeString(),
        ])->setStatusCode($base === 'conectada' ? 200 : 503);
    }
}
