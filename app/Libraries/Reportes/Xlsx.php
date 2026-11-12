<?php

declare(strict_types=1);

namespace App\Libraries\Reportes;

use RuntimeException;
use ZipArchive;

/**
 * Exportación de un reporte a Excel (.xlsx, Office Open XML) con la extensión zip de PHP, sin dependencias (D-44).
 * Cada hoja es una tabla: la primera fila es el encabezado (en negrita); los números se guardan como números.
 */
final class Xlsx
{
    /**
     * @param array<string, list<list<string|int|float|null>>> $hojas nombre => filas
     * @return string contenido del archivo
     */
    public static function generar(array $hojas): string
    {
        if ($hojas === []) {
            $hojas = ['Reporte' => [['Sin datos']]];
        }
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo generar el archivo Excel.');
        }
        $nombres = [];
        $i = 0;
        foreach ($hojas as $nombre => $filas) {
            $i++;
            $nombres[$i] = self::nombreHoja((string) $nombre, $nombres);
            $zip->addFromString("xl/worksheets/sheet{$i}.xml", self::hoja($filas));
        }
        $zip->addFromString('[Content_Types].xml', self::tipos(count($nombres)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', self::libro($nombres));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relaciones(count($nombres)));
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();
        $contenido = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    /** @param list<list<string|int|float|null>> $filas */
    private static function hoja(array $filas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_values($filas) as $r => $fila) {
            $n = $r + 1;
            $xml .= "<row r=\"{$n}\">";
            foreach (array_values($fila) as $c => $valor) {
                $ref = self::columna($c) . $n;
                if ($valor === null || $valor === '') {
                    continue;
                }
                if ((is_int($valor) || is_float($valor)) && $r > 0) {
                    $estilo = is_float($valor) ? ' s="2"' : '';
                    $xml .= "<c r=\"{$ref}\"{$estilo}><v>" . $valor . '</v></c>';
                } else {
                    $estilo = $r === 0 ? ' s="1"' : '';
                    $xml .= "<c r=\"{$ref}\" t=\"inlineStr\"{$estilo}><is><t xml:space=\"preserve\">" . self::texto((string) $valor) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    public static function columna(int $indice): string
    {
        $s = '';
        for ($i = $indice + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    private static function texto(string $v): string
    {
        // Se quitan los caracteres de control que XML no admite
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param array<int, string> $usados */
    private static function nombreHoja(string $nombre, array $usados): string
    {
        $base = mb_substr(trim((string) preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $nombre)) ?: 'Hoja', 0, 28);
        $candidato = $base;
        for ($n = 2; in_array($candidato, $usados, true); $n++) {
            $candidato = $base . ' ' . $n;
        }

        return $candidato;
    }

    private static function tipos(int $hojas): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $hojas; $i++) {
            $x .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return $x . '</Types>';
    }

    /** @param array<int, string> $nombres */
    private static function libro(array $nombres): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($nombres as $i => $n) {
            $x .= '<sheet name="' . self::texto($n) . "\" sheetId=\"{$i}\" r:id=\"rId{$i}\"/>";
        }

        return $x . '</sheets></workbook>';
    }

    private static function relaciones(int $hojas): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $hojas; $i++) {
            $x .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        }
        $n = $hojas + 1;

        return $x . "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/></Relationships>";
    }
}
