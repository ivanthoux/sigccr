<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Clientes\CatalogoGeografico;
use App\Services\Comunes\GeografiaService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Importa el catálogo oficial de localidades censales del Servicio de Normalización de Datos Geográficos (georef,
 * datos.gob.ar) para el domicilio de los clientes (D-18). Se ejecuta una vez después de la instalación y cuando se
 * quiera actualizar el catálogo; es idempotente.
 *
 *   php spark geografia:importar                      descarga el catálogo de sigccr.georefLocalidadesUrl
 *   php spark geografia:importar --archivo=ruta.json  usa un archivo descargado (JSON o NDJSON de georef)
 */
class ImportarGeografia extends BaseCommand
{
    protected $group = 'SIG-CCR';
    protected $name = 'geografia:importar';
    protected $description = 'Importa las localidades censales de georef para el domicilio de los clientes.';
    protected $usage = 'geografia:importar [--archivo=ruta]';
    protected $options = ['--archivo' => 'Archivo JSON o NDJSON de localidades censales ya descargado.'];

    public function run(array $params): void
    {
        $archivo = (string) (CLI::getOption('archivo') ?? '');
        try {
            if ($archivo !== '') {
                if (!is_file($archivo)) {
                    CLI::error("No existe el archivo {$archivo}.");

                    return;
                }
                $contenido = (string) file_get_contents($archivo);
            } else {
                $url = config('Sigccr')->georefLocalidadesUrl;
                CLI::write("Descargando {$url} …");
                $r = service('curlrequest', ['timeout' => 120, 'http_errors' => false, 'verify' => true], null, false)->get($url);
                if ($r->getStatusCode() !== 200) {
                    CLI::error("La descarga respondió {$r->getStatusCode()}. Descargue el archivo y use --archivo.");

                    return;
                }
                $contenido = (string) $r->getBody();
            }
            $localidades = CatalogoGeografico::leer($contenido);
            $resultado = (new GeografiaService())->importar($localidades);
        } catch (Throwable $e) {
            CLI::error('No se pudo importar el catálogo: ' . $e->getMessage());

            return;
        }
        CLI::write(sprintf('%d localidades leídas: %d nuevas, %d existentes completadas con su código.', count($localidades), $resultado['nuevas'], $resultado['actualizadas']), 'green');
    }
}
