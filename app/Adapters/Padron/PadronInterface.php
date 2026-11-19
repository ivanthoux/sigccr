<?php

declare(strict_types=1);

namespace App\Adapters\Padron;

/**
 * Consulta de la denominación asociada a un CUIT o CUIL, para proponer el nombre y el apellido o la razón social en el
 * alta del cliente (CU-11 A9, D-60). Solo se obtiene la denominación: el domicilio, el empleo y los demás datos los
 * completa el Analista. El número de documento no se consulta: sale del propio CUIL (DatosPadron).
 *
 * Implementaciones: la Central de Deudores del BCRA (API pública) y un simulador para desarrollo y pruebas (RNF-05).
 */
interface PadronInterface
{
    /**
     * @param string $cuit 11 dígitos, sin guiones
     * @return string|null la denominación tal como la informa el servicio («PEREZ GOMEZ ANA MARIA»), o null si el
     *                     servicio no tiene a esa persona
     * @throws \App\Adapters\ServicioNoDisponibleException si el servicio no responde o la respuesta no es válida
     */
    public function consultar(string $cuit): ?string;
}
