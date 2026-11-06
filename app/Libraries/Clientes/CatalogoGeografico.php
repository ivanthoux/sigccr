<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

use InvalidArgumentException;

/**
 * Catálogo geográfico del domicilio (D-18): provincias con su código INDEC, localidades base de la instalación y
 * lectura del catálogo oficial de localidades censales del Servicio de Normalización de Datos Geográficos (georef).
 */
final class CatalogoGeografico
{
    /** Código INDEC => nombre. */
    public const PROVINCIAS = [
        2 => 'Ciudad Autónoma de Buenos Aires', 6 => 'Buenos Aires', 10 => 'Catamarca', 14 => 'Córdoba', 18 => 'Corrientes',
        22 => 'Chaco', 26 => 'Chubut', 30 => 'Entre Ríos', 34 => 'Formosa', 38 => 'Jujuy', 42 => 'La Pampa', 46 => 'La Rioja',
        50 => 'Mendoza', 54 => 'Misiones', 58 => 'Neuquén', 62 => 'Río Negro', 66 => 'Salta', 70 => 'San Juan', 74 => 'San Luis',
        78 => 'Santa Cruz', 82 => 'Santa Fe', 86 => 'Santiago del Estero', 90 => 'Tucumán',
        94 => 'Tierra del Fuego, Antártida e Islas del Atlántico Sur',
    ];

    /**
     * Localidades que se cargan con la instalación, para operar sin el catálogo completo: las capitales y las de la
     * zona de Apóstoles (Misiones) y del Gran Resistencia (Chaco). [provincia, nombre, departamento].
     */
    public const LOCALIDADES_BASE = [
        [2, 'Ciudad Autónoma de Buenos Aires', null], [6, 'La Plata', 'La Plata'], [10, 'San Fernando del Valle de Catamarca', 'Capital'],
        [14, 'Córdoba', 'Capital'], [18, 'Corrientes', 'Capital'], [22, 'Resistencia', 'San Fernando'], [26, 'Rawson', 'Rawson'],
        [30, 'Paraná', 'Paraná'], [34, 'Formosa', 'Formosa'], [38, 'San Salvador de Jujuy', 'Doctor Manuel Belgrano'],
        [42, 'Santa Rosa', 'Capital'], [46, 'La Rioja', 'Capital'], [50, 'Mendoza', 'Capital'], [54, 'Posadas', 'Capital'],
        [58, 'Neuquén', 'Confluencia'], [62, 'Viedma', 'Adolfo Alsina'], [66, 'Salta', 'Capital'], [70, 'San Juan', 'Capital'],
        [74, 'San Luis', 'Juan Martín de Pueyrredón'], [78, 'Río Gallegos', 'Güer Aike'], [82, 'Santa Fe', 'La Capital'],
        [86, 'Santiago del Estero', 'Capital'], [90, 'San Miguel de Tucumán', 'Capital'], [94, 'Ushuaia', 'Ushuaia'],
        [54, 'Apóstoles', 'Apóstoles'], [54, 'Azara', 'Apóstoles'], [54, 'San José', 'Apóstoles'], [54, 'Tres Capones', 'Apóstoles'],
        [54, 'Concepción de la Sierra', 'Concepción'], [54, 'Leandro N. Alem', 'Leandro N. Alem'], [54, 'Oberá', 'Oberá'],
        [18, 'Colonia Liebig', 'Ituzaingó'], [18, 'Gobernador Ingeniero Valentín Virasoro', 'Santo Tomé'], [18, 'Santo Tomé', 'Santo Tomé'],
        [22, 'Barranqueras', 'San Fernando'], [22, 'Fontana', 'San Fernando'], [22, 'Puerto Vilelas', 'San Fernando'],
    ];

    private const MINUSCULAS = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e'];

    /**
     * Lee el catálogo de localidades censales de georef en JSON (lista, u objeto con la lista en alguna clave) o en
     * NDJSON (un objeto por línea). Cada elemento tiene id, nombre, provincia {id, nombre} y departamento {nombre}.
     *
     * @return list<array{codigo: string, nombre: string, provincia_id: int, departamento: ?string}>
     */
    public static function leer(string $contenido): array
    {
        $contenido = ltrim($contenido, "\xEF\xBB\xBF \n\r\t");
        $datos = json_decode($contenido, true);
        if (!is_array($datos)) {
            $datos = [];
            foreach (preg_split('/\R/', $contenido) ?: [] as $linea) {
                $fila = json_decode(trim($linea), true);
                if (is_array($fila)) {
                    $datos[] = $fila;
                }
            }
        } elseif (!array_is_list($datos)) {
            $lista = array_values(array_filter($datos, static fn ($v) => is_array($v) && array_is_list($v) && $v !== []));
            $datos = $lista[0] ?? [];
        }
        if ($datos === []) {
            throw new InvalidArgumentException('El archivo no contiene localidades en el formato de georef.');
        }
        $r = [];
        foreach ($datos as $l) {
            $provincia = (int) ($l['provincia']['id'] ?? 0);
            $nombre = trim((string) ($l['nombre'] ?? ''));
            if (!isset(self::PROVINCIAS[$provincia]) || $nombre === '') {
                continue;
            }
            $departamento = trim((string) ($l['departamento']['nombre'] ?? ''));
            $r[] = [
                'codigo' => mb_substr(trim((string) ($l['id'] ?? '')), 0, 12),
                'nombre' => mb_substr(self::nombrePropio($nombre), 0, 150),
                'provincia_id' => $provincia,
                'departamento' => $departamento === '' ? null : mb_substr(self::nombrePropio($departamento), 0, 100),
            ];
        }

        return $r;
    }

    /** Nombres en mayúsculas (como los publica parte del catálogo) a nombre propio: «SAN JOSE DE LA ESQUINA» → «San Jose de la Esquina». */
    public static function nombrePropio(string $nombre): string
    {
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre)) ?? $nombre;
        if ($nombre !== mb_strtoupper($nombre)) {
            return $nombre;
        }
        $palabras = explode(' ', mb_strtolower($nombre));
        foreach ($palabras as $i => $p) {
            if ($i === 0 || !in_array($p, self::MINUSCULAS, true)) {
                $palabras[$i] = mb_convert_case($p, MB_CASE_TITLE);
            }
        }

        return implode(' ', $palabras);
    }

    /** Clave de comparación sin mayúsculas, tildes ni signos, para reconocer una localidad ya cargada. */
    public static function clave(string $nombre): string
    {
        $t = mb_strtolower(trim($nombre));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $t));
    }

    /** Código postal: cuatro dígitos o CPA (letra de provincia, cuatro dígitos y tres letras). Normalizado en mayúsculas. */
    public static function codigoPostal(string $valor): ?string
    {
        $v = strtoupper(str_replace([' ', '-'], '', trim($valor)));

        return preg_match('/^(\d{4}|[A-HJ-NP-Z]\d{4}[A-Z]{3})$/', $v) === 1 ? $v : null;
    }
}
