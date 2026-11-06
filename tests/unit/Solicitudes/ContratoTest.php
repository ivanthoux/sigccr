<?php

declare(strict_types=1);

namespace Tests\Unit\Solicitudes;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Solicitudes\Contrato;
use PHPUnit\Framework\TestCase;

/** Contrato de préstamo y constancia firmada (CU-103, RN-78, D-31). */
final class ContratoTest extends TestCase
{
    public function testFechaDeRecepcion(): void
    {
        $this->assertSame('2026-11-06', Contrato::validarRecepcion('06/11/2026', '2026-11-05', '2026-11-06')['fecha']);
        $this->assertSame('2026-11-05', Contrato::validarRecepcion('2026-11-05', '2026-11-05', '2026-11-06')['fecha']);
        $this->assertStringContainsString('anterior a la aprobación', (string) Contrato::validarRecepcion('04/11/2026', '2026-11-05', '2026-11-06')['error']);
        $this->assertStringContainsString('futura', (string) Contrato::validarRecepcion('07/11/2026', '2026-11-05', '2026-11-06')['error']);
        $this->assertNotNull(Contrato::validarRecepcion('', '2026-11-05', '2026-11-06')['error']);
        $this->assertNotNull(Contrato::validarRecepcion('31/02/2026', '2026-11-05', '2026-11-06')['error']);
    }

    /** RN-34: la tasa punitoria diaria no supera el tope sobre la TNA. */
    public function testTasaPunitoriaDelContrato(): void
    {
        $morosidad = ['tasa_punitoria_diaria' => '0.0012', 'tope_sobre_tna' => '0.50', 'tolerancia_dias' => 3];
        // TNA 70 %: tope 0,35 / 365 = 0,000958904…; rige el tope
        $p = Contrato::punitorios($morosidad, Decimal::of('0.70'));
        $this->assertSame('0.000958904110', $p['diaria']->toFixed(12));
        $this->assertSame('0.35', $p['anual']->toFixed(2));
        $this->assertSame(3, $p['tolerancia']);
        // TNA 120 %: tope 0,60 / 365 = 0,00164; rige la tasa configurada
        $this->assertSame('0.001200000000', Contrato::punitorios($morosidad, Decimal::of('1.20'))['diaria']->toFixed(12));
    }
}
