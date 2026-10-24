<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

/**
 * Reglas de los archivos del legajo (RNF-12): PDF, JPEG o PNG según su contenido real, hasta 5 MB. Se guardan fuera
 * del directorio público con un nombre aleatorio; el nombre original solo se conserva como dato.
 */
final class ArchivoLegajo
{
    public const TAMANO_MAXIMO = 5 * 1024 * 1024;
    public const EXTENSIONES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** @return string|null mensaje de error, o null si el archivo es aceptable */
    public static function validar(string $nombreOriginal, int $tamano, string $tipoReal): ?string
    {
        if ($tamano <= 0) {
            return "El archivo {$nombreOriginal} está vacío.";
        }
        if ($tamano > self::TAMANO_MAXIMO) {
            return "El archivo {$nombreOriginal} supera los 5 MB.";
        }
        if (!isset(self::EXTENSIONES[$tipoReal])) {
            return "El archivo {$nombreOriginal} no es PDF, JPG ni PNG.";
        }

        return null;
    }

    /** Nombre aleatorio con la extensión que corresponde al contenido real. */
    public static function nombreAleatorio(string $tipoReal): string
    {
        return bin2hex(random_bytes(16)) . '.' . self::EXTENSIONES[$tipoReal];
    }

    /** Nombre original sin rutas ni caracteres de control, para guardarlo y mostrarlo. */
    public static function nombreSeguro(string $nombre): string
    {
        $base = basename(str_replace('\\', '/', $nombre));
        $base = preg_replace('/[\x00-\x1F\x7F]/u', '', $base) ?? '';

        return mb_substr($base === '' ? 'documento' : $base, 0, 255);
    }
}
