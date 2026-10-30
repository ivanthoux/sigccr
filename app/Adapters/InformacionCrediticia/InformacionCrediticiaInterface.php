<?php

declare(strict_types=1);

namespace App\Adapters\InformacionCrediticia;

/**
 * Servicio externo de información crediticia (Central de Deudores del BCRA). RNF-05, RNF-08.
 * Las implementaciones aplican la espera máxima de 10 s y hasta dos reintentos; agotados, lanzan
 * ServicioNoDisponibleException y la evaluación queda No completada (CU-86).
 */
interface InformacionCrediticiaInterface
{
    /**
     * @return list<array{entidad: string, periodo: string, situacion: int, monto: string}> deudas de los últimos 12 meses
     *
     * @throws \App\Adapters\ServicioNoDisponibleException
     */
    public function consultar(string $cuitCuil): array;

    /**
     * Peticiones de la última consulta con su resultado y duración, para intercambios_externos (RNF-08). No incluyen
     * datos del cliente.
     *
     * @return list<array{operacion: string, codigo: string, duracion_ms: int, intento: int}>
     */
    public function intercambios(): array;
}
