<?php

declare(strict_types=1);

namespace App\Services\Clientes;

use App\Libraries\Clientes\ArchivoLegajo;
use App\Services\BaseService;

/**
 * Legajo digital del cliente (RN-11, RN-44, RNF-12): validación de los archivos por su tipo real y su tamaño, guardado
 * fuera del directorio público con nombre aleatorio y documentos vigentes por tipo. Lo usan el alta y la modificación
 * del cliente (CU-11, CU-12), el registro de la solicitud (CU-22), su verificación (CU-23 A4) y sus correcciones (CU-84).
 *
 * Las operaciones que guardan archivos deben eliminarlos si su transacción se revierte (descartar()).
 */
class LegajoService extends BaseService
{
    /** Carpeta de los legajos dentro de writable/. */
    public const CARPETA = 'legajos';

    /** @return list<array{id: int, codigo: string, nombre: string, aplica_a: string, es_clave: int}> */
    public function tiposDocumentacion(): array
    {
        return $this->db->table('tipos_documentacion')->select('id, codigo, nombre, aplica_a, es_clave')->where('activo', 1)->orderBy('id')->get()->getResultArray();
    }

    /**
     * Documentos vigentes del legajo: los que no fueron reemplazados por otro (RN-44).
     *
     * @return list<array<string, mixed>>
     */
    public function vigentes(int $clienteId): array
    {
        return $this->db->query(
            'SELECT d.id, d.nombre_original, d.tipo_mime, d.tamano_bytes, d.fecha_incorporacion, td.codigo, td.nombre AS tipo, td.es_clave '
            . 'FROM dbo.documentos_legajo d JOIN dbo.tipos_documentacion td ON td.id = d.tipo_documentacion_id '
            . 'WHERE d.cliente_id = ? AND NOT EXISTS (SELECT 1 FROM dbo.documentos_legajo r WHERE r.reemplaza_documento_id = d.id) '
            . 'ORDER BY td.id, d.fecha_incorporacion DESC',
            [$clienteId],
        )->getResultArray();
    }

    /**
     * Documentos vigentes agrupados por código de tipo, para la verificación (CU-23).
     *
     * @return array<string, list<int>>
     */
    public function vigentesPorTipo(int $clienteId): array
    {
        $r = [];
        foreach ($this->vigentes($clienteId) as $d) {
            $r[(string) $d['codigo']][] = (int) $d['id'];
        }

        return $r;
    }

    /**
     * Valida los archivos recibidos (tipo real, extensión y tamaño). Los errores se agregan por campo doc_<código>.
     *
     * @param list<array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza?: ?int}> $archivos
     * @param array<string, string> $errores
     * @return list<array{codigo: string, nombre: string, temporal: string, tamano: int, mime: string, tipo_id: int, es_clave: int, reemplaza?: ?int}>
     */
    public function validarArchivos(array $archivos, array &$errores): array
    {
        $tipos = [];
        foreach ($this->tiposDocumentacion() as $t) {
            $tipos[$t['codigo']] = $t;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $validos = [];
        foreach ($archivos as $a) {
            if (!isset($tipos[$a['codigo']])) {
                continue;
            }
            $mime = is_file($a['temporal']) ? (string) $finfo->file($a['temporal']) : '';
            $error = ArchivoLegajo::validar(ArchivoLegajo::nombreSeguro($a['nombre']), $a['tamano'], $mime);
            if ($error !== null) {
                $errores['doc_' . $a['codigo']] = $error;
                continue;
            }
            $validos[] = $a + ['mime' => $mime, 'tipo_id' => (int) $tipos[$a['codigo']]['id'], 'es_clave' => (int) $tipos[$a['codigo']]['es_clave']];
        }

        return $validos;
    }

    /**
     * Mueve el archivo al legajo con nombre aleatorio y registra el documento; si reemplaza a otro del mismo cliente
     * y tipo, lo referencia (RN-44). Debe llamarse dentro de la transacción de la operación.
     *
     * @param array<string, mixed> $doc archivo validado por validarArchivos()
     * @return array{id: int, ruta: string}
     */
    public function guardar(int $clienteId, array $doc, int $usuarioId): array
    {
        $carpeta = self::CARPETA . '/' . $clienteId;
        if (!is_dir(WRITEPATH . $carpeta) && !mkdir(WRITEPATH . $carpeta, 0750, true) && !is_dir(WRITEPATH . $carpeta)) {
            throw new \RuntimeException("No se pudo crear la carpeta del legajo {$carpeta}");
        }
        $reemplaza = null;
        if (($doc['reemplaza'] ?? null) !== null) {
            $previo = $this->db->table('documentos_legajo')->select('id')->where('id', (int) $doc['reemplaza'])
                ->where('cliente_id', $clienteId)->where('tipo_documentacion_id', $doc['tipo_id'])->get()->getRowArray();
            $reemplaza = $previo === null ? null : (int) $previo['id'];
        }
        $ruta = $carpeta . '/' . ArchivoLegajo::nombreAleatorio($doc['mime']);
        $hash = hash_file('sha256', $doc['temporal']);
        if (!rename($doc['temporal'], WRITEPATH . $ruta)) {
            throw new \RuntimeException('No se pudo guardar el archivo del legajo.');
        }
        $this->db->table('documentos_legajo')->insert([
            'cliente_id' => $clienteId, 'tipo_documentacion_id' => $doc['tipo_id'], 'nombre_original' => ArchivoLegajo::nombreSeguro($doc['nombre']),
            'ruta_archivo' => $ruta, 'tipo_mime' => $doc['mime'], 'tamano_bytes' => $doc['tamano'], 'hash_sha256' => $hash,
            'fecha_incorporacion' => $this->ahoraUtc(), 'reemplaza_documento_id' => $reemplaza, 'usuario_id' => $usuarioId,
        ]);

        return ['id' => (int) $this->db->insertID(), 'ruta' => $ruta];
    }

    /**
     * Elimina los archivos ya movidos cuando la transacción que los registraba se revirtió.
     *
     * @param list<string> $rutas
     */
    public static function descartar(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            if (is_file(WRITEPATH . $ruta)) {
                unlink(WRITEPATH . $ruta);
            }
        }
    }
}
