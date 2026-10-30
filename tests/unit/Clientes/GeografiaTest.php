<?php

declare(strict_types=1);

namespace Tests\Unit\Clientes;

use App\Libraries\Clientes\CatalogoGeografico;
use App\Libraries\Validacion\ValidadorCliente;
use PHPUnit\Framework\TestCase;

/** D-18: provincia, localidad y código postal del domicilio; lectura del catálogo de localidades censales (georef). */
final class GeografiaTest extends TestCase
{
    public function testLasProvinciasSonLas24JurisdiccionesConSuCodigoIndec(): void
    {
        $this->assertCount(24, CatalogoGeografico::PROVINCIAS);
        $this->assertSame('Misiones', CatalogoGeografico::PROVINCIAS[54]);
        $this->assertSame('Corrientes', CatalogoGeografico::PROVINCIAS[18]);
        foreach (CatalogoGeografico::LOCALIDADES_BASE as [$provincia]) {
            $this->assertArrayHasKey($provincia, CatalogoGeografico::PROVINCIAS);
        }
    }

    public function testLeeElCatalogoEnJsonConLaListaDentroDeUnObjeto(): void
    {
        $json = '{"cantidad":2,"localidades-censales":[{"id":"54007010","nombre":"APOSTOLES","provincia":{"id":"54","nombre":"Misiones"},"departamento":{"id":"54007","nombre":"APOSTOLES"},"categoria":"Localidad simple"},'
            . '{"id":"18077020","nombre":"Colonia Liebig","provincia":{"id":"18","nombre":"Corrientes"},"departamento":{"id":"18077","nombre":"Ituzaingó"}},'
            . '{"id":"99","nombre":"Sin provincia","provincia":{"id":"99"}}]}';
        $l = CatalogoGeografico::leer($json);
        $this->assertCount(2, $l);
        $this->assertSame(['codigo' => '54007010', 'nombre' => 'Apostoles', 'provincia_id' => 54, 'departamento' => 'Apostoles'], $l[0]);
        $this->assertSame('Colonia Liebig', $l[1]['nombre']);
    }

    public function testLeeElCatalogoEnNdjson(): void
    {
        $nd = '{"id":"54007020","nombre":"Azara","provincia":{"id":"54"},"departamento":{"nombre":"Apóstoles"}}' . "\n"
            . '{"id":"54007030","nombre":"San José","provincia":{"id":"54"},"departamento":{"nombre":"Apóstoles"}}' . "\n";
        $this->assertSame(['Azara', 'San José'], array_column(CatalogoGeografico::leer($nd), 'nombre'));
    }

    public function testNombrePropioYClaveDeComparacion(): void
    {
        $this->assertSame('San Jose de la Esquina', CatalogoGeografico::nombrePropio('SAN  JOSE DE LA ESQUINA'));
        $this->assertSame('Leandro N. Alem', CatalogoGeografico::nombrePropio('Leandro N. Alem'));
        $this->assertSame(CatalogoGeografico::clave('Apóstoles'), CatalogoGeografico::clave('APOSTOLES'));
        $this->assertSame('gobernador ingeniero valentin virasoro', CatalogoGeografico::clave('Gobernador Ingeniero Valentín Virasoro'));
    }

    public function testCodigoPostalDeCuatroDigitosOCpa(): void
    {
        $this->assertSame('3350', CatalogoGeografico::codigoPostal(' 3350 '));
        $this->assertSame('N3350ABC', CatalogoGeografico::codigoPostal('n3350abc'));
        $this->assertNull(CatalogoGeografico::codigoPostal('335'));
        $this->assertNull(CatalogoGeografico::codigoPostal('I3350ABC'));
    }

    public function testElDomicilioExigeProvinciaYLocalidad(): void
    {
        $base = ['domicilio' => 'Av. Sarmiento 1250'];
        $e = ValidadorCliente::validar(ValidadorCliente::normalizar($base), false, '2026-10-30');
        $this->assertSame('Seleccione la provincia.', $e['provincia_id']);
        $e = ValidadorCliente::validar(ValidadorCliente::normalizar($base + ['provincia_id' => '54', 'codigo_postal' => 'abc']), false, '2026-10-30');
        $this->assertSame('Seleccione la localidad.', $e['localidad_id']);
        $this->assertArrayHasKey('codigo_postal', $e);
    }
}
