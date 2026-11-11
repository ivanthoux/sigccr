<?php

declare(strict_types=1);

namespace Tests\Unit\Finanzas;

use App\Libraries\Cartera\Condonacion;
use App\Libraries\Finanzas\Decimal;
use PHPUnit\Framework\TestCase;

/** Condonación de punitorios (RN-38, D-36): máximo, distribución por antigüedad e IVA proporcional. */
final class CondonacionTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function cuotas(): array
    {
        return [
            ['id' => 1, 'punitorio_pendiente' => '200.0049', 'iva_punitorio_pendiente' => '42.0010'],
            ['id' => 2, 'punitorio_pendiente' => '100.0000', 'iva_punitorio_pendiente' => '21.0000'],
        ];
    }

    public function testMaximoCondonable(): void
    {
        $this->assertSame('300.00', Condonacion::maximo($this->cuotas(), Decimal::of('1'))->toFixed(2));
        $this->assertSame('149.99', Condonacion::maximo([['punitorio_pendiente' => '299.99']], Decimal::of('0.5'))->toFixed(2));
    }

    public function testDistribucionPorAntiguedadConIvaProporcional(): void
    {
        $r = Condonacion::distribuir(Decimal::of('250.00'), $this->cuotas());
        $this->assertCount(2, $r);
        // Primera cuota: se condona todo, con todo su IVA
        $this->assertSame(['200.0049', '42.0010', '0.0000', '0.0000'], [$r[0]['importe']->toFixed(4), $r[0]['iva']->toFixed(4), $r[0]['punitorio_pendiente']->toFixed(4), $r[0]['iva_punitorio_pendiente']->toFixed(4)]);
        // Segunda: la mitad del punitorio y la mitad de su IVA
        $this->assertSame(['50.0000', '10.5000', '50.0000', '10.5000'], [$r[1]['importe']->toFixed(4), $r[1]['iva']->toFixed(4), $r[1]['punitorio_pendiente']->toFixed(4), $r[1]['iva_punitorio_pendiente']->toFixed(4)]);
    }

    public function testImporteMenorQueLaPrimeraCuota(): void
    {
        $r = Condonacion::distribuir(Decimal::of('20.00'), $this->cuotas());
        $this->assertCount(1, $r);
        $this->assertSame('4.2000', $r[0]['iva']->toFixed(4));
        $this->assertSame('180.0049', $r[0]['punitorio_pendiente']->toFixed(4));
    }
}
