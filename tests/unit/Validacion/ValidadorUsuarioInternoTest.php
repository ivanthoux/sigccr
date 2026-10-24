<?php

declare(strict_types=1);

namespace Tests\Unit\Validacion;

use App\Libraries\Validacion\ValidadorUsuarioInterno;
use PHPUnit\Framework\TestCase;

/** Datos de un usuario interno (CU-05 paso 4 y A3, CU-06 paso 5). */
final class ValidadorUsuarioInternoTest extends TestCase
{
    /** @return array<string, string> */
    private function valido(): array
    {
        return ['nombre' => 'Ana María', 'apellido' => 'Gómez', 'correo' => 'ana.gomez@entidad.com.ar', 'tipo_documento' => 'DNI', 'nro_documento' => '28999111', 'rol' => 'Cajero'];
    }

    /** @param array<string, string> $cambios */
    private function errores(array $cambios, bool $conRol = true): array
    {
        return ValidadorUsuarioInterno::validar(ValidadorUsuarioInterno::normalizar([...$this->valido(), ...$cambios]), $conRol);
    }

    public function testDatosCompletosSonValidos(): void
    {
        $this->assertSame([], $this->errores([]));
    }

    public function testFormularioVacioMarcaCadaCampoObligatorio(): void
    {
        $e = ValidadorUsuarioInterno::validar(ValidadorUsuarioInterno::normalizar([]), true);
        $this->assertSame([
            'nombre' => 'Complete el nombre.',
            'apellido' => 'Complete el apellido.',
            'tipo_documento' => 'Complete el tipo de documento.',
            'nro_documento' => 'Complete el número de documento.',
            'correo' => 'Complete el correo electrónico.',
            'rol' => 'Seleccione el rol.',
        ], $e);
    }

    public function testSoloEspaciosCuentaComoVacio(): void
    {
        $this->assertSame(['nombre' => 'Complete el nombre.'], $this->errores(['nombre' => "   \t "]));
    }

    public function testUnErrorPorCampo(): void
    {
        $e = $this->errores(['correo' => 'ana@', 'nro_documento' => '12', 'apellido' => 'G0mez']);
        $this->assertSame(['apellido', 'nro_documento', 'correo'], array_keys($e));
    }

    public function testRol(): void
    {
        $this->assertSame(['rol' => 'Seleccione un rol válido.'], $this->errores(['rol' => 'Cliente']));
        // En la modificación el rol no se valida: se cambia con CU-10
        $this->assertSame([], $this->errores(['rol' => ''], false));
    }

    public function testNormalizaAntesDeValidar(): void
    {
        $d = ValidadorUsuarioInterno::normalizar([...$this->valido(), 'correo' => ' ANA.Gomez@Entidad.com.ar ', 'nro_documento' => '28.999.111', 'nombre' => ' Ana   María ']);
        $this->assertSame('ana.gomez@entidad.com.ar', $d['correo']);
        $this->assertSame('28999111', $d['nro_documento']);
        $this->assertSame('Ana María', $d['nombre']);
        $this->assertSame([], ValidadorUsuarioInterno::validar($d, true));
    }

    public function testDocumentoSegunTipo(): void
    {
        $this->assertSame([], $this->errores(['tipo_documento' => 'Pasaporte', 'nro_documento' => 'aab123456']));
        $this->assertArrayHasKey('nro_documento', $this->errores(['tipo_documento' => 'DNI', 'nro_documento' => 'AAB123456']));
        $this->assertSame(['tipo_documento' => 'Seleccione un tipo de documento válido.'], $this->errores(['tipo_documento' => 'CI']));
    }
}
