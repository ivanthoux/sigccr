<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

/**
 * Datos que el alta del cliente propone a partir del CUIT o CUIL (CU-11 A9, D-60).
 *
 * - Persona humana (prefijos 20, 23, 24 y 27): el DNI son los ocho dígitos centrales del CUIL, sin los ceros de la
 *   izquierda, y se completa aunque el servicio no encuentre a la persona. De la denominación («PEREZ GOMEZ ANA
 *   MARIA») la primera palabra se propone como apellido y el resto como nombre, con mayúscula inicial: el servicio no
 *   separa el apellido del nombre, así que un apellido compuesto lo corrige el Analista.
 * - Persona jurídica (prefijos 30, 33 y 34): la denominación es la razón social, tal como la informa el servicio.
 *
 * Nada más se propone: el domicilio, el empleo y los demás datos los completa el Analista.
 */
final class DatosPadron
{
    private const PERSONA_HUMANA = ['20', '23', '24', '27'];

    public static function esPersonaHumana(string $cuit): bool
    {
        return in_array(substr($cuit, 0, 2), self::PERSONA_HUMANA, true);
    }

    /** DNI contenido en el CUIL de una persona humana. */
    public static function dniDelCuil(string $cuil): string
    {
        $dni = ltrim(substr($cuil, 2, 8), '0');

        return $dni === '' ? '0' : $dni;
    }

    /**
     * @param string|null $denominacion la del servicio, o null si no encontró a la persona
     * @return array{tipo_cliente: string, tipo_documento?: string, nro_documento?: string, nombre?: string, apellido?: string, razon_social?: string}
     */
    public static function desde(string $cuit, ?string $denominacion): array
    {
        if (!self::esPersonaHumana($cuit)) {
            return ['tipo_cliente' => 'PersonaJuridica'] + ($denominacion === null ? [] : ['razon_social' => trim($denominacion)]);
        }
        $datos = ['tipo_cliente' => 'PersonaHumana', 'tipo_documento' => 'DNI', 'nro_documento' => self::dniDelCuil($cuit)];
        if ($denominacion === null) {
            return $datos;
        }
        $palabras = preg_split('/\s+/u', trim($denominacion), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $apellido = array_shift($palabras) ?? '';

        return $datos + ['apellido' => self::capitalizar($apellido), 'nombre' => self::capitalizar(implode(' ', $palabras))];
    }

    /** «PEREZ» → «Perez»; «DE LA FUENTE» → «De La Fuente». */
    private static function capitalizar(string $texto): string
    {
        return mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE, 'UTF-8');
    }
}
