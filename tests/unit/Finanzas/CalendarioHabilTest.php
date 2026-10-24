<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Finanzas\CalendarioHabil;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** Vencimiento de aprobaciones en 72 horas hábiles (RN-49). */
final class CalendarioHabilTest extends TestCase
{
    private function f(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('America/Argentina/Buenos_Aires'));
    }

    public function testDesdeUnViernes(): void
    {
        // vie 23/10/2026 10:00: 14 h del viernes + lunes + martes (48 h) + 10 h del miércoles
        $v = CalendarioHabil::venceAprobacion($this->f('2026-10-23 10:00'), []);
        $this->assertSame('2026-10-28 10:00', $v->format('Y-m-d H:i'));
    }

    public function testRegistroEnDiaNoHabilComienzaElSiguienteDiaHabil(): void
    {
        $v = CalendarioHabil::venceAprobacion($this->f('2026-10-24 15:00'), []);
        $this->assertSame('2026-10-29 00:00', $v->format('Y-m-d H:i'));
    }

    public function testElComputoSeSuspendeEnFeriados(): void
    {
        $v = CalendarioHabil::venceAprobacion($this->f('2026-10-23 10:00'), ['2026-10-26']);
        $this->assertSame('2026-10-29 10:00', $v->format('Y-m-d H:i'));
    }
}
