<?php

declare(strict_types=1);

namespace App\Adapters\Padron;

/**
 * Servicio de padrón de ARCA (ACT-10): datos de identificación asociados a un CUIL, para proponerlos en el alta del
 * cliente (CU-11 A9) y al completar un pre-registro (CU-104). El Analista los confirma o corrige; si el servicio no
 * responde, los datos se ingresan manualmente (RNF-05, RNF-08).
 */
interface PadronInterface
{
    /**
     * @return array{nombre: string, apellido: string, tipo_documento: string, nro_documento: string, domicilio_fiscal: string}|null
     *         null si el padrón no tiene datos para ese CUIL
     * @throws \App\Adapters\ServicioNoDisponibleException
     */
    public function consultar(string $cuil): ?array;
}
