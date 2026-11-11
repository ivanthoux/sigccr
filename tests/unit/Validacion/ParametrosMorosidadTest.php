<?php

declare(strict_types=1);

namespace Tests\Unit\Validacion;

use App\Libraries\Validacion\ValidadorParametrosMorosidad as M;
use PHPUnit\Framework\TestCase;

/** CU-60 Configurar Parámetros de Morosidad (RN-34, RN-35, RN-38, RN-53, RN-65). */
final class ParametrosMorosidadTest extends TestCase
{
    /** @return array<string, string> formulario con los valores iniciales */
    private function formulario(array $cambios = []): array
    {
        return array_replace(M::aFormulario(M::SUGERIDOS), $cambios);
    }

    public function testValoresInicialesIdaYVuelta(): void
    {
        $f = $this->formulario();
        $this->assertSame('0,12', $f['tasa_punitoria_diaria']);
        $this->assertSame('50', $f['tope_sobre_tna']);
        $p = M::normalizar($f);
        $this->assertSame([], M::validar($p));
        $this->assertSame('0.001200', $p['tasa_punitoria_diaria']);
        $this->assertSame('0.5000', $p['tope_sobre_tna']);
        $this->assertSame(3, $p['tolerancia_dias']);
        $this->assertSame(36, $p['refinanciacion']['cuotas_maximas']);
    }

    /** A1: valores incoherentes. */
    public function testValoresIncoherentes(): void
    {
        $e = M::validar(M::normalizar($this->formulario([
            'tasa_punitoria_diaria' => '-1', 'tope_sobre_tna' => '60', 'tolerancia_dias' => '5', 'umbral_morosidad_avanzada_dias' => '5',
            'limite_condonacion' => '120', 'refinanciacion_tasa_minima' => '80', 'refinanciacion_tasa_maxima' => '30', 'refinanciacion_cuotas_maximas' => '0',
        ])));
        $this->assertSame([
            'tasa_punitoria_diaria', 'tope_sobre_tna', 'umbral_morosidad_avanzada_dias', 'limite_condonacion', 'refinanciacion_tasa_maxima', 'refinanciacion_cuotas_maximas',
        ], array_keys($e));
        $this->assertStringContainsString('769', $e['tope_sobre_tna']);
        $this->assertStringContainsString('mayor que la tolerancia', $e['umbral_morosidad_avanzada_dias']);
    }

    public function testToleranciaCeroYTasaCeroAdmitidas(): void
    {
        $this->assertSame([], M::validar(M::normalizar($this->formulario(['tolerancia_dias' => '0', 'tasa_punitoria_diaria' => '0']))));
        $this->assertArrayHasKey('tolerancia_dias', M::validar(M::normalizar($this->formulario(['tolerancia_dias' => '-2']))));
    }
}
