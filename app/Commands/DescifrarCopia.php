<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Copias\CifradoCopia;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Descifra una copia descargada (CU-62) para restaurarla fuera del sistema, por ejemplo en el entorno de prueba de la
 * restauración mensual (plan preventivo de R2). El resultado es un zip con sigccr.bak y la carpeta adjuntos/. Requiere
 * la misma encryption.key con la que se generó la copia.
 *
 *   php spark copias:descifrar <copia.sccr> <destino.zip>
 */
class DescifrarCopia extends BaseCommand
{
    protected $group = 'SIG-CCR';
    protected $name = 'copias:descifrar';
    protected $description = 'Descifra una copia de seguridad descargada (CU-62).';
    protected $usage = 'copias:descifrar <copia.sccr> <destino.zip>';

    public function run(array $params): void
    {
        // spark cambia el directorio de trabajo a public/: las rutas relativas se toman desde la raíz del proyecto
        $absoluta = static fn (string $r): string => $r === '' || preg_match('#^([A-Za-z]:)?[\\\\/]#', $r) === 1 ? $r : ROOTPATH . $r;
        [$origen, $destino] = [$absoluta($params[0] ?? ''), $absoluta($params[1] ?? '')];
        if ($origen === '' || $destino === '' || !is_file($origen)) {
            CLI::error('Uso: php spark ' . $this->usage);

            return;
        }
        try {
            CLI::write('SHA-256 de la copia: ' . hash_file('sha256', $origen));
            CifradoCopia::descifrar($origen, $destino, CifradoCopia::clave((string) config('Encryption')->key));
            CLI::write("Copia descifrada en {$destino}.", 'green');
        } catch (Throwable $e) {
            CLI::error($e->getMessage());
        }
    }
}
