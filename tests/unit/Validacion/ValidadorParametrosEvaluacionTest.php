<?php

declare(strict_types=1);

namespace Tests\Unit\Validacion;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\ModeloEvaluacion;
use App\Libraries\Validacion\ValidadorParametrosEvaluacion as V;
use PHPUnit\Framework\TestCase;

/** Parámetros configurables del motor de evaluación (CU-58, RN-69, D-13). */
final class ValidadorParametrosEvaluacionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function formularioSugerido(): array
    {
        return V::aFormulario(V::sugeridos());
    }

    public function testLosValoresSugeridosSonValidosYEvaluanIgual(): void
    {
        $p = V::normalizar($this->formularioSugerido());
        $this->assertSame([], V::validar($p));
        // Ejemplo de RN-69: situación 1, sin atraso, endeudamiento 15 %, 8 meses => 86,5, categoría A
        $r = ModeloEvaluacion::evaluar(1, 1, 0, Decimal::of('15000'), Decimal::of('100000'), 8, $p);
        $this->assertSame('86.50', $r['puntaje']->toFixed(2));
        $this->assertSame('A', $r['categoria']);
    }

    public function testUnaPrestadoraMasExigentePuedeCambiarPesosYCortes(): void
    {
        $f = $this->formularioSugerido();
        $f['pesos'] = ['bcra' => '50', 'comportamiento' => '30', 'endeudamiento' => '15', 'antiguedad' => '5'];
        $f['categorias'] = ['A' => '95', 'B' => '75', 'C' => '60', 'D' => '40', 'E' => '0'];
        $f['situaciones_excluyentes'] = ['3', '4', '5', '6'];
        $p = V::normalizar($f);
        $this->assertSame([], V::validar($p));
        // El mismo cliente del ejemplo ya no es categoría A
        $r = ModeloEvaluacion::evaluar(1, 1, 0, Decimal::of('15000'), Decimal::of('100000'), 8, $p);
        $this->assertSame('B', $r['categoria']);
        // y una situación 3 vigente pasa a ser excluyente
        $this->assertSame('Desfavorable', ModeloEvaluacion::evaluar(1, 3, 0, Decimal::of('15000'), Decimal::of('100000'), 8, $p)['resultado']);
    }

    public function testLosPesosDebenSumarCien(): void
    {
        $f = $this->formularioSugerido();
        $f['pesos']['antiguedad'] = '20';
        $e = V::validar(V::normalizar($f));
        $this->assertSame('Los pesos deben sumar 100 % (suman 110.00 %).', $e['pesos']);
    }

    public function testCortesDescendentesYCategoriaEDesdeCero(): void
    {
        $f = $this->formularioSugerido();
        $f['categorias']['C'] = '70';
        $f['categorias']['E'] = '10';
        $e = V::validar(V::normalizar($f));
        $this->assertArrayHasKey('categorias.C', $e);
        $this->assertArrayHasKey('categorias.E', $e);
    }

    public function testTramosOrdenados(): void
    {
        $f = $this->formularioSugerido();
        $f['comportamiento']['tramos'][2]['limite'] = '20';
        $f['antiguedad']['tramos'][1]['limite'] = '30';
        $e = V::validar(V::normalizar($f));
        $this->assertArrayHasKey('comportamiento.tramos', $e);
        $this->assertArrayHasKey('antiguedad.tramos', $e);
    }

    public function testValoresFueraDeRangoYResultadosIncoherentes(): void
    {
        $f = $this->formularioSugerido();
        $f['bcra']['2'] = '150';
        $f['resultados']['A'] = 'Desfavorable';
        $f['vigencia_dias'] = '0';
        $e = V::validar(V::normalizar($f));
        $this->assertArrayHasKey('bcra.2', $e);
        $this->assertArrayHasKey('resultados', $e);
        $this->assertArrayHasKey('vigencia_dias', $e);
    }
}
