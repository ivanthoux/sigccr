<?php

declare(strict_types=1);

namespace Tests\Unit\Validacion;

use App\Libraries\Solicitudes\CalendarioOperativo;
use App\Libraries\Validacion\ValidadorParametrosEstructuracion as E;
use PHPUnit\Framework\TestCase;

/** D06: parámetros de estructuración (CU-59) y calendario operativo (CU-82). */
final class ParametrosCalendarioTest extends TestCase
{
    private const HOY = '2026-11-04';

    /** @return array<string, string> */
    private function formulario(array $cambios = []): array
    {
        return $cambios + ['porcentaje_afectacion' => '30', 'alicuota_iva' => '21', 'vigencia_alternativas_dias' => '7', 'paso_redondeo' => '1.000,00', 'escalones' => '100 75 50', 'tope_global_cliente' => ''];
    }

    public function testLosValoresInicialesSonValidosYVuelvenAlFormulario(): void
    {
        $p = E::normalizar($this->formulario());
        $this->assertSame([], E::validar($p));
        $this->assertSame('0.3000', $p['porcentaje_afectacion']);
        $this->assertSame(['1.0000', '0.7500', '0.5000'], $p['escalones']);
        $this->assertNull($p['tope_global_cliente']);
        $f = E::aFormulario(E::SUGERIDOS);
        $this->assertSame('30', $f['porcentaje_afectacion']);
        $this->assertSame('100 75 50', $f['escalones']);
        $this->assertSame('1000,00', $f['paso_redondeo']);
        $this->assertSame('', $f['tope_global_cliente']);
    }

    public function testValoresIncoherentes(): void
    {
        $e = E::validar(E::normalizar($this->formulario([
            'porcentaje_afectacion' => '120', 'alicuota_iva' => 'x', 'vigencia_alternativas_dias' => '0', 'paso_redondeo' => '0', 'escalones' => '75 100', 'tope_global_cliente' => 'abc',
        ])));
        $this->assertSame(['porcentaje_afectacion', 'alicuota_iva', 'vigencia_alternativas_dias', 'paso_redondeo', 'escalones', 'tope_global_cliente'], array_keys($e));
        $this->assertArrayHasKey('escalones', E::validar(E::normalizar($this->formulario(['escalones' => '100 50 75']))));
        $p = E::normalizar($this->formulario(['tope_global_cliente' => '2.000.000,00', 'alicuota_iva' => '0']));
        $this->assertSame([], E::validar($p));
        $this->assertSame('2000000.00', $p['tope_global_cliente']);
    }

    public function testCalendarioRechazaFechasPasadasYDuplicadas(): void
    {
        $filas = CalendarioOperativo::filasDeTexto("08/12/2026; Inmaculada Concepción\n25/12/2026; Navidad; Feriado nacional\n01/11/2026; Pasado\n08/12/2026; Repetido\n31/12/2026; Balance; CierreEntidad\nxx; sin fecha", 'Feriado');
        $r = CalendarioOperativo::validar($filas, self::HOY, ['2026-12-25']);
        $this->assertSame(['2026-12-08', '2026-12-31'], array_column($r['validas'], 'fecha'));
        $this->assertSame('CierreEntidad', $r['validas'][1]['tipo']);
        $this->assertCount(4, $r['rechazadas']);
        $this->assertStringContainsString('ya está registrada', $r['rechazadas'][0]);
        $this->assertStringContainsString('pasada', $r['rechazadas'][1]);
    }
}
