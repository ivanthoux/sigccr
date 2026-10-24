<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\ModeloEvaluacion;
use PHPUnit\Framework\TestCase;

/** Modelo de evaluación crediticia (RN-69). */
final class ModeloEvaluacionTest extends TestCase
{
    public function testEjemploDelDocumento(): void
    {
        // situación 1 (40), sin atraso (25), endeudamiento 15 % (17,5) y 8 meses de antigüedad (4)
        $r = ModeloEvaluacion::evaluar(1, 1, 0, Decimal::of('15000'), Decimal::of('100000'), 8);
        $this->assertSame('86.50', $r['puntaje']->toFixed(2));
        $this->assertSame('A', $r['categoria']);
        $this->assertSame('Favorable', $r['resultado']);
        $this->assertSame(70, $r['indicadores']['endeudamiento']['valor']);
    }

    public function testSituacionVigenteExcluyente(): void
    {
        $r = ModeloEvaluacion::evaluar(5, 5, null, Decimal::zero(), Decimal::of('100000'), 30);
        $this->assertSame('Desfavorable', $r['resultado']);
    }

    public function testCategoriaCRequiereAnalisisGerencial(): void
    {
        // sin deudas (70·0,40=28) + sin antecedentes (50·0,25=12,5) + 25 % (40·0,25=10) + 12 meses (70·0,10=7) = 57,5
        $r = ModeloEvaluacion::evaluar(null, null, null, Decimal::of('25000'), Decimal::of('100000'), 12);
        $this->assertSame('57.50', $r['puntaje']->toFixed(2));
        $this->assertSame('C', $r['categoria']);
        $this->assertSame('RequiereAnalisisGerencial', $r['resultado']);
    }

    public function testLimitesDeTramos(): void
    {
        // atraso de 30 días = 70; endeudamiento exacto 10 % = 100; 24 meses = 100
        $r = ModeloEvaluacion::evaluar(2, 2, 30, Decimal::of('10000'), Decimal::of('100000'), 24);
        $this->assertSame(70, $r['indicadores']['comportamiento']['valor']);
        $this->assertSame(100, $r['indicadores']['endeudamiento']['valor']);
        $this->assertSame('76.50', $r['puntaje']->toFixed(2));
        $this->assertSame('B', $r['categoria']);
    }
}
