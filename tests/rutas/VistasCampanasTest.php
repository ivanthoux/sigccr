<?php

declare(strict_types=1);

namespace Tests\Rutas;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\ValidadorParametrosCampanas as P;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pantallas de las campañas crediticias (CU-50, CU-51 y CU-52, y CU-43 desde el portal) renderizadas con datos de
 * ejemplo, sin base de datos (D-41).
 *
 * @internal
 */
final class VistasCampanasTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        service('routes')->loadRoutes();
        session()->set('cuenta', ['id' => 2, 'tipo' => 'Interna', 'nombre' => 'Gerente', 'cliente_id' => 3, 'permisos' => ['campanas.consultar', 'parametros.campanas', 'solicitudes.consultar']]);
    }

    /** @return array<string, mixed> */
    private static function oferta(string $estado = 'Vigente'): array
    {
        return [
            'id' => 5, 'campana_id' => 2, 'campana_numero' => 1, 'campana_estado' => 'Activa', 'cliente_id' => 3, 'denominacion' => 'Díaz, Martina', 'cuit_cuil' => '27394445520',
            'linea' => 'Electrodomésticos', 'importe_maximo' => '600000.00', 'cantidad_cuotas' => 12, 'tna' => '0.750000000000', 'tea' => '1.069999999999', 'cftea' => '1.311111111111',
            'vence' => '2026-11-27 15:00:00.000 +00:00', 'en_vigencia' => 1, 'estado' => $estado, 'fecha_respuesta' => $estado === 'Vigente' ? null : '2026-11-13 15:00:00.000 +00:00',
            'solicitud_id' => $estado === 'Aceptada' ? 40 : null, 'solicitud_numero' => $estado === 'Aceptada' ? 33 : null, 'solicitud_estado' => $estado === 'Aceptada' ? 'PendienteVerificacion' : null,
            'version_fila' => 1, 'primera_cuota' => Decimal::of('72500.10'),
            'aceptables' => [
                ['importe' => Decimal::of('600000'), 'cuotas' => 12, 'viable' => true, 'primera_cuota' => Decimal::of('72500.10'), 'tea' => Decimal::of('1.07'), 'cftea' => Decimal::of('1.31')],
                ['importe' => Decimal::of('450000'), 'cuotas' => 6, 'viable' => true, 'primera_cuota' => Decimal::of('91000.00'), 'tea' => Decimal::of('1.07'), 'cftea' => Decimal::of('1.29')],
            ],
            'comunicaciones' => [['id' => 9, 'estado' => 'AceptadaProveedor', 'intentos' => 1, 'creado_en' => '2026-11-12 09:00:00.000 +00:00', 'programada_para' => '2026-11-12 09:00:00.000 +00:00']],
        ];
    }

    public function testParametrosDeCampanas(): void
    {
        $datos = [
            'titulo' => 'Parámetros', 'vigente' => ['id' => 4, 'numero' => 1, 'valores' => []], 'f' => P::aFormulario([]),
            'lineas' => [['id' => 4, 'nombre' => 'Electrodomésticos', 'codigo' => 'ELECTRO']], 'historial' => [],
        ];
        $html = view('parametros/campanas', $datos);
        foreach (['umbral_disponibilidad', 'factor_cobrabilidad', 'linea_id', 'capital_maximo', 'categorias_admitidas[]', 'canales[]', 'duracion_dias', 'fecha_vigencia', 'motivo'] as $campo) {
            $this->assertStringContainsString('name="' . $campo . '"', $html, $campo);
        }
        $this->assertStringContainsString('ELECTRO', $html);
        $this->assertStringContainsString('No hay ninguna línea de crédito activa', view('parametros/campanas', ['lineas' => []] + $datos));
    }

    public function testConsultaDeCampanas(): void
    {
        $f = ['numero' => '', 'linea' => '', 'estado' => '', 'desde' => '', 'hasta' => '', 'canal' => ''];
        $this->assertStringContainsString('Todavía no se activó', view('campanas/index', ['titulo' => 'Campañas', 'f' => $f, 'campanas' => [], 'lineas' => []]));
        $this->assertStringContainsString('(A1)', view('campanas/index', ['titulo' => 'Campañas', 'f' => ['estado' => 'Activa'] + $f, 'campanas' => [], 'lineas' => []]));
        $c = [
            'id' => 2, 'numero' => 1, 'activacion' => '2026-11-12 09:00:00.000 +00:00', 'linea' => 'Electrodomésticos', 'tna' => '0.75', 'cuotas_minimas' => 3, 'cuotas_maximas' => 18,
            'monto_minimo' => '50000.00', 'monto_maximo' => '800000.00', 'vence' => '2026-11-27 09:00:00.000 +00:00', 'fecha_cierre' => '2026-11-14 09:00:00.000 +00:00', 'causa_cierre' => 'Cupo',
            'capital_asignado' => '3000000.00', 'capital_comprometido' => '3000000.00', 'ofertas' => 1, 'estado' => 'Finalizada',
        ];
        $html = view('campanas/index', ['titulo' => 'Campañas', 'f' => $f, 'campanas' => [$c], 'lineas' => []]);
        $this->assertStringContainsString(site_url('admin/campanas/2'), $html);
        $this->assertStringContainsString('capital asignado alcanzado', $html);

        $detalle = $c + [
            'version_parametros' => 2, 'version_condiciones' => 1, 'disponible_proyectado' => '7500000.00', 'periodo_dias' => 30, 'fecha_proyeccion' => '2026-11-12 09:00:00.000 +00:00',
            'parametros' => P::completar([]), 'ofertas' => [self::oferta('Aceptada') + ['liquidado' => null, 'solicitud_importe' => '600000.00']],
            'resultados' => ['emitidas' => 1, 'vigentes' => 0, 'aceptadas' => 1, 'rechazadas' => 0, 'vencidas' => 0, 'solicitudes' => 1, 'capital_solicitado' => '600000.00', 'capital_colocado' => '0.00', 'tasa_aceptacion' => '100.00'],
            'comunicaciones' => [['estado' => 'AceptadaProveedor', 'cantidad' => 1]],
        ];
        $html = view('campanas/ver', ['titulo' => 'Campaña', 'c' => $detalle]);
        $this->assertStringContainsString('RN-43', $html);
        $this->assertStringContainsString(site_url('admin/solicitudes/40'), $html);
        $this->assertStringContainsString(site_url('admin/campanas/ofertas/5'), $html);
        $this->assertStringContainsString('aceptada por el proveedor', view('campanas/oferta', ['titulo' => 'Oferta', 'o' => self::oferta('Aceptada')]));
    }

    public function testOfertasEnElPortal(): void
    {
        $html = view('portal/propuestas', ['titulo' => 'Ofertas', 'ofertas' => [self::oferta()], 'propuestas' => [[
            'id' => 7, 'numero' => 3, 'version' => 1, 'fecha_registro' => '2026-11-10 12:00:00.000 +00:00', 'capital_refinanciado' => '500000.00', 'cantidad_cuotas' => 12, 'estado' => 'PendienteResolucion',
        ]]]);
        $this->assertStringContainsString(site_url('portal/ofertas/5'), $html);
        $this->assertStringContainsString(site_url('portal/propuestas/refinanciaciones/7'), $html);
        $this->assertStringContainsString('No recibió ofertas', view('portal/propuestas', ['titulo' => 'Ofertas', 'ofertas' => [], 'propuestas' => []]));

        $html = view('portal/oferta', ['titulo' => 'Oferta', 'o' => self::oferta()]);
        $this->assertStringContainsString(site_url('portal/ofertas/5/aceptar'), $html);
        $this->assertStringContainsString(site_url('portal/ofertas/5/rechazar'), $html);
        $this->assertStringContainsString('value="600000.00|12" checked', $html);
        $this->assertStringContainsString('value="450000.00|6"', $html);
        $html = view('portal/oferta', ['titulo' => 'Oferta', 'o' => self::oferta('Aceptada')]);
        $this->assertStringContainsString('solicitud N.º 33', $html);
        $this->assertStringNotContainsString('/aceptar', $html);

        $p = [
            'numero' => 3, 'version' => 1, 'estado' => 'Aplicada', 'fecha_registro' => '2026-11-10 12:00:00.000 +00:00', 'vence' => '2026-11-17 12:00:00.000 +00:00', 'observacion' => null,
            'cuotas' => [['credito' => 21, 'numero' => 1, 'vencimiento' => '2026-10-10', 'capital_pendiente' => '31412.71', 'no_capitalizable' => '25000.00', 'punitorio' => '335.64']],
            'capital_refinanciado' => '500000.00', 'concepto_no_capitalizable' => '25335.64', 'tasa' => '0.6', 'cantidad_cuotas' => 12,
            'plan_aplicado' => [['numero' => 1, 'vencimiento' => '2026-12-10', 'capital' => '40000.00', 'interes' => '25000.00', 'iva' => '5250.00', 'concepto_no_capitalizable' => '2111.30', 'gastos' => '0.00']],
            'plan' => null, 'resoluciones' => [['fecha' => '2026-11-10 13:00:00.000 +00:00', 'decision' => 'Aprobada', 'fundamento' => 'Capacidad de pago suficiente.']],
        ];
        $html = view('portal/propuesta', ['titulo' => 'Propuesta', 'p' => $p]);
        $this->assertStringContainsString('RN-37', $html);
        $this->assertStringContainsString('Plan aplicado', $html);
        $this->assertStringContainsString('Capacidad de pago suficiente.', $html);
    }
}
