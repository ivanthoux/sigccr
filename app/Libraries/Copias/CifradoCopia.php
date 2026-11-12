<?php

declare(strict_types=1);

namespace App\Libraries\Copias;

use App\Exceptions\CopiaCorruptaException;
use RuntimeException;

/**
 * Cifrado de las copias de seguridad (RNF-13, D-45) con AES-256-GCM por bloques, para no cargar en memoria una copia
 * grande. Formato: cabecera «SIGCCR-COPIA» + versión, y por cada bloque un indicador de último bloque (1 byte), la
 * longitud del texto (4 bytes), el vector de inicialización (12 bytes), el texto cifrado y la etiqueta (16 bytes).
 * El número de bloque y el indicador forman parte de los datos autenticados, de modo que un archivo truncado,
 * reordenado o alterado no se descifra.
 */
final class CifradoCopia
{
    public const CABECERA = "SIGCCR-COPIA\x01";
    public const BLOQUE = 1048576;
    private const ALGORITMO = 'aes-256-gcm';

    /** Clave de las copias derivada de encryption.key, independiente de la que cifra los datos de la aplicación. */
    public static function clave(string $claveAplicacion): string
    {
        if (str_starts_with($claveAplicacion, 'hex2bin:')) {
            $claveAplicacion = (string) hex2bin(substr($claveAplicacion, 8));
        } elseif (str_starts_with($claveAplicacion, 'base64:')) {
            $claveAplicacion = (string) base64_decode(substr($claveAplicacion, 7), true);
        }
        if (strlen($claveAplicacion) < 16) {
            throw new RuntimeException('La clave de cifrado (encryption.key) no está configurada.');
        }

        return hash_hkdf('sha256', $claveAplicacion, 32, 'sigccr-copias-v1');
    }

    public static function cifrar(string $origen, string $destino, string $clave): void
    {
        [$in, $out] = self::abrir($origen, $destino);
        try {
            fwrite($out, self::CABECERA);
            $indice = 0;
            $actual = (string) fread($in, self::BLOQUE);
            do {
                $siguiente = feof($in) ? '' : (string) fread($in, self::BLOQUE);
                $ultimo = $siguiente === '' && feof($in);
                $iv = random_bytes(12);
                $tag = '';
                $cifrado = openssl_encrypt($actual, self::ALGORITMO, $clave, OPENSSL_RAW_DATA, $iv, $tag, self::aad($indice, $ultimo));
                if ($cifrado === false) {
                    throw new RuntimeException('No se pudo cifrar la copia.');
                }
                fwrite($out, ($ultimo ? "\x01" : "\x00") . pack('N', strlen($actual)) . $iv . $cifrado . $tag);
                $actual = $siguiente;
                $indice++;
            } while (!$ultimo);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** @throws CopiaCorruptaException si el archivo no es una copia del sistema, está truncado o fue alterado */
    public static function descifrar(string $origen, string $destino, string $clave): void
    {
        [$in, $out] = self::abrir($origen, $destino);
        try {
            if (fread($in, strlen(self::CABECERA)) !== self::CABECERA) {
                throw new CopiaCorruptaException('El archivo no es una copia de seguridad del SIG-CCR.');
            }
            $indice = 0;
            do {
                $encabezado = fread($in, 17);
                if ($encabezado === false || strlen($encabezado) !== 17) {
                    throw new CopiaCorruptaException('La copia está incompleta.');
                }
                $ultimo = $encabezado[0] === "\x01";
                $largo = unpack('N', substr($encabezado, 1, 4))[1];
                if ($largo > self::BLOQUE) {
                    throw new CopiaCorruptaException('La copia está dañada.');
                }
                $datos = (string) fread($in, $largo + 16);
                if (strlen($datos) !== $largo + 16) {
                    throw new CopiaCorruptaException('La copia está incompleta.');
                }
                $plano = openssl_decrypt(substr($datos, 0, $largo), self::ALGORITMO, $clave, OPENSSL_RAW_DATA, substr($encabezado, 5, 12), substr($datos, $largo), self::aad($indice, $ultimo));
                if ($plano === false) {
                    throw new CopiaCorruptaException('La copia fue alterada o se cifró con otra clave.');
                }
                fwrite($out, $plano);
                $indice++;
            } while (!$ultimo);
            if (fread($in, 1) !== '') {
                throw new CopiaCorruptaException('La copia tiene datos después del último bloque.');
            }
        } catch (CopiaCorruptaException $e) {
            fclose($out);
            $out = null;
            @unlink($destino);
            throw $e;
        } finally {
            fclose($in);
            if ($out !== null) {
                fclose($out);
            }
        }
    }

    private static function aad(int $indice, bool $ultimo): string
    {
        return self::CABECERA . pack('J', $indice) . ($ultimo ? "\x01" : "\x00");
    }

    /** @return array{0: resource, 1: resource} */
    private static function abrir(string $origen, string $destino): array
    {
        $in = @fopen($origen, 'rb');
        if ($in === false) {
            throw new RuntimeException("No se pudo leer {$origen}.");
        }
        $out = @fopen($destino, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("No se pudo escribir {$destino}.");
        }

        return [$in, $out];
    }
}
