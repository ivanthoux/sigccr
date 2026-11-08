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

    /** @param array<string, string> $cabeceras */
    public function firmaValida(string $cuerpo, array $cabeceras): bool
    {
        $secreto = config('Sigccr')->pasarelaSecretoNotificaciones ?: 'simulador';
        $firma = $cabeceras['x-signature'] ?? '';

        return hash_equals(hash_hmac('sha256', $cuerpo, $secreto), (string) $firma);
    }

    /**
     * El resultado lo decide el modo configurado en la página del simulador (aprobada, rechazada, pendiente o sin
     * respuesta). Las devoluciones quedan en writable/simulador_devoluciones.json.
     */
    public function devolver(string $idTransaccion, string $importe, string $idOperacion): array
    {
        $datos = self::leerDevoluciones();
        if (isset($datos['devoluciones'][$idOperacion])) {
            return $datos['devoluciones'][$idOperacion];
        }
        $modo = (string) ($datos['modo'] ?? 'approved');
        if ($modo === 'timeout') {
            $datos['devoluciones'][$idOperacion] = ['estado' => 'in_process', 'id_devolucion' => null, 'detalle' => 'Sin respuesta (simulada)', 'transaccion' => $idTransaccion, 'importe' => $importe];
            self::guardarDevoluciones($datos);
            throw new \RuntimeException('La pasarela no respondió (simulado).');
        }
        $r = ['estado' => $modo, 'id_devolucion' => $modo === 'rejected' ? null : 'SIMREF-' . bin2hex(random_bytes(4)), 'detalle' => $modo === 'rejected' ? 'Rechazada por el emisor (simulado)' : null];
        $datos['devoluciones'][$idOperacion] = $r + ['transaccion' => $idTransaccion, 'importe' => $importe];
        self::guardarDevoluciones($datos);

        return $r;
    }

    public function consultarDevolucion(string $idOperacion): ?array
    {
        $d = self::leerDevoluciones()['devoluciones'][$idOperacion] ?? null;

        return $d === null ? null : ['estado' => (string) $d['estado'], 'id_devolucion' => $d['id_devolucion'] ?? null, 'detalle' => $d['detalle'] ?? null];
    }

    /** @return array<string, mixed> */
    public static function leerDevoluciones(): array
    {
        $archivo = WRITEPATH . 'simulador_devoluciones.json';
        $d = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : [];

        return is_array($d) ? $d : [];
    }

    /** @param array<string, mixed> $datos */
    public static function guardarDevoluciones(array $datos): void
    {
        file_put_contents(WRITEPATH . 'simulador_devoluciones.json', json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
