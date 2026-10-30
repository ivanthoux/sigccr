<?php

declare(strict_types=1);

namespace Tests\Unit\Comunicaciones;

use App\Adapters\Comunicaciones\ComunicacionesInterface as C;
use App\Libraries\Comunicaciones\PoliticaEnvio as P;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Cola de comunicaciones: validez, reintentos y resultado incierto (CU-95, RNF-09). */
final class PoliticaEnvioTest extends TestCase
{
    private function t(string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-29 ' . $hora . '+00:00');
    }

    public function testReintentosAlUnoTresYCincoMinutosDelPrimerIntento(): void
    {
        $primero = $this->t('12:00:00');
        $this->assertSame('12:01', P::despuesDelIntento(C::FALLO_TRANSITORIO, 1, $primero)['programada_para']?->format('H:i'));
        $this->assertSame('12:03', P::despuesDelIntento(C::FALLO_TRANSITORIO, 2, $primero)['programada_para']?->format('H:i'));
        $this->assertSame('12:05', P::despuesDelIntento(C::FALLO_TRANSITORIO, 3, $primero)['programada_para']?->format('H:i'));
        $agotado = P::despuesDelIntento(C::FALLO_TRANSITORIO, 4, $primero);
        $this->assertSame('Fallida', $agotado['estado']);
        $this->assertTrue($agotado['alerta']);
    }

    public function testAceptadoDefinitivoEIncierto(): void
    {
        $primero = $this->t('12:00:00');
        $this->assertSame('AceptadaProveedor', P::despuesDelIntento(C::ACEPTADO, 2, $primero)['estado']);
        $definitivo = P::despuesDelIntento(C::FALLO_DEFINITIVO, 1, $primero);
        $this->assertSame('Fallida', $definitivo['estado']);
        $this->assertNull($definitivo['programada_para']);
        // A4: un resultado incierto no se reenvía
        $incierto = P::despuesDelIntento(C::INCIERTO, 1, $primero);
        $this->assertSame('EnRevision', $incierto['estado']);
        $this->assertNull($incierto['programada_para']);
    }

    public function testAutorizacionVencidaUsadaOSustituidaSeCancela(): void
    {
        $ahora = $this->t('12:00:00');
        $this->assertNull(P::motivoCancelacion('Autorizacion', ['estado' => 'Vigente', 'vence' => '2026-10-29 12:15:00+00:00'], null, null, $ahora));
        $this->assertSame('La autorización venció antes del envío.', P::motivoCancelacion('Autorizacion', ['estado' => 'Vigente', 'vence' => '2026-10-29 11:59:00+00:00'], null, null, $ahora));
        $this->assertSame('La autorización ya fue utilizada.', P::motivoCancelacion('VerificacionCorreo', ['estado' => 'Utilizada', 'vence' => '2026-10-30 00:00:00+00:00'], null, null, $ahora));
        $this->assertSame('La autorización ya fue sustituida o vencida.', P::motivoCancelacion('Autorizacion', ['estado' => 'Sustituida', 'vence' => '2026-10-30 00:00:00+00:00'], null, null, $ahora));
        $this->assertSame('La autorización de origen no existe.', P::motivoCancelacion('Autorizacion', null, null, null, $ahora));
    }

    public function testOfertaRequiereVigenciaYConsentimiento(): void
    {
        $ahora = $this->t('12:00:00');
        $vigente = ['estado' => 'Vigente', 'vence' => '2026-11-10 00:00:00+00:00'];
        $this->assertNull(P::motivoCancelacion('Oferta', null, $vigente, true, $ahora));
        $this->assertStringContainsString('RN-64', (string) P::motivoCancelacion('Oferta', null, $vigente, false, $ahora));
        $this->assertSame('La oferta ya no está vigente.', P::motivoCancelacion('Oferta', null, ['estado' => 'Aceptada', 'vence' => '2026-11-10 00:00:00+00:00'], true, $ahora));
        // los avisos operativos no dependen de autorizaciones ni de consentimiento
        $this->assertNull(P::motivoCancelacion('AvisoObligacion', null, null, null, $ahora));
    }
}
