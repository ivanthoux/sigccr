<?php

declare(strict_types=1);

namespace App\Libraries\Copias;

use App\Exceptions\CopiaCorruptaException;
use RuntimeException;
use ZipArchive;

/**
 * Contenido de una copia antes de cifrarla (CU-61 paso 5): la copia de la base (sigccr.bak) y, si está configurado,
 * los archivos del legajo bajo adjuntos/. Se empaqueta en un zip sin compresión, porque el cifrado lo hace ilegible.
 *
 * Los adjuntos se identifican por las rutas que registra la base (documentos_legajo.ruta_archivo, relativas a
 * writable/), nunca por los nombres guardados en el paquete: al restaurar se escriben solo los que la base restaurada
 * referencia, con rutas validadas, lo que impide escribir fuera de la carpeta del legajo («zip slip»).
 */
final class PaqueteCopia
{
    public const BASE = 'sigccr.bak';
    private const ADJUNTOS = 'adjuntos/';

    /** Ruta de un archivo del legajo: legajos/<cliente>/<32 hexadecimales>.<extensión> (LegajoService, ArchivoLegajo). */
    public static function rutaValida(string $ruta): bool
    {
        return preg_match('#^legajos/\d{1,10}/[0-9a-f]{32}\.[a-z0-9]{2,5}$#', $ruta) === 1;
    }

    /**
     * @param list<string> $rutas rutas de los adjuntos relativas a $raiz
     * @return int cantidad de adjuntos incluidos
     */
    public static function armar(string $bak, ?string $raiz, array $rutas, string $destino): int
    {
        $zip = new ZipArchive();
        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el paquete de la copia.');
        }
        $zip->addFile($bak, self::BASE);
        $zip->setCompressionName(self::BASE, ZipArchive::CM_STORE);
        $n = 0;
        if ($raiz !== null) {
            foreach (array_unique($rutas) as $ruta) {
                $archivo = rtrim($raiz, '/') . '/' . $ruta;
                if (self::rutaValida($ruta) && is_file($archivo)) {
                    $zip->addFile($archivo, self::ADJUNTOS . $ruta);
                    $zip->setCompressionName(self::ADJUNTOS . $ruta, ZipArchive::CM_STORE);
                    $n++;
                }
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('No se pudo cerrar el paquete de la copia.');
        }

        return $n;
    }

    /** Extrae la copia de la base en $destinoBak. */
    public static function extraerBase(string $paquete, string $destinoBak): void
    {
        $zip = self::abrir($paquete);
        $in = $zip->getStream(self::BASE);
        $out = fopen($destinoBak, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('No se pudo extraer la base de la copia.');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $zip->close();
    }

    /**
     * Restaura los adjuntos que referencia la base restaurada: agrega o reemplaza, sin borrar los posteriores.
     *
     * @param list<string> $rutas rutas registradas en la base, relativas a $raiz
     * @return int cantidad de adjuntos restaurados
     */
    public static function extraerAdjuntos(string $paquete, string $raiz, array $rutas): int
    {
        $zip = self::abrir($paquete);
        $n = 0;
        foreach (array_unique($rutas) as $ruta) {
            if (!self::rutaValida($ruta) || $zip->locateName(self::ADJUNTOS . $ruta) === false) {
                continue;
            }
            [, $cliente, $nombre] = explode('/', $ruta);
            $carpeta = rtrim($raiz, '/') . '/legajos/' . (int) $cliente;
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0750, true);
            }
            $s = $zip->getStream(self::ADJUNTOS . $ruta);
            $d = fopen($carpeta . '/' . basename($nombre), 'wb');
            if ($s === false || $d === false) {
                throw new RuntimeException('No se pudo restaurar el adjunto ' . basename($nombre) . '.');
            }
            stream_copy_to_stream($s, $d);
            fclose($s);
            fclose($d);
            $n++;
        }
        $zip->close();

        return $n;
    }

    private static function abrir(string $paquete): ZipArchive
    {
        $zip = new ZipArchive();
        if ($zip->open($paquete) !== true || $zip->locateName(self::BASE) === false) {
            throw new CopiaCorruptaException('La copia no contiene la base de datos.');
        }

        return $zip;
    }
}
