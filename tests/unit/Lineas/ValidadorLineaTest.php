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
        $this->assertSame('El importe máximo debe ser mayor que el mínimo (RN-14, D-23).', $e['monto_maximo']);
    }

    /** D-23: el mínimo y el máximo de importes, cuotas y edades no pueden ser iguales. */
    public function testMinimoYMaximoIgualesSeRechazan(): void
    {
        $e = V::validar(V::normalizar($this->motos([
            'monto_minimo' => '1.500.000', 'cuotas_minimas' => '24', 'edad_minima' => '40', 'edad_maxima' => '40',
        ])), self::CODIGOS, '2026-10-27');
        $this->assertSame('El importe máximo debe ser mayor que el mínimo (RN-14, D-23).', $e['monto_maximo']);
        $this->assertSame('La cantidad máxima de cuotas debe ser mayor que la mínima (RN-14, D-23).', $e['cuotas_maximas']);
        $this->assertSame('La edad máxima debe ser mayor que la mínima (RN-14, D-23).', $e['edad_maxima']);
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

    public function testLineaSoloConDni(): void
    {
        // D-14: una línea puede exigir únicamente el documento de identidad
        $express = $this->motos(['codigo' => 'EXPRESS', 'requisitos' => ['RelacionDependencia' => ['DOC_IDENTIDAD'], 'Monotributista' => ['DOC_IDENTIDAD']]]);
        $this->assertSame([], V::validar(V::normalizar($express), self::CODIGOS, '2026-10-27'));
        // pero no puede omitirlo
        $sinDni = $this->motos(['requisitos' => ['RelacionDependencia' => ['RECIBO_SUELDO'], 'Monotributista' => ['DOC_IDENTIDAD', 'CONST_MONOTRIBUTO']]]);
        $e = V::validar(V::normalizar($sinDni), self::CODIGOS, '2026-10-27');
        $this->assertSame('El documento de identidad es obligatorio en todas las categorías; falta en: Relación de dependencia.', $e['requisitos']);
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

    /** D-28: gasto administrativo como porcentaje del capital en cada cuota. */
    public function testGastoAdministrativoPorcentual(): void
    {
        $d = V::normalizar($this->motos(['tipo_gasto_administrativo' => 'Porcentual', 'gasto_administrativo_pct' => '0,5', 'gasto_administrativo_cuota' => '']));
        $this->assertSame('Porcentual', $d['tipo_gasto_administrativo']);
        $this->assertSame('0.005000', $d['gasto_administrativo_pct']);
        $this->assertSame('0.00', $d['gasto_administrativo_cuota']);
        $this->assertArrayNotHasKey('gasto_administrativo_cuota', V::validar($d, self::CODIGOS, '2026-10-27'));
        $this->assertArrayNotHasKey('gasto_administrativo_pct', V::validar($d, self::CODIGOS, '2026-10-27'));
        $e = V::validar(V::normalizar($this->motos(['tipo_gasto_administrativo' => 'Porcentual', 'gasto_administrativo_pct' => '15'])), self::CODIGOS, '2026-10-27');
        $this->assertStringContainsString('10 %', $e['gasto_administrativo_pct']);
        $e = V::validar(V::normalizar($this->motos(['tipo_gasto_administrativo' => 'Porcentual', 'gasto_administrativo_pct' => ''])), self::CODIGOS, '2026-10-27');
        $this->assertArrayHasKey('gasto_administrativo_pct', $e);
        $fijo = V::normalizar($this->motos());
        $this->assertSame('Fijo', $fijo['tipo_gasto_administrativo']);
        $this->assertNull($fijo['gasto_administrativo_pct']);
    }
}
