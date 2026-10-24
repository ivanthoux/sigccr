<?php

declare(strict_types=1);

namespace App\Adapters\Pasarela;

/**
 * Simulador de la pasarela para desarrollo y pruebas. Los pagos simulados se registran en
 * writable/simulador_pasarela.json, para poder provocar notificaciones repetidas, tardías o con importe distinto.
 */
class SimuladorPasarela implements PasarelaInterface
{
    private string $archivo;

    public function __construct(?string $archivo = null)
    {
        $this->archivo = $archivo ?? WRITEPATH . 'simulador_pasarela.json';
    }

    public function crearOrden(string $numeroOrden, string $importe, string $descripcion, \DateTimeImmutable $vence): array
    {
        $ref = 'SIM-' . $numeroOrden;

        return ['referencia_externa' => $ref, 'url_pago' => site_url('simulador/pagar/' . $ref)];
    }

    public function consultarPagos(string $referenciaExterna): array
    {
        $datos = is_file($this->archivo) ? json_decode((string) file_get_contents($this->archivo), true) : [];

        return array_values($datos[$referenciaExterna] ?? []);
    }

    public function firmaValida(string $cuerpo, array $cabeceras): bool
    {
        $secreto = config('Sigccr')->pasarelaSecretoNotificaciones ?: 'simulador';
        $firma = $cabeceras['x-signature'] ?? '';

        return hash_equals(hash_hmac('sha256', $cuerpo, $secreto), (string) $firma);
    }
}
