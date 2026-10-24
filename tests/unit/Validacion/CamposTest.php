<?php

declare(strict_types=1);

namespace Tests\Unit\Validacion;

use App\Libraries\Validacion\Campos;
use PHPUnit\Framework\TestCase;

/** Normalización y validación de campos comunes (RNF-02). */
final class CamposTest extends TestCase
{
    public function testNormalizacion(): void
    {
        $this->assertSame('Ana María', Campos::texto("  Ana \t  María \n"));
        $this->assertSame('', Campos::texto(null));
        $this->assertSame('ana.gomez@entidad.com.ar', Campos::correo(' Ana.Gomez@Entidad.COM.ar '));
        $this->assertSame('28999111', Campos::documento('28.999.111'));
        $this->assertSame('AAB123456', Campos::documento('aab 123-456'));
    }

    public function testObligatorioYLargo(): void
    {
        $this->assertSame('Complete el nombre.', Campos::obligatorio('', 'el nombre'));
        $this->assertNull(Campos::obligatorio('Ana', 'el nombre'));
        $this->assertNull(Campos::largoMaximo(str_repeat('a', 80), 80, 'el nombre'));
        $this->assertSame('El nombre admite hasta 80 caracteres.', Campos::largoMaximo(str_repeat('a', 81), 80, 'el nombre'));
    }

    public function testNombrePersona(): void
    {
        foreach (["Ana María", "Ñandú", "O'Connor", 'Pérez-Gómez', 'Jr. Smith'] as $valido) {
            $this->assertNull(Campos::nombrePersona($valido, 'el nombre'), $valido);
        }
        foreach (['Ana1', '<script>', '-Ana', 'Ana@', '123'] as $invalido) {
            $this->assertSame('El nombre solo admite letras, espacios, apóstrofos y guiones.', Campos::nombrePersona($invalido, 'el nombre'), $invalido);
        }
        $this->assertNull(Campos::nombrePersona('', 'el nombre'));
        $this->assertSame('El nombre admite hasta 80 caracteres.', Campos::nombrePersona(str_repeat('a', 81), 'el nombre'));
    }

    public function testCorreo(): void
    {
        foreach (['ana@entidad.com.ar', 'a.b+c@dominio.com', 'x@sub.dominio.org'] as $valido) {
            $this->assertNull(Campos::formatoCorreo($valido), $valido);
        }
        foreach (['ana', 'ana@', '@dominio.com', 'ana@dominio', 'ana gomez@dominio.com', 'ana@@dominio.com', str_repeat('a', 250) . '@x.com'] as $invalido) {
            $this->assertSame('Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.', Campos::formatoCorreo($invalido), $invalido);
        }
    }

    public function testDocumento(): void
    {
        $this->assertNull(Campos::tipoDocumento('DNI'));
        $this->assertSame('Seleccione un tipo de documento válido.', Campos::tipoDocumento('CUIT'));
        $this->assertNull(Campos::numeroDocumento('DNI', '28999111'));
        $this->assertNull(Campos::numeroDocumento('LE', '123456'));
        $this->assertSame('El documento debe tener entre 6 y 9 dígitos, sin puntos.', Campos::numeroDocumento('DNI', '12345'));
        $this->assertSame('El documento debe tener entre 6 y 9 dígitos, sin puntos.', Campos::numeroDocumento('DNI', '1234567890'));
        $this->assertSame('El documento debe tener entre 6 y 9 dígitos, sin puntos.', Campos::numeroDocumento('DNI', 'AB123456'));
        $this->assertSame('El número de documento no es válido.', Campos::numeroDocumento('DNI', '000000'));
        $this->assertNull(Campos::numeroDocumento('Pasaporte', 'AAB123456'));
        $this->assertSame('El pasaporte debe tener entre 5 y 15 letras o números.', Campos::numeroDocumento('Pasaporte', 'AB12'));
    }

    public function testCuit(): void
    {
        $this->assertNull(Campos::cuit('20-12345678-6'));
        $this->assertNull(Campos::cuit('30500010912'));
        $this->assertNull(Campos::cuit('20111111112'));
        $this->assertSame('El CUIT o CUIL debe tener 11 dígitos.', Campos::cuit('2012345678'));
        $this->assertSame('El CUIT o CUIL tiene un prefijo inválido.', Campos::cuit('11123456786'));
        $this->assertSame('El dígito verificador del CUIT o CUIL no es correcto.', Campos::cuit('20123456787'));
    }

    public function testMotivo(): void
    {
        $this->assertSame('Indique el motivo de la operación (al menos 5 caracteres).', Campos::motivo('ok'));
        $this->assertNull(Campos::motivo('Licencia prolongada'));
        $this->assertSame('El motivo admite hasta 1000 caracteres.', Campos::motivo(str_repeat('a', 1001)));
    }
}
