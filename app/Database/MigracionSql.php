<?php

declare(strict_types=1);

namespace App\Database;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Migración que ejecuta un script SQL por lotes separados con GO.
 *
 * Las migraciones del esquema se generan desde la especificación del modelo de datos (4.3), de modo que el script de
 * referencia, el diccionario y la base coinciden. Se ejecutan con el propietario del esquema:
 *   php spark migrate --all -g migraciones
 */
abstract class MigracionSql extends Migration
{
    protected $DBGroup = 'migraciones';

    /** Prefijo del archivo en app/Database/Sql, sin el sufijo _subir.sql / _bajar.sql. */
    protected string $archivo = '';

    public function up(): void
    {
        $this->ejecutar($this->archivo . '_subir.sql');
    }

    public function down(): void
    {
        $this->ejecutar($this->archivo . '_bajar.sql');
    }

    private function ejecutar(string $nombre): void
    {
        $ruta = APPPATH . 'Database/Sql/' . $nombre;
        if (!is_file($ruta)) {
            throw new RuntimeException("No se encontró el script {$ruta}.");
        }
        $lotes = preg_split('/^\s*GO\s*$/mi', (string) file_get_contents($ruta)) ?: [];
        foreach ($lotes as $lote) {
            if (trim(preg_replace('/^\s*--.*$/m', '', $lote) ?? '') === '') {
                continue;
            }
            $this->db->query($lote);
        }
    }
}
