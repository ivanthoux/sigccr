<?php

declare(strict_types=1);

namespace Tests\Unit\Solicitudes;

use App\Libraries\Solicitudes\Desembolso;
use App\Libraries\Solicitudes\ResolucionGerencial;
use PHPUnit\Framework\TestCase;

/** D04: resolución convencional (CU-26), cancelación (CU-28) y liquidación (CU-27). */
final class ResolucionLiquidacionTest extends TestCase
{
    private const HOY = '2026-11-04';

    private const CLIENTE = ['tipo_cliente' => 'PersonaHumana', 'nro_documento' => '28734006', 'representante_documento' => null, 'cuit_cuil' => '20287340061'];

    public function testAprobarConAlternativaConfirmadaSoloExigeFundamento(): void
    {
        $r = ResolucionGerencial::validar(['decision' => 'Aprobada', 'fundamento' => 'Ingresos estables y buena relación.'], true);
        $this->assertSame([], $r['errores']);
        $this->assertSame('confirmada', $r['datos']['plan']);
    }

    public function testAprobarSinConfirmadaExigeElPlan(): void
    {
        $e = ResolucionGerencial::validar(['decision' => 'Aprobada', 'fundamento' => 'Ingresos estables y buena relación.', 'plan' => 'confirmada'], false)['errores'];
        $this->assertArrayHasKey('plan', $e);
        $r = ResolucionGerencial::validar(['decision' => 'Aprobada', 'fundamento' => 'Ingresos estables y buena relación.', 'plan' => 'definida', 'importe' => '400.000,00', 'cantidad_cuotas' => '12'], false);
        $this->assertSame([], $r['errores']);
        $this->assertSame('400000.00', $r['datos']['importe']);
        $this->assertSame(12, $r['datos']['cuotas']);
        $e = ResolucionGerencial::validar(['decision' => 'Aprobada', 'fundamento' => 'Ingresos estables y buena relación.', 'plan' => 'alternativa'], false)['errores'];
        $this->assertArrayHasKey('alternativa_id', $e);
    }

    public function testRechazoYDevolucion(): void
    {
        $e = ResolucionGerencial::validar(['decision' => 'Rechazada', 'fundamento' => 'corto'], false)['errores'];
        $this->assertStringContainsString('motivo del rechazo', $e['fundamento']);
        $e = ResolucionGerencial::validar(['decision' => 'DevueltaRevision', 'fundamento' => 'Falta revisar los ingresos.'], false)['errores'];
        $this->assertArrayHasKey('etapa_retorno', $e);
        $r = ResolucionGerencial::validar(['decision' => 'DevueltaRevision', 'fundamento' => 'Falta revisar los ingresos.', 'etapa_retorno' => 'Verificacion'], false);
        $this->assertSame([], $r['errores']);
        $this->assertArrayHasKey('decision', ResolucionGerencial::validar([], false)['errores']);
    }

    public function testCbuYCvuConDigitosVerificadores(): void
    {
        $this->assertTrue(Desembolso::cbuValido('2850590940090418135201'));
        $this->assertTrue(Desembolso::cbuValido('0170099220000067797370'));
        $this->assertFalse(Desembolso::cbuValido('2850590940090418135202'));
        $this->assertFalse(Desembolso::cbuValido('28505909400904181352'));
    }

    public function testEfectivoExigeElDocumentoDelCliente(): void
    {
        $ok = Desembolso::validar(['medio' => 'Efectivo', 'documento_verificado' => '28.734.006'], self::CLIENTE, '488000.00', self::HOY, false);
        $this->assertSame([], $ok['errores']);
        $e = Desembolso::validar(['medio' => 'Efectivo', 'documento_verificado' => '30111222'], self::CLIENTE, '488000.00', self::HOY, true)['errores'];
        $this->assertStringContainsString('tercero', $e['documento_verificado']);
        $this->assertArrayHasKey('conformidad', $e);
    }

    public function testTransferenciaSoloACuentaDelClientePorElNeto(): void
    {
        $datos = [
            'medio' => 'Transferencia', 'documento_verificado' => '28734006', 'cbu' => '2850590940090418135201', 'cuit_titular' => '20-28734006-1',
            'evidencia_titularidad' => 'Constancia de CBU del banco a nombre del cliente.', 'comprobante' => '998877', 'fecha_transferencia' => '04/11/2026',
            'importe_transferencia' => '488.000,00',
        ];
        $r = Desembolso::validar($datos, self::CLIENTE, '488000.00', self::HOY, false);
        $this->assertSame([], $r['errores']);
        $this->assertSame('Op. 998877 del 2026-11-04', $r['datos']['comprobante']);
        $e = Desembolso::validar(['cuit_titular' => '27-30111222-5', 'importe_transferencia' => '500.000,00'] + $datos, self::CLIENTE, '488000.00', self::HOY, false)['errores'];
        $this->assertSame(['cuit_titular', 'importe_transferencia'], array_keys($e));
        $this->assertStringContainsString('terceros', $e['cuit_titular']);
    }
}
