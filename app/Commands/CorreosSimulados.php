<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Muestra los últimos correos guardados por el simulador de comunicaciones (solo desarrollo y pruebas), con el
 * enlace listo para copiar. En producción los correos los envía el proveedor y no quedan en disco.
 *
 *   php spark sigccr:correos          el último correo
 *   php spark sigccr:correos 5        los cinco últimos
 */
class CorreosSimulados extends BaseCommand
{
    protected $group = 'SIG-CCR';
    protected $name = 'sigccr:correos';
    protected $description = 'Muestra los últimos correos del simulador de comunicaciones con su enlace (desarrollo).';
    protected $usage = 'sigccr:correos [cantidad]';

    public function run(array $params): int
    {
        if (ENVIRONMENT === 'production') {
            CLI::error('No disponible en producción.');

            return EXIT_ERROR;
        }
        $archivos = glob(WRITEPATH . 'correos/*.html') ?: [];
        if ($archivos === []) {
            CLI::write('No hay correos simulados todavía (writable/correos está vacío).', 'yellow');

            return EXIT_SUCCESS;
        }
        usort($archivos, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($archivos, 0, max(1, (int) ($params[0] ?? 1))) as $archivo) {
            $html = (string) file_get_contents($archivo);
            preg_match('/<!-- Para: (.*?) \| Asunto: (.*?) -->/', $html, $cabecera);
            preg_match('/href="([^"]+)"/', $html, $enlace);
            CLI::newLine();
            CLI::write(date('d/m/Y H:i:s', (int) filemtime($archivo)) . '  ' . ($cabecera[1] ?? '?'), 'green');
            CLI::write('Asunto: ' . html_entity_decode($cabecera[2] ?? '?', ENT_QUOTES | ENT_HTML5));
            CLI::write('Enlace: ' . (isset($enlace[1]) ? html_entity_decode($enlace[1], ENT_QUOTES | ENT_HTML5) : '(sin enlace)'));
        }

        return EXIT_SUCCESS;
    }
}
