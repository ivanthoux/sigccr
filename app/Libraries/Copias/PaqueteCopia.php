<?php

declare(strict_types=1);

namespace App\Libraries\Copias;

use App\Exceptions\CopiaCorruptaException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Contenido de una copia antes de cifrarla (CU-61 paso 5): la copia de la base (sigccr.bak) y, si está configurado,
 * los archivos del legajo bajo adjuntos/. Se empaqueta en un zip sin compresión, porque el cifrado lo hace ilegible.
 */
final class PaqueteCopia
{
    public const BASE = 'sigccr.bak';
    private const ADJUNTOS = 'adjuntos/';

    /** @return int cantidad de adjuntos incluidos */
    public static function armar(string $bak, ?string $dirAdjuntos, string $destino): int
    {
        $zip = new ZipArchive();
        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el paquete de la copia.');
        }
        $zip->addFile($bak, self::BASE);
        $zip->setCompressionName(self::BASE, ZipArchive::CM_STORE);
        $n = 0;
        if ($dirAdjuntos !== null && is_dir($dirAdjuntos)) {
            $base = rtrim($dirAdjuntos, '/') . '/';
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $nombre = self::ADJUNTOS . substr($f->getPathname(), strlen($base));
                    $zip->addFile($f->getPathname(), $nombre);
                    $zip->setCompressionName($nombre, ZipArchive::CM_STORE);
                    $n++;
                }
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('No se pudo cerrar el paquete de la copia.');
        }

        return $n;
    }

    /**
     * Extrae la base en $destinoBak y, si se indica la carpeta, agrega o reemplaza los adjuntos (no borra los
     * posteriores a la copia).
     *
     * @return int cantidad de adjuntos restaurados
     */
    public static function extraer(string $paquete, string $destinoBak, ?string $dirAdjuntos): int
    {
        $zip = new ZipArchive();
        if ($zip->open($paquete) !== true || $zip->locateName(self::BASE) === false) {
            throw new CopiaCorruptaException('La copia no contiene la base de datos.');
        }
        $in = $zip->getStream(self::BASE);
        $out = fopen($destinoBak, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('No se pudo extraer la base de la copia.');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $n = 0;
        if ($dirAdjuntos !== null) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = (string) $zip->getNameIndex($i);
                if (!str_starts_with($nombre, self::ADJUNTOS) || str_ends_with($nombre, '/')) {
                    continue;
                }
                $relativo = substr($nombre, strlen(self::ADJUNTOS));
                if ($relativo === '' || str_contains($relativo, '..') || str_starts_with($relativo, '/')) {
                    throw new CopiaCorruptaException('La copia contiene una ruta de adjunto inválida.');
                }
                $ruta = rtrim($dirAdjuntos, '/') . '/' . $relativo;
                if (!is_dir(dirname($ruta))) {
                    mkdir(dirname($ruta), 0750, true);
                }
                $s = $zip->getStream($nombre);
                $d = fopen($ruta, 'wb');
                if ($s === false || $d === false) {
                    throw new RuntimeException("No se pudo restaurar el adjunto {$relativo}.");
                }
                stream_copy_to_stream($s, $d);
                fclose($s);
                fclose($d);
                $n++;
            }
        }
        $zip->close();

        return $n;
    }
}
