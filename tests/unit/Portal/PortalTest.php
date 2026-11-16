<?php

declare(strict_types=1);

namespace Tests\Unit\Portal;

use App\Adapters\Padron\SimuladorPadron;
use App\Adapters\ServicioNoDisponibleException;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Clientes\ValidadorPreRegistro;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Portal\ReglasPortal;
use PHPUnit\Framework\TestCase;

/**
 * Portal de autogestión (SS-07, D-42): reglas de presentación de CU-46 y CU-47, validaciones de CU-75, CU-98 y
 * CU-104, y el simulador del padrón de ARCA (CU-11 A9).
 */
final class PortalTest extends TestCase
{
    /** @return array<string, string> datos de Martina Díaz (guía de demostración) */
    private function preRegistro(): array
    {
        return [
            'nombre' => 'Martina', 'apellido' => 'Díaz', 'tipo_documento' => 'DNI', 'nro_documento' => '39.444.552',
            'cuit_cuil' => '27-39444552-0', 'correo' => ' Martina.Diaz@Prueba.local ', 'telefono' => '3758 41-2020', 'acepta_politica' => '1',
        ];
    }

    /** @param array<string, string> $c */
    private static function cuota(array $c, int $dias, string $vencimiento = '2026-12-10'): array
    {
        $base = ['Punitorio' => '0', 'InteresOrdinario' => '0', 'GastosCargos' => '0', 'IVA' => '0', 'NoCapitalizable' => '0', 'Capital' => '0'];

        return ['conceptos' => array_map(static fn ($v) => Decimal::of($v), $c + $base), 'dias_atraso' => $dias, 'vencimiento' => $vencimiento];
    }

    public function testPreRegistroValidoSeNormaliza(): void
    {
        $d = ValidadorPreRegistro::normalizar($this->preRegistro());
        $this->assertSame([], ValidadorPreRegistro::validar($d));
        $this->assertSame('39444552', $d['nro_documento']);
        $this->assertSame('27394445520', $d['cuit_cuil']);
        $this->assertSame('martina.diaz@prueba.local', $d['correo']);
        $this->assertTrue($d['acepta_politica']);
    }

    public function testPreRegistroExigeLaPoliticaDeDatos(): void
    {
        $datos = $this->preRegistro();
        unset($datos['acepta_politica']);
        $errores = ValidadorPreRegistro::validar(ValidadorPreRegistro::normalizar($datos));
        $this->assertArrayHasKey('acepta_politica', $errores);
        $this->assertCount(1, $errores);
    }

    public function testPreRegistroControlaElCuilContraElDni(): void
    {
        $datos = ['nro_documento' => '39444553'] + $this->preRegistro();
        $errores = ValidadorPreRegistro::validar(ValidadorPreRegistro::normalizar($datos));
        $this->assertSame('El CUIL no corresponde al número de documento.', $errores['cuit_cuil']);

        $errores = ValidadorPreRegistro::validar(ValidadorPreRegistro::normalizar(['cuit_cuil' => '27394445521'] + $this->preRegistro()));
        $this->assertStringContainsString('dígito verificador', $errores['cuit_cuil']);
    }

    public function testPreRegistroExigeLosDatosObligatorios(): void
    {
        $errores = ValidadorPreRegistro::validar(ValidadorPreRegistro::normalizar(['tipo_documento' => 'DNI']));
        foreach (['nombre', 'apellido', 'nro_documento', 'cuit_cuil', 'correo', 'acepta_politica'] as $campo) {
            $this->assertArrayHasKey($campo, $errores, $campo);
        }
        $this->assertArrayNotHasKey('telefono', $errores);
        $errores = ValidadorPreRegistro::validar(ValidadorPreRegistro::normalizar(['correo' => 'martina@', 'telefono' => '12'] + $this->preRegistro()));
        $this->assertArrayHasKey('correo', $errores);
        $this->assertArrayHasKey('telefono', $errores);
    }

    public function testAccionPendienteSegunElEstadoDeLaSolicitud(): void
    {
        $this->assertStringContainsString('documentación', (string) ReglasPortal::accionPendiente('PendienteDocumentacion'));
        $this->assertStringContainsString('alternativa', (string) ReglasPortal::accionPendiente('PendienteSeleccionPlan'));
        $this->assertStringContainsString('contrato', (string) ReglasPortal::accionPendiente('AprobadaPendienteLiquidacion'));
        $this->assertNull(ReglasPortal::accionPendiente('EnEvaluacion'));
        $this->assertNull(ReglasPortal::accionPendiente('Liquidada'));
        $this->assertNull(ReglasPortal::condicionFinal('PendienteResolucion'));
        $this->assertStringContainsString('liberaron', (string) ReglasPortal::condicionFinal('Vencida'));
    }

