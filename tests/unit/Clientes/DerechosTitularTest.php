<?php

declare(strict_types=1);

namespace Tests\Unit\Clientes;

use App\Libraries\Clientes\DerechosTitular;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de las solicitudes de derechos del titular (CU-108, D-64; Ley 25.326, arts. 14, 16 y 20; RN-85, RN-86).
 *
 * @internal
 */
final class DerechosTitularTest extends TestCase
{
    private static function f(string $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha);
    }

    /** RN-85: acceso y revisión vencen a los 10 días corridos, aunque caigan en un día no hábil (art. 14, inc. 2). */
    public function testAccesoYRevisionVencenAlosDiezDiasCorridos(): void
    {
        foreach (['Acceso', 'RevisionAutomatizada'] as $tipo) {
            $this->assertSame('2026-11-30', DerechosTitular::vence(self::f('2026-11-20 15:30:00'), $tipo, [])->format('Y-m-d'), $tipo);
            // los días corridos no saltan feriados ni fines de semana: lunes 23/11 + 10 = jueves 03/12
            $this->assertSame('2026-12-03', DerechosTitular::vence(self::f('2026-11-23 09:00:00'), $tipo, ['2026-11-30', '2026-12-01'])->format('Y-m-d'), $tipo);
        }
    }

    /** RN-85: rectificación, actualización y supresión vencen a los 5 días hábiles (art. 16, inc. 2). */
    public function testRectificacionActualizacionYSupresionVencenAlosCincoDiasHabiles(): void
    {
        foreach (['Rectificacion', 'Actualizacion', 'Supresion'] as $tipo) {
            // lunes 23/11/2026 → martes 24, miércoles 25, jueves 26, viernes 27, lunes 30
            $this->assertSame('2026-11-30', DerechosTitular::vence(self::f('2026-11-23 10:00:00'), $tipo, [])->format('Y-m-d'), $tipo);
        }
    }

    public function testElCincoHabilSaltaFinesDeSemanaYDiasNoHabiles(): void
    {
        // viernes 20/11/2026 (recibida) → lunes 23, martes 24, miércoles 25, jueves 26, viernes 27
        $this->assertSame('2026-11-27', DerechosTitular::vence(self::f('2026-11-20 08:00:00'), 'Supresion', [])->format('Y-m-d'));
        // con el lunes 23 y el martes 24 no hábiles: miércoles 25 (1), jueves 26 (2), viernes 27 (3), lunes 30 (4), martes 1/12 (5)
        $this->assertSame('2026-12-01', DerechosTitular::vence(self::f('2026-11-20 08:00:00'), 'Supresion', ['2026-11-23', '2026-11-24'])->format('Y-m-d'));
    }

    /** Recibida un día no hábil, el día de recepción no se cuenta y el primer día hábil es el siguiente. */
    public function testRecibidaUnSabadoCuentaDesdeElLunes(): void
    {
        // sábado 21/11/2026 → lunes 23 (1), martes 24 (2), miércoles 25 (3), jueves 26 (4), viernes 27 (5)
        $this->assertSame('2026-11-27', DerechosTitular::vence(self::f('2026-11-21 12:00:00'), 'Rectificacion', [])->format('Y-m-d'));
    }

    public function testSituacionDelPlazo(): void
    {
        $this->assertSame('EnPlazo', DerechosTitular::situacionPlazo('2026-12-10', '2026-11-23'));
        $this->assertSame('EnPlazo', DerechosTitular::situacionPlazo('2026-11-26', '2026-11-23'));
        $this->assertSame('PorVencer', DerechosTitular::situacionPlazo('2026-11-25', '2026-11-23'));
        $this->assertSame('PorVencer', DerechosTitular::situacionPlazo('2026-11-23', '2026-11-23'), 'El último día todavía está en término.');
        $this->assertSame('Vencido', DerechosTitular::situacionPlazo('2026-11-22', '2026-11-23'));
    }

    public function testValidacionDelPedido(): void
    {
        $this->assertSame([], DerechosTitular::validar(['tipo' => 'Acceso', 'detalle' => 'Quiero ver mis datos']));
        $this->assertArrayHasKey('tipo', DerechosTitular::validar(['tipo' => '', 'detalle' => 'Quiero ver mis datos']));
        $this->assertArrayHasKey('tipo', DerechosTitular::validar(['tipo' => 'Otro', 'detalle' => 'Quiero ver mis datos']));
        $this->assertArrayHasKey('detalle', DerechosTitular::validar(['tipo' => 'Acceso', 'detalle' => 'corto']));
        $this->assertArrayHasKey('detalle', DerechosTitular::validar(['tipo' => 'Acceso', 'detalle' => str_repeat('a', 1001)]));
        $this->assertSame([], DerechosTitular::validar(['tipo' => 'Acceso', 'detalle' => str_repeat('a', 1000)]));
    }

    public function testLaRevisionExigeLaSolicitud(): void
    {
        $this->assertArrayHasKey('solicitud_id', DerechosTitular::validar(['tipo' => 'RevisionAutomatizada', 'detalle' => 'No estoy de acuerdo', 'solicitud_id' => 0]));
        $this->assertSame([], DerechosTitular::validar(['tipo' => 'RevisionAutomatizada', 'detalle' => 'No estoy de acuerdo', 'solicitud_id' => 7]));
        $this->assertSame([], DerechosTitular::validar(['tipo' => 'Acceso', 'detalle' => 'Quiero ver mis datos', 'solicitud_id' => 0]), 'Los demás tipos no la piden.');
    }

    public function testValidacionDeLaRespuesta(): void
    {
        $this->assertSame([], DerechosTitular::validarRespuesta('Atendida', 'Se entregó la copia de sus datos.'));
        $this->assertSame([], DerechosTitular::validarRespuesta('Denegada', 'Los datos deben conservarse por una obligación legal.'));
        $this->assertArrayHasKey('estado', DerechosTitular::validarRespuesta('Pendiente', 'Se entregó la copia de sus datos.'));
        $this->assertArrayHasKey('estado', DerechosTitular::validarRespuesta('', 'Se entregó la copia de sus datos.'));
        $this->assertArrayHasKey('respuesta', DerechosTitular::validarRespuesta('Atendida', 'ok'));
        $this->assertArrayHasKey('respuesta', DerechosTitular::validarRespuesta('Atendida', str_repeat('a', 1001)));
    }

    /** Los plazos de la política coinciden con los que controla el sistema. */
    public function testElCatalogoDeTiposEsElDeLaBase(): void
    {
        $this->assertSame(['Acceso', 'Rectificacion', 'Actualizacion', 'Supresion', 'RevisionAutomatizada'], array_keys(DerechosTitular::TIPOS));
        $this->assertSame(['Pendiente', 'Atendida', 'Denegada'], array_keys(DerechosTitular::ESTADOS));
    }
}
