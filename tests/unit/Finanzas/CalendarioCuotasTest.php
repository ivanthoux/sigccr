<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\CalendarioCuotas;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Calendario de cuotas con día ancla (RN-52). */
final class CalendarioCuotasTest extends TestCase
{
    /** @param list<DateTimeImmutable> $fechas @return list<string> */
    private function f(array $fechas): array
    {
        return array_map(static fn ($d) => $d->format('Y-m-d'), $fechas);
    }

    public function testPrimerVencimientoA30DiasYMensualidades(): void
    {
        $v = CalendarioCuotas::vencimientos(new DateTimeImmutable('2026-01-10'), 3);
        $this->assertSame(['2026-02-09', '2026-03-09', '2026-04-09'], $this->f($v));
    }

    public function testDiaAnclaInexistenteUsaUltimoDiaYConservaElAncla(): void
    {
        // 01/01/2026 + 30 días = 31/01/2026: ancla 31
        $v = CalendarioCuotas::vencimientos(new DateTimeImmutable('2026-01-01'), 4);
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], $this->f($v));
    }

    public function testAnioBisiesto(): void
    {
        $v = CalendarioCuotas::vencimientos(new DateTimeImmutable('2027-12-31'), 2);
        $this->assertSame(['2028-01-30', '2028-02-29'], $this->f($v));
    }

    public function testCambioDeAnio(): void
    {
        $v = CalendarioCuotas::vencimientos(new DateTimeImmutable('2026-11-05'), 3);
        $this->assertSame(['2026-12-05', '2027-01-05', '2027-02-05'], $this->f($v));
    }

    public function testNoTrasladaFinesDeSemana(): void
    {
        // 07/03/2026 es sábado: el vencimiento no se traslada
        $v = CalendarioCuotas::vencimientos(new DateTimeImmutable('2026-02-05'), 1);
        $this->assertSame(['2026-03-07'], $this->f($v));
    }
}
