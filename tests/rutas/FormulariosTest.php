<?php

declare(strict_types=1);

namespace Tests\Rutas;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cada formulario POST de las vistas envía a una ruta POST declarada. Un formulario que usa el nombre de una ruta GET
 * (por ejemplo, la pantalla del formulario) termina en «no se encontró la ruta» al enviarse.
 *
 * Revisa las acciones con url_to('nombre') y con site_url('ruta/fija'); las que arman la ruta con variables se
 * prueban con las pantallas de cada caso de uso.
 *
 * @internal
 */
final class FormulariosTest extends CIUnitTestCase
{
    private RouteCollection $rutas;

    protected function setUp(): void
    {
        parent::setUp();
        helper('url');
        $this->rutas = service('routes')->loadRoutes();
    }

    /** @return list<array{vista: string, accion: string}> acciones de los formularios con method="post" */
    private function formulariosPost(): array
    {
        $r = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH . 'Views'));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/<form\b[^>]*>/i', (string) file_get_contents($f->getPathname()), $m);
            foreach ($m[0] as $form) {
                if (stripos($form, 'method="post"') === false || preg_match('/action="([^"]*)"/', $form, $a) !== 1) {
                    continue;
                }
                $r[] = ['vista' => str_replace(APPPATH . 'Views/', '', $f->getPathname()), 'accion' => $a[1]];
            }
        }

        return $r;
    }

    public function testCadaFormularioPostEnviaAUnaRutaPost(): void
    {
        $post = array_keys($this->rutas->getRoutes('POST', false));
        $errores = [];
        $revisadas = 0;
        foreach ($this->formulariosPost() as ['vista' => $vista, 'accion' => $accion]) {
            if (preg_match("/^<\\?= url_to\\('([^']+)'\\) \\?>$/", $accion, $n) === 1) {
                $ruta = ltrim((string) parse_url((string) route_to($n[1]), PHP_URL_PATH), '/');
            } elseif (preg_match("/^<\\?= site_url\\('([a-z0-9\\/-]+)'\\) \\?>$/", $accion, $n) === 1) {
                $ruta = $n[1];
            } else {
                continue;
            }
            $revisadas++;
            if (!in_array($ruta === '' ? '/' : $ruta, $post, true)) {
                $errores[] = "{$vista}: el formulario envía a «{$ruta}», que no tiene ruta POST";
            }
        }
        $this->assertGreaterThan(5, $revisadas);
        $this->assertSame([], $errores, implode("\n", $errores));
    }
}
