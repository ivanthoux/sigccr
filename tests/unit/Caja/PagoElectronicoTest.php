<?php

declare(strict_types=1);

namespace Tests\Unit\Caja;

use App\Libraries\Caja\PagoElectronico;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Pagos electrónicos: estados, vencimiento de la orden e incidencias (RN-30, RN-54 a RN-56). */
final class PagoElectronicoTest extends TestCase
{
    public function testEstadoDesconocidoQuedaPendiente(): void
    {
        $this->assertSame('aprobado', PagoElectronico::clasificar('approved'));
        $this->assertSame('rechazado', PagoElectronico::clasificar('Rejected'));
        $this->assertSame('pendiente', PagoElectronico::clasificar('in_process'));
        $this->assertSame('pendiente', PagoElectronico::clasificar('algo_nuevo'));
    }

    /** RN-54: una orden emitida a las 23:50 vence a las 00:00 locales; el instante UTC es las 03:00. */
    public function testVenceAlFinDelDiaLocal(): void
    {
        $v = PagoElectronico::vencimiento(new DateTimeImmutable('2026-11-08T02:50:00+00:00'), 'America/Argentina/Buenos_Aires');
        $this->assertSame('2026-11-08T00:00:00-03:00', $v->format('c'));
        $this->assertSame('2026-11-08 03:00', $v->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));
    }

    public function testIncidencias(): void
    {
        $vence = new DateTimeImmutable('2026-11-08T00:00:00-03:00');
        $enPlazo = new DateTimeImmutable('2026-11-07T23:59:00-03:00');
        $this->assertSame([], PagoElectronico::incidencias('21263.79', '21263.79', 'ARS', $vence, $enPlazo));
        $this->assertSame(['ImporteDistinto'], PagoElectronico::incidencias('21263.79', '20000.00', 'ARS', $vence, $enPlazo));
        $this->assertSame(['ImporteDistinto'], PagoElectronico::incidencias('21263.79', '21263.79', 'USD', $vence, $enPlazo));
        // RN-55: un pago en plazo notificado después del vencimiento es válido; uno realizado después es incidencia
        $this->assertSame(['FueraDePlazo'], PagoElectronico::incidencias('100', '100', 'ARS', $vence, $vence));
    }
}
