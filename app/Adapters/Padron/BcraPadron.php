<?php

declare(strict_types=1);

namespace App\Adapters\Padron;

use App\Adapters\InformacionCrediticia\BcraInformacionCrediticia;
use App\Adapters\ServicioNoDisponibleException;
use Throwable;

/**
 * Denominación de una persona según la Central de Deudores del BCRA (API pública, sin credenciales; D-59).
 *
 *   GET {url}/Deudas/{cuit} → 200 {"status":200,"results":{"identificacion":…,"denominacion":"PEREZ ANA",…}}
 *                           → 404 si el BCRA no tiene deudas informadas para esa identificación
 *
 * Es el mismo servicio que usa la evaluación crediticia (CU-86). Se consulta mientras el Analista completa el alta,
 * así que hace un solo intento, con la espera de sigccr.tiempoEsperaExterno (RNF-08): sin respuesta, con una
 * respuesta distinta de 200 o 404 o con una denominación vacía, lanza ServicioNoDisponibleException. La comunicación
 * usa HTTPS con verificación de certificados.
 */
final class BcraPadron implements PadronInterface
{
    /** @var callable(string): array{status: int, body: string} */
    private $http;

    /** @param (callable(string): array{status: int, body: string})|null $http petición GET (por defecto, CURLRequest) */
    public function __construct(
        private readonly string $url = BcraInformacionCrediticia::URL,
        private readonly int $tiempoEspera = 10,
        ?callable $http = null,
    ) {
        $this->http = $http ?? $this->clienteHttp(...);
    }

    public function consultar(string $cuit): ?string
    {
        if (preg_match('/^\d{11}$/', $cuit) !== 1) {
            throw new ServicioNoDisponibleException('El CUIT o CUIL no tiene 11 dígitos.');
        }
        try {
            $r = ($this->http)(rtrim($this->url, '/') . '/Deudas/' . $cuit);
        } catch (Throwable) {
            throw new ServicioNoDisponibleException('La Central de Deudores no respondió.');
        }
        if ($r['status'] === 404) {
            return null;
        }
        if ($r['status'] !== 200) {
            throw new ServicioNoDisponibleException("La Central de Deudores respondió {$r['status']}.");
        }
        $json = json_decode($r['body'], true);
        $denominacion = is_array($json) ? trim((string) ($json['results']['denominacion'] ?? '')) : '';
        if ($denominacion === '') {
            throw new ServicioNoDisponibleException('La Central de Deudores devolvió una respuesta sin denominación.');
        }

        return (string) preg_replace('/\s+/u', ' ', $denominacion);
    }

    /** @return array{status: int, body: string} */
    private function clienteHttp(string $url): array
    {
        $r = service('curlrequest', [
            'timeout' => $this->tiempoEspera, 'connect_timeout' => $this->tiempoEspera, 'http_errors' => false, 'verify' => true,
        ], null, false)->get($url, ['headers' => ['Accept' => 'application/json']]);

        return ['status' => $r->getStatusCode(), 'body' => (string) $r->getBody()];
    }
}
