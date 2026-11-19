<?php

declare(strict_types=1);

namespace App\Libraries\Comunicaciones;

use App\Libraries\Copias\CifradoCopia;
use RuntimeException;

/**
 * Cifrado de los datos de las comunicaciones pendientes (RNF-13; OWASP ASVS 5.0 V11.3.2) con AES-256-GCM, un modo
 * autenticado: el apéndice C de ASVS 5 no admite CTR, que es el que trae CodeIgniter por defecto.
 *
 * Formato: «SCD1» + vector de inicialización (12 bytes) + etiqueta (16 bytes) + texto cifrado. La clave se deriva de
 * encryption.key con HKDF y es distinta de la que cifra las copias. Lo cifrado antes de este cambio, con el cifrador
 * de CodeIgniter, no lleva la marca: ComunicacionService lo sigue descifrando con ese cifrador hasta que se envíe.
 */
final class CifradoDatos
{
    public const MARCA = 'SCD1';
    private const ALGORITMO = 'aes-256-gcm';

    public static function cifrar(string $texto, string $claveAplicacion): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($texto, self::ALGORITMO, self::clave($claveAplicacion), OPENSSL_RAW_DATA, $iv, $tag, self::MARCA);
        if ($cifrado === false) {
            throw new RuntimeException('No se pudo cifrar el dato.');
        }

        return self::MARCA . $iv . $tag . $cifrado;
    }

    public static function lleva(string $binario): bool
    {
        return str_starts_with($binario, self::MARCA);
    }

    /** @throws RuntimeException si el dato fue alterado o la clave no es la que lo cifró */
    public static function descifrar(string $binario, string $claveAplicacion): string
    {
        if (!self::lleva($binario) || strlen($binario) < 4 + 12 + 16) {
            throw new RuntimeException('El dato cifrado no tiene el formato esperado.');
        }
        $iv = substr($binario, 4, 12);
        $tag = substr($binario, 16, 16);
        $texto = openssl_decrypt(substr($binario, 32), self::ALGORITMO, self::clave($claveAplicacion), OPENSSL_RAW_DATA, $iv, $tag, self::MARCA);
        if ($texto === false) {
            throw new RuntimeException('El dato cifrado fue alterado o la clave no coincide.');
        }

        return $texto;
    }

    private static function clave(string $claveAplicacion): string
    {
        return CifradoCopia::derivar($claveAplicacion, 'sigccr-comunicaciones-v1');
    }
}