    public function testResumenDeLaDeudaPorConcepto(): void
    {
        $r = ReglasPortal::resumenDeuda([
            self::cuota(['Punitorio' => '335.64', 'InteresOrdinario' => '25000', 'GastosCargos' => '500', 'IVA' => '5425.48', 'Capital' => '31412.71'], 13),
            self::cuota(['InteresOrdinario' => '23345.28', 'GastosCargos' => '500', 'IVA' => '5007.51', 'Capital' => '31932.57'], 0),
        ]);
        $this->assertSame('335.64', $r['conceptos']['Punitorio']);
        $this->assertSame('48345.28', $r['conceptos']['InteresOrdinario']);
        $this->assertSame('63345.28', $r['conceptos']['Capital']);
        $this->assertSame('123459.19', $r['total']);
        $this->assertSame(1, $r['vencidas']);
        $this->assertSame(1, $r['pendientes']);
        $vacio = ReglasPortal::resumenDeuda([]);
        $this->assertSame('0.00', $vacio['total']);
        $this->assertSame(0, $vacio['vencidas']);
    }

    public function testProximosVencimientosSoloCuotasNoVencidasEnOrden(): void
    {
        $proximos = ReglasPortal::proximosVencimientos([
            self::cuota([], 0, '2027-02-10'),
            self::cuota([], 5, '2026-11-06'),
            self::cuota([], 0, '2026-12-10'),
            self::cuota([], 0, '2027-01-11'),
            self::cuota([], 0, '2027-03-10'),
        ]);
        $this->assertSame(['2026-12-10', '2027-01-11', '2027-02-10'], array_column($proximos, 'vencimiento'));
    }

    public function testSituacionDeLaCuotaFrenteALaTolerancia(): void
    {
        $this->assertSame('Sin atraso.', ReglasPortal::situacionAtraso(0, 3));
        $this->assertStringContainsString('dentro de la tolerancia', ReglasPortal::situacionAtraso(3, 3));
        $this->assertStringContainsString('desde el primer día', ReglasPortal::situacionAtraso(4, 3));
    }

    public function testElConsentimientoDebeCambiar(): void
    {
        ReglasPortal::validarCambioConsentimiento(null, true);
        ReglasPortal::validarCambioConsentimiento(true, false);
        $this->expectException(ReglaNegocioException::class);
        ReglasPortal::validarCambioConsentimiento(null, false);
    }

    public function testElConsentimientoOtorgadoNoSeVuelveAOtorgar(): void
    {
        $this->expectException(ReglaNegocioException::class);
        ReglasPortal::validarCambioConsentimiento(true, true);
    }

    public function testCorreoNuevoDelCambioPorAutogestion(): void
    {
        $this->assertSame('Ingrese el nuevo correo electrónico.', ReglasPortal::validarCorreoNuevo('', 'martina@prueba.local'));
        $this->assertNotNull(ReglasPortal::validarCorreoNuevo('martina@', 'martina@prueba.local'));
        $this->assertSame('El nuevo correo es igual al actual.', ReglasPortal::validarCorreoNuevo('martina@prueba.local', 'Martina@Prueba.local'));
        $this->assertNull(ReglasPortal::validarCorreoNuevo('martina.diaz@nuevo.local', 'martina@prueba.local'));
    }

    public function testSimuladorDelPadron(): void
    {
        $p = new SimuladorPadron();
        $martina = $p->consultar('27394445520');
        $this->assertSame(['nombre' => 'Martina', 'apellido' => 'Díaz', 'tipo_documento' => 'DNI', 'nro_documento' => '39444552', 'domicilio_fiscal' => 'Av. San Martín 2020, Apóstoles, Misiones'], $martina);
        $otro = $p->consultar('20301112225');
        $this->assertSame($otro, $p->consultar('20301112225'));
        $this->assertSame('30111222', $otro['nro_documento']);
        $this->assertNull($p->consultar('20981112223'));
        $this->expectException(ServicioNoDisponibleException::class);
        $p->consultar('20991112227');
    }
}
