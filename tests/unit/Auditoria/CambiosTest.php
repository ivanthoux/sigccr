<?php

declare(strict_types=1);

namespace Tests\Unit\Auditoria;

use App\Libraries\Auditoria\Cambios;
use PHPUnit\Framework\TestCase;

/**
 * D-48: el registro de auditoría de una modificación guarda el valor anterior y el nuevo de cada campo que cambió.
 */
final class CambiosTest extends TestCase
{
    public function testSoloRegistraLosCamposQueCambiaron(): void
    {
        $antes = ['nombre' => 'Ana', 'apellido' => 'Pérez', 'telefono' => '3758 400000', 'correo' => 'ana@prueba.local'];
        $despues = ['nombre' => 'Ana', 'apellido' => 'Pérez Gómez', 'telefono' => '3758 400001', 'correo' => 'ana@prueba.local'];

        $this->assertSame([
            'apellido' => ['anterior' => 'Pérez', 'nuevo' => 'Pérez Gómez'],
            'telefono' => ['anterior' => '3758 400000', 'nuevo' => '3758 400001'],
        ], Cambios::entre($antes, $despues));
    }

    public function testCamposElegidosYCamposAgregadosOQuitados(): void
    {
        $antes = ['tna' => '0.600000', 'id' => 7, 'version_fila' => 3];
        $despues = ['tna' => '0.65', 'id' => 7, 'version_fila' => 4, 'descripcion' => 'Motos 0 km'];

        $this->assertSame(['tna' => ['anterior' => '0.600000', 'nuevo' => '0.65']], Cambios::entre($antes, $despues, ['tna']));
        $this->assertSame([
            'tna' => ['anterior' => '0.600000', 'nuevo' => '0.65'],
            'descripcion' => ['anterior' => null, 'nuevo' => 'Motos 0 km'],
        ], Cambios::entre($antes, $despues, ['tna', 'descripcion', 'id']));
        $this->assertSame(['tipo' => ['anterior' => 'Feriado', 'nuevo' => null]], Cambios::entre(['tipo' => 'Feriado'], []));
    }

    public function testEquivalenciasQueNoSonCambios(): void
    {
        // Números con distinta escala, lógicos como 1 y 0, y vacío como null: el valor es el mismo
        $antes = ['tna' => '0.600000', 'monto_minimo' => '300000.00', 'activo' => 1, 'admite_persona_humana' => '1', 'empleador' => null, 'cero' => '0.00'];
        $despues = ['tna' => '0.6', 'monto_minimo' => '300000', 'activo' => true, 'admite_persona_humana' => true, 'empleador' => '', 'cero' => '0'];
        $this->assertSame([], Cambios::entre($antes, $despues));

        // Un documento o un código postal se comparan como texto: los ceros a la izquierda cuentan
        $this->assertSame(['codigo_postal' => ['anterior' => '3350', 'nuevo' => '03350']], Cambios::entre(['codigo_postal' => '3350'], ['codigo_postal' => '03350']));
        $this->assertSame(['activo' => ['anterior' => 1, 'nuevo' => 0]], Cambios::entre(['activo' => 1], ['activo' => 0]));
    }

    public function testDatosAnidadosPorRutaYListasCompletas(): void
    {
        $antes = ['pesos' => ['situacion_bcra' => 40, 'atraso_interno' => 30], 'cortes' => [80, 60, 40, 20, 0], 'vigencia_dias' => 30];
        $despues = ['pesos' => ['situacion_bcra' => 35, 'atraso_interno' => 35], 'cortes' => [80, 60, 40, 20, 0], 'vigencia_dias' => 30];
        $this->assertSame([
            'pesos.situacion_bcra' => ['anterior' => 40, 'nuevo' => 35],
            'pesos.atraso_interno' => ['anterior' => 30, 'nuevo' => 35],
        ], Cambios::entre($antes, $despues));

        $cambio = Cambios::entre(['escalones' => ['1', '0.75', '0.5']], ['escalones' => ['1', '0.8', '0.5']]);
        $this->assertSame(['escalones' => ['anterior' => ['1', '0.75', '0.5'], 'nuevo' => ['1', '0.8', '0.5']]], $cambio);
    }

    public function testUnSoloCampo(): void
    {
        $this->assertSame(['estado' => ['anterior' => 'Habilitada', 'nuevo' => 'Desactivada']], Cambios::campo('estado', 'Habilitada', 'Desactivada'));
        $this->assertSame([], Cambios::campo('estado', 'Activa', 'Activa'));
    }

    public function testDetalleNuevoYFormatosAnteriores(): void
    {
        $nuevo = Cambios::desdeDetalle(['cambios' => ['rol' => ['anterior' => 'Cajero', 'nuevo' => 'Analista']], 'version' => 3]);
        $this->assertSame(['rol' => ['anterior' => 'Cajero', 'nuevo' => 'Analista']], $nuevo['cambios']);
        $this->assertSame(['version' => 3], $nuevo['datos']);

        // Registros anteriores a D-48
        $cliente = Cambios::desdeDetalle(['telefono' => ['anterior' => '1', 'nuevo' => '2'], 'correo' => ['anterior' => 'a@x', 'nuevo' => 'b@x', 'evidencia_identidad' => 'DNI a la vista'], 'solicitudes_afectadas' => 1]);
        $this->assertSame(['telefono' => ['anterior' => '1', 'nuevo' => '2'], 'correo' => ['anterior' => 'a@x', 'nuevo' => 'b@x']], $cliente['cambios']);
        $this->assertSame(['correo' => ['evidencia_identidad' => 'DNI a la vista'], 'solicitudes_afectadas' => 1], $cliente['datos']);
        $this->assertSame(['estado' => ['anterior' => 'Habilitada', 'nuevo' => 'Inhabilitada']], Cambios::desdeDetalle(['anterior' => 'Habilitada', 'nuevo' => 'Inhabilitada'])['cambios']);
        $this->assertSame(['estado' => ['anterior' => 'Activa', 'nuevo' => 'Suspendida']], Cambios::desdeDetalle(['estado_anterior' => 'Activa', 'estado_nuevo' => 'Suspendida'])['cambios']);
        $tipo = Cambios::desdeDetalle(['antes' => ['nombre' => 'Recibo', 'activo' => 1], 'despues' => ['nombre' => 'Recibo de sueldo', 'activo' => 1]]);
        $this->assertSame([['nombre' => ['anterior' => 'Recibo', 'nuevo' => 'Recibo de sueldo']], []], [$tipo['cambios'], $tipo['datos']]);
        $this->assertSame(['cambios' => [], 'datos' => []], Cambios::desdeDetalle(null));
    }

    public function testPresentacion(): void
    {
        $this->assertSame('Correo electrónico', Cambios::etiqueta('correo'));
        $this->assertSame('Pesos › situacion bcra', Cambios::etiqueta('pesos.situacion_bcra'));
        $this->assertSame('Tope › A', Cambios::etiqueta('tope.A'));
        $this->assertSame('Valor sin etiqueta', Cambios::etiqueta('valor_sin_etiqueta'));

        $this->assertSame('—', Cambios::mostrar(null));
        $this->assertSame('—', Cambios::mostrar(''));
        $this->assertSame('Sí', Cambios::mostrar(true));
        $this->assertSame('No', Cambios::mostrar(false));
        $this->assertSame('DNI, RECIBO_SUELDO', Cambios::mostrar(['DNI', 'RECIBO_SUELDO']));
        $this->assertSame('{"a":1}', Cambios::mostrar(['a' => 1]));
        $this->assertSame('0.65', Cambios::mostrar('0.65'));
    }
}
