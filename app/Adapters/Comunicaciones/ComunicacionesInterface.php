<?php

declare(strict_types=1);

namespace App\Adapters\Comunicaciones;

/**
 * Servicio de comunicaciones (correo transaccional). RNF-09: espera máxima de 10 s; distingue la aceptación del
 * proveedor de la entrega y los fallos transitorios de los definitivos.
 */
interface ComunicacionesInterface
{
    public const ACEPTADO = 'Aceptado';
    public const FALLO_TRANSITORIO = 'FalloTransitorio';
    public const FALLO_DEFINITIVO = 'FalloDefinitivo';
    public const INCIERTO = 'Incierto';

    /**
     * @param string $identificadorOperacion se envía al proveedor para evitar duplicados en los reintentos
     *
     * @return array{resultado: string, id_proveedor: ?string, detalle: ?string}
     */
    public function enviar(string $destino, string $asunto, string $cuerpoHtml, string $identificadorOperacion): array;
}
