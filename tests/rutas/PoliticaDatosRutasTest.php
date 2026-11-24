<?php

declare(strict_types=1);

namespace Tests\Rutas;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * S-14 · La política de datos es pública y los formularios que recogen datos personales la enlazan y llevan la leyenda
 * de la AAIP (RNF-14; Ley 25.326, arts. 5 y 6; Res. AAIP 14/2018). Se verifica sobre las rutas y las vistas, sin base de
 * datos. Plan de pruebas 7.10.
 *
 * @internal
 */
final class PoliticaDatosRutasTest extends CIUnitTestCase
{
    /** Formularios donde una persona entrega sus datos personales (el alta en el panel y el pre-registro del portal). */
    private const FORMULARIOS = ['clientes/formulario.php', 'portal/pre_registro.php'];

    public function testLaPoliticaEsPublica(): void
    {
        $rutas = service('routes')->loadRoutes();
        $this->assertArrayHasKey('politica-de-datos', $rutas->getRoutes('GET', false));
        $this->assertArrayNotHasKey('filter', $rutas->getRoutesOptions('politica-de-datos', 'GET'), 'Se lee antes de iniciar sesión.');
    }

    public function testLosFormulariosEnlazanLaPoliticaYLlevanLaLeyenda(): void
    {
        foreach (self::FORMULARIOS as $vista) {
            $codigo = (string) file_get_contents(APPPATH . 'Views/' . $vista);
            $this->assertStringContainsString("url_to('politica-datos')", $codigo, "{$vista}: la casilla debe enlazar la política.");
            $this->assertStringContainsString("view('partials/leyenda_aaip')", $codigo, "{$vista}: falta la leyenda de la AAIP.");
        }
    }

    public function testLaLeyendaUsaElTextoDeLaBiblioteca(): void
    {
        $parcial = (string) file_get_contents(APPPATH . 'Views/partials/leyenda_aaip.php');
        $this->assertStringContainsString('PoliticaDatos::LEYENDA_AAIP', $parcial);
    }
}
