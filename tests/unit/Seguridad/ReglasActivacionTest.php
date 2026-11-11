<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Seguridad\ReglasActivacion as R;
use PHPUnit\Framework\TestCase;

/** CU-04 Activar o Rehabilitar Acceso del Cliente (RN-10, RN-62). */
final class ReglasActivacionTest extends TestCase
{
    /** @return array{estado_cliente: string, correo: string, correo_verificado: bool, estado_cuenta: ?string, bloqueada: bool} */
    private function cliente(array $c = []): array
    {
        return array_replace(['estado_cliente' => 'Activo', 'correo' => 'ana@correo.com', 'correo_verificado' => true, 'estado_cuenta' => 'PendienteActivacion', 'bloqueada' => false], $c);
    }

    public function testActivacionYRehabilitacion(): void
    {
        $this->assertSame('Activacion', R::evaluar($this->cliente(), 'ANA@correo.com ')['proposito']);
        $this->assertSame('Rehabilitacion', R::evaluar($this->cliente(['estado_cuenta' => 'Desactivada']), 'ana@correo.com')['proposito']);
    }

    public function testA1DatosQueNoCoincidenConUnClienteActivo(): void
    {
        $this->assertSame(R::RESULTADO_NO_VERIFICADO, R::evaluar(null, 'ana@correo.com')['resultado']);
        $this->assertSame(R::RESULTADO_NO_VERIFICADO, R::evaluar($this->cliente(['estado_cliente' => 'Desactivado', 'estado_cuenta' => 'Desactivada']), 'ana@correo.com')['resultado']);
        $this->assertSame(R::RESULTADO_NO_VERIFICADO, R::evaluar($this->cliente(), 'otra@correo.com')['resultado']);
    }

    public function testD40ElCorreoNoNecesitaEstarVerificado(): void
    {
        // El enlace va al correo registrado; usarlo lo verifica (D-40)
        $this->assertSame('Activacion', R::evaluar($this->cliente(['correo_verificado' => false]), 'ana@correo.com')['proposito']);
        $this->assertSame('Rehabilitacion', R::evaluar($this->cliente(['correo_verificado' => false, 'estado_cuenta' => 'Desactivada']), 'ana@correo.com')['proposito']);
        $this->assertSame(R::RESULTADO_NO_VERIFICADO, R::evaluar($this->cliente(['correo_verificado' => false]), 'otra@correo.com')['resultado']);
    }

    public function testA2CuentaHabilitadaOBloqueadaNoRecibeElEnlace(): void
    {
        $this->assertSame(R::RESULTADO_SIN_ENVIO, R::evaluar($this->cliente(['estado_cuenta' => 'Habilitada']), 'ana@correo.com')['resultado']);
        $this->assertSame(R::RESULTADO_SIN_ENVIO, R::evaluar($this->cliente(['estado_cuenta' => 'BloqueadaTemporalmente']), 'ana@correo.com')['resultado']);
        $this->assertSame(R::RESULTADO_SIN_ENVIO, R::evaluar($this->cliente(['bloqueada' => true]), 'ana@correo.com')['resultado']);
    }

    public function testIdentificacionPorDocumentoOCuit(): void
    {
        $this->assertSame(['tipo_documento' => 'DNI', 'nro_documento' => '30123456'], R::identificacion('DNI', '30.123.456'));
        $this->assertSame(['cuit' => '30712345671'], R::identificacion('CUIT', '30-71234567-1'));
        $this->assertNull(R::identificacion('DNI', '12'));
        $this->assertNull(R::identificacion('CUIT', '30-71234567-5'));
        $this->assertNull(R::identificacion('Otro', '30123456'));
    }
}
