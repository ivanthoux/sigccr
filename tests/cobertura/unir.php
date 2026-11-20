<?php

/*
 * Une la cobertura de las suites de PHPUnit (build/*.cov) con las líneas que ejecutó la batería de punta a punta
 * (build/cobertura-e2e/*.gz) y escribe un Clover por conjunto: unitarias (Finanzas, Unitarias y Rutas),
 * + integración, + punta a punta. Uso: php tests/cobertura/unir.php
 */
require 'vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as Html;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

$raiz = getcwd() . '/';
/** @var CodeCoverage $a */
$a = include 'build/a.cov';
(new Clover())->process($a, 'build/clover-unitarias.xml');
if (is_file('build/b.cov')) {
    $a->merge(include 'build/b.cov');
}
(new Clover())->process($a, 'build/clover-phpunit.xml');

// Líneas ejecutadas por la batería de punta a punta, con la ruta del contenedor traducida a la del repositorio
$e2e = [];
$archivos = glob('build/cobertura-e2e/*.gz') ?: [];
foreach ($archivos as $f) {
    foreach (explode("\n", (string) gzdecode((string) file_get_contents($f))) as $fila) {
        if ($fila === '') {
            continue;
        }
        [$archivo, $lineas] = explode("\t", $fila, 2);
        $archivo = str_replace('/var/www/html/', $raiz, $archivo);
        foreach (explode(',', $lineas) as $l) {
            $e2e[$archivo][(int) $l] = true;
        }
    }
}
echo 'Archivos de punta a punta: ' . count($archivos) . ', archivos de app/ ejecutados: ' . count($e2e) . PHP_EOL;

$datos = $a->getData();
$lineas = $datos->lineCoverage();
$soloE2e = [];
foreach ($lineas as $archivo => $porLinea) {
    foreach ($porLinea as $l => $pruebas) {
        if ($pruebas !== null && isset($e2e[$archivo][$l])) {
            $lineas[$archivo][$l] = array_values(array_unique(array_merge((array) $pruebas, ['e2e'])));
            $soloE2e[$archivo][$l] = true;
        }
    }
}
$datos->setLineCoverage($lineas);
$a->setData($datos);
$a->setTests($a->getTests() + ['e2e' => ['size' => 'large', 'status' => 'success']]);
(new Clover())->process($a, 'build/clover-total.xml');
file_put_contents('build/e2e-lineas.json', json_encode(array_map('array_keys', $soloE2e)));
echo (new Text(Thresholds::default(), false, true))->process($a, false);
try {
    (new Html())->process($a, 'build/html');
} catch (Throwable $e) {
    echo 'Sin informe HTML: ' . $e->getMessage() . PHP_EOL;
}
