<?php

declare(strict_types=1);

namespace Tests\Unit\Lineas;

use App\Libraries\Lineas\ValidadorLinea as V;
use PHPUnit\Framework\TestCase;

/** Alta y modificación de líneas: límites de importe y coherencia de condiciones (CU-16, CU-17, RN-14, D-09). */
final class ValidadorLineaTest extends TestCase
{
    private const CODIGOS = ['DOC_IDENTIDAD', 'RECIBO_SUELDO', 'RECIBO_HABERES', 'CONST_MONOTRIBUTO', 'PAGOS_MONOTRIBUTO'];

    /** Línea de ejemplo de la devolución: créditos para motos entre $ 300.000 y $ 1.500.000. */
    private function motos(array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'motos', 'nombre' => 'Créditos para motos', 'descripcion' => 'Compra de motos nuevas o usadas.',
            'tna' => '70', 'gasto_otorgamiento_pct' => '2', 'gasto_administrativo_cuota' => '500',
            'monto_minimo' => '300.000', 'monto_maximo' => '1.500.000,00', 'cuotas_minimas' => '6', 'cuotas_maximas' => '24',
            'admite_persona_humana' => '1', 'edad_minima' => '18', 'edad_maxima' => '75',
            'categorias' => ['RelacionDependencia', 'Monotributista'],
            'requisitos' => ['RelacionDependencia' => ['DOC_IDENTIDAD', 'RECIBO_SUELDO'], 'Monotributista' => ['DOC_IDENTIDAD', 'CONST_MONOTRIBUTO', 'PAGOS_MONOTRIBUTO']],
            'vigencia_inicio' => '01/11/2026', 'vigencia_fin' => '31/12/2027',
        ], $cambios);
    }

    public function testLineaMotosValidaYNormalizada(): void
    {
        $d = V::normalizar($this->motos());
        $this->assertSame([], V::validar($d, self::CODIGOS, '2026-10-27'));
        $this->assertSame('MOTOS', $d['codigo']);
        $this->assertSame('300000.00', $d['monto_minimo']);
        $this->assertSame('1500000.00', $d['monto_maximo']);
        $this->assertSame('0.700000', $d['tna']);
        $this->assertSame('0.020000', $d['gasto_otorgamiento_pct']);
        $this->assertSame('70', V::aPorcentaje($d['tna']));
        $this->assertNull($d['antiguedad_minima_meses']);
    }

    public function testElMinimoNoPuedeSuperarAlMaximo(): void
    {
        $e = V::validar(V::normalizar($this->motos(['monto_minimo' => '2.000.000'])), self::CODIGOS, '2026-10-27');
        $this->assertSame('El importe máximo no puede ser menor que el mínimo (RN-14).', $e['monto_maximo']);
    }

    public function testImportesObligatoriosYPositivos(): void
    {
        $e = V::validar(V::normalizar($this->motos(['monto_minimo' => '0', 'monto_maximo' => ''])), self::CODIGOS, '2026-10-27');
        $this->assertArrayHasKey('monto_minimo', $e);
        $this->assertArrayHasKey('monto_maximo', $e);
    }

    public function testCuotasEdadesYVigenciaCoherentes(): void
    {
        $e = V::validar(V::normalizar($this->motos([
            'cuotas_minimas' => '30', 'edad_minima' => '80', 'edad_maxima' => '70', 'vigencia_fin' => '01/10/2026',
        ])), self::CODIGOS, '2026-10-27');
        $this->assertArrayHasKey('cuotas_maximas', $e);
        $this->assertArrayHasKey('edad_maxima', $e);
        $this->assertArrayHasKey('vigencia_fin', $e);
    }

    public function testCategoriasYDocumentacionPorCategoria(): void
    {
        $sinDocs = $this->motos();
        $sinDocs['requisitos']['Monotributista'] = [];
        $e = V::validar(V::normalizar($sinDocs), self::CODIGOS, '2026-10-27');
        $this->assertSame('Indique la documentación exigida para: Monotributista.', $e['requisitos']);

        $empresa = V::validar(V::normalizar($this->motos(['categorias' => ['Empresa']])), self::CODIGOS, '2026-10-27');
        $this->assertArrayHasKey('categorias', $empresa);

        $ninguno = V::validar(V::normalizar($this->motos(['admite_persona_humana' => ''])), self::CODIGOS, '2026-10-27');
        $this->assertArrayHasKey('tipos_cliente', $ninguno);
    }

    public function testTasasEnPorcentaje(): void
    {
        $this->assertSame('0.655000', V::porcentaje('65,5 %'));
        $this->assertNull(V::porcentaje('abc'));
        $e = V::validar(V::normalizar($this->motos(['tna' => '600', 'gasto_otorgamiento_pct' => '100'])), self::CODIGOS, '2026-10-27');
        $this->assertArrayHasKey('tna', $e);
        $this->assertArrayHasKey('gasto_otorgamiento_pct', $e);
    }

    public function testEstadoEfectivo(): void
    {
        $this->assertSame('Vencida', V::estadoEfectivo('Activa', '2026-10-26', '2026-10-27'));
        $this->assertSame('Suspendida', V::estadoEfectivo('Suspendida', '2027-01-01', '2026-10-27'));
    }
}
