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
        $this->assertSame('AAB123456', Campos::documento(' aab123456 '));
        // Solo se quita el separador de miles bien formado: el resto llega a numeroDocumento() y se rechaza
        $this->assertSame('28999111.', Campos::documento('28999111.'));
        $this->assertSame('28.99.9111', Campos::documento('28.99.9111'));
        $this->assertSame('AAB 123-456', Campos::documento('aab 123-456'));
        $this->assertSame('28999111', Campos::documentoFlexible('28.999.111.'));
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
        $this->assertSame('El documento debe tener entre 6 y 9 dígitos, sin puntos.', Campos::numeroDocumento('DNI', Campos::documento('28999111.')));
        $this->assertSame('El documento debe tener entre 6 y 9 dígitos, sin puntos.', Campos::numeroDocumento('DNI', Campos::documento('.28999111')));
        $this->assertNull(Campos::numeroDocumento('DNI', Campos::documento('28.999.111')));
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
        $this->assertSame('El dígito verificador no corresponde: para 20-12345678 el último dígito es 6.', Campos::cuit('20123456787'));
        // El dígito depende del prefijo: 20 y 27 para el mismo DNI tienen dígitos distintos y los dos son válidos
        $this->assertNull(Campos::cuit('20289991116', 'PersonaHumana'));
        $this->assertNull(Campos::cuit('27289991110', 'PersonaHumana'));
        $this->assertNull(Campos::cuit('23289991115', 'PersonaHumana'));
        $this->assertSame('El dígito verificador no corresponde: para 27-28999111 el último dígito es 0.', Campos::cuit('27289991116', 'PersonaHumana'));
        $this->assertSame('El CUIL o CUIT de una persona humana empieza con 20, 23, 24 o 27.', Campos::cuit('30500010912', 'PersonaHumana'));
        $this->assertSame('El CUIT de una persona jurídica empieza con 30, 33 o 34.', Campos::cuit('20289991116', 'PersonaJuridica'));
        $this->assertNull(Campos::cuit('30500010912', 'PersonaJuridica'));
    }

    public function testMotivo(): void
    {
        $this->assertSame('Indique el motivo de la operación (al menos 5 caracteres).', Campos::motivo('ok'));
        $this->assertNull(Campos::motivo('Licencia prolongada'));
        $this->assertSame('El motivo admite hasta 1000 caracteres.', Campos::motivo(str_repeat('a', 1001)));
    }

    /** D-49, COM-02 y COM-06: un campo enviado como lista se trata como vacío y los caracteres de control se eliminan. */
    public function testValoresQueNoSonTextoYCaracteresDeControl(): void
    {
        $this->assertSame('', Campos::texto(['Ana']));
        $this->assertSame('', Campos::texto(['a' => ['b']]));
        $this->assertSame('', Campos::correo(['ana@prueba.local']));
        $this->assertSame('', Campos::documento(['28999111']));
        $this->assertNull(Campos::importe(['100']));
        $this->assertNull(Campos::fecha(['2026-01-01']));
        $this->assertSame('12', Campos::texto(12));
        $this->assertSame('AnaB', Campos::texto("Ana\0B"));
        $this->assertSame('Ana B', Campos::texto("Ana\x07 \x1FB"));
        $this->assertSame('Ana María', Campos::texto("Ana\r\nMaría"));
        $this->assertSame('', Campos::crudo(['x']));
        $this->assertSame('  clave con espacios  ', Campos::crudo('  clave con espacios  '));
    }

    public function testLista(): void
    {
        $this->assertSame(['A', 'B'], Campos::lista(['A', ['x'], 'B']));
        $this->assertSame(['A'], Campos::lista('A'));
        $this->assertSame([], Campos::lista(null));
        $this->assertSame([], Campos::lista(''));
        $this->assertSame(['1'], Campos::lista([1]));
    }

    /** D-49, TXT-04 y TXT-05: el largo se mide como en NVARCHAR; un emoji ocupa dos unidades. */
    public function testLargoComoEnLaBase(): void
    {
        $this->assertSame(5, Campos::largo('Ñandú'));
        $this->assertSame(2, Campos::largo('😀'));
        $this->assertNull(Campos::largoMaximo(str_repeat('ñ', 60), 60, 'la nacionalidad'));
        $this->assertSame('La nacionalidad admite hasta 60 caracteres.', Campos::largoMaximo(str_repeat('ñ', 59) . '😀', 60, 'la nacionalidad'));
        $this->assertSame('El motivo admite hasta 1000 caracteres.', Campos::motivo(str_repeat('😀', 501)));
    }

    /** D-49, H-12 y FIL-03: los comodines de LIKE se buscan como texto (ESCAPE '!'). */
    public function testLiteralLike(): void
    {
        $this->assertSame('!%', Campos::literalLike('%'));
        $this->assertSame('a!_b', Campos::literalLike('a_b'));
        $this->assertSame('![a]', Campos::literalLike('[a]'));
        $this->assertSame('!!', Campos::literalLike('!'));
        $this->assertSame("O'Brien", Campos::literalLike("O'Brien"));
    }
}
