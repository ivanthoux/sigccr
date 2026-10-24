<?php

declare(strict_types=1);

namespace App\Adapters\Comunicaciones;

/**
 * Simulador del servicio de comunicaciones: guarda cada mensaje en writable/correos/ y permite provocar fallos
 * según el destinatario (falla.transitoria@…, falla.definitiva@…, incierto@…).
 */
class SimuladorComunicaciones implements ComunicacionesInterface
{
    public function enviar(string $destino, string $asunto, string $cuerpoHtml, string $identificadorOperacion): array
    {
        $usuario = strtolower(strtok($destino, '@') ?: '');
        $resultado = match ($usuario) {
            'falla.transitoria' => self::FALLO_TRANSITORIO,
            'falla.definitiva' => self::FALLO_DEFINITIVO,
            'incierto' => self::INCIERTO,
            default => self::ACEPTADO,
        };
        if ($resultado === self::ACEPTADO) {
            $dir = WRITEPATH . 'correos/';
            is_dir($dir) || mkdir($dir, 0775, true);
            file_put_contents($dir . $identificadorOperacion . '.html', "<!-- Para: {$destino} | Asunto: {$asunto} -->\n" . $cuerpoHtml);
        }

        return ['resultado' => $resultado, 'id_proveedor' => $resultado === self::ACEPTADO ? 'SIM-' . $identificadorOperacion : null, 'detalle' => null];
    }
}
