<?php

declare(strict_types=1);

namespace App\Adapters\InformacionCrediticia;

use App\Adapters\ServicioNoDisponibleException;
use Throwable;

/**
 * Adaptador de la Central de Deudores del BCRA (API pública, sin credenciales). RNF-05, RNF-08.
 *
 *   GET {url}/Deudas/{cuit}             situación del último período informado
 *   GET {url}/Deudas/Historicas/{cuit}  últimos 24 períodos
 *
 * Respuesta 200: {"status":200,"results":{"identificacion":…,"denominacion":…,"periodos":[{"periodo":"AAAAMM",
 * "entidades":[{"entidad":…,"situacion":1,"monto":12.5,…}]}]}}. El monto se informa en miles de pesos. 404 indica
 * que no hay deudas informadas para la identificación.
 *
 * Cada petición espera como máximo sigccr.tiempoEsperaExterno segundos (10) y se reintenta hasta
 * sigccr.reintentosInformacionCrediticia veces (2) ante fallos transitorios: sin respuesta, 429 o 5xx. Agotados, o
 * ante una respuesta no válida, lanza ServicioNoDisponibleException y la evaluación queda No completada (CU-86 A3).
 * La comunicación usa HTTPS con verificación de certificados.
 */
class BcraInformacionCrediticia implements InformacionCrediticiaInterface
{
    public const URL = 'https://api.bcra.gob.ar/CentralDeDeudores/v1.0';

    /** @var callable(string): array{status: int, body: string} */
    private $http;

    /** Resumen de los intercambios de la última consulta, para intercambios_externos (sin datos del cliente). */
    private array $intercambios = [];

    /**
     * @param (callable(string): array{status: int, body: string})|null $http petición GET; lanza una excepción si no
     *                                                                        hay respuesta (por defecto, CURLRequest)
     * @param callable(int): void|null                                  $espera pausa entre reintentos en milisegundos
     */
    public function __construct(
        private readonly string $url = self::URL,
        private readonly int $tiempoEspera = 10,
        private readonly int $reintentos = 2,
        ?callable $http = null,
        private $espera = null,
    ) {
        $this->http = $http ?? $this->clienteHttp(...);
        $this->espera ??= static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function consultar(string $cuitCuil): array
    {
        $this->intercambios = [];
        if (preg_match('/^\d{11}$/', $cuitCuil) !== 1) {
            throw new ServicioNoDisponibleException('El CUIT/CUIL del cliente no tiene 11 dígitos.');
        }
        $base = rtrim($this->url, '/');
        $deudas = [];
        foreach (['Deudas' => "Deudas/{$cuitCuil}", 'Historicas' => "Deudas/Historicas/{$cuitCuil}"] as $operacion => $ruta) {
            foreach ($this->obtener("{$base}/{$ruta}", $operacion) as $p) {
                $periodo = (string) ($p['periodo'] ?? '');
                foreach ((array) ($p['entidades'] ?? []) as $e) {
                    $situacion = (int) ($e['situacion'] ?? 0);
                    $entidad = trim((string) ($e['entidad'] ?? ''));
                    if (preg_match('/^\d{6}$/', $periodo) !== 1 || $situacion < 1 || $situacion > 6 || $entidad === '') {
                        continue;
                    }
                    // Una misma entidad y período puede venir en las dos consultas: se conserva una sola vez
                    $deudas["{$periodo}|{$entidad}"] = [
                        'entidad' => mb_substr($entidad, 0, 150), 'periodo' => $periodo, 'situacion' => $situacion,
                        'monto' => self::milesAPesos((string) ($e['monto'] ?? '0')),
                    ];
                }
            }
        }

        return array_values($deudas);
    }

    /** @return list<array{operacion: string, codigo: string, duracion_ms: int, intento: int}> */
    public function intercambios(): array
    {
        return $this->intercambios;
    }

    /**
     * GET con reintentos. Devuelve los períodos informados, o [] si la respuesta es 404 (sin deudas).
     *
     * @return list<array<string, mixed>>
     */
    private function obtener(string $url, string $operacion): array
    {
        $ultimo = '';
        for ($intento = 1; $intento <= $this->reintentos + 1; $intento++) {
            if ($intento > 1) {
                ($this->espera)(500 * ($intento - 1));
            }
            $inicio = hrtime(true);
            try {
                $r = ($this->http)($url);
            } catch (Throwable $e) {
                $this->registrar($operacion, 'SinRespuesta', $inicio, $intento);
                $ultimo = 'sin respuesta del servicio';
                continue;
            }
            $this->registrar($operacion, (string) $r['status'], $inicio, $intento);
            if ($r['status'] === 404) {
                return [];
            }
            if ($r['status'] === 429 || $r['status'] >= 500) {
                $ultimo = "respuesta {$r['status']}";
                continue;
            }
            if ($r['status'] !== 200) {
                throw new ServicioNoDisponibleException("La Central de Deudores rechazó la consulta (respuesta {$r['status']}).");
            }
            // Los montos se leen como texto para no operar importes con punto flotante
            $cuerpo = preg_replace('/"monto"\s*:\s*(-?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?)/', '"monto":"$1"', $r['body']) ?? '';
            $json = json_decode($cuerpo, true);
            if (!is_array($json) || !is_array($json['results'] ?? null)) {
                throw new ServicioNoDisponibleException('La Central de Deudores devolvió una respuesta no válida.');
            }

            return array_values(array_filter((array) ($json['results']['periodos'] ?? []), 'is_array'));
        }

        throw new ServicioNoDisponibleException("La Central de Deudores no respondió después de {$this->reintentos} reintentos ({$ultimo}).");
    }

    private function registrar(string $operacion, string $codigo, int|float $inicio, int $intento): void
    {
        $this->intercambios[] = ['operacion' => $operacion, 'codigo' => $codigo, 'duracion_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000), 'intento' => $intento];
    }

    /** Miles de pesos (texto decimal, sin punto flotante) a pesos con dos decimales. */
    public static function milesAPesos(string $miles): string
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $miles, $m) !== 1) {
            return '0.00';
        }
        $dec = str_pad($m[2] ?? '', 5, '0');
        $entero = ltrim($m[1] . substr($dec, 0, 3), '0');

        return ($entero === '' ? '0' : $entero) . '.' . substr($dec, 3, 2);
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
