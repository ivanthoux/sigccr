<?php

declare(strict_types=1);

namespace Tests\Rutas;

use App\Libraries\Finanzas\Decimal;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pantallas del portal de autogestión (CU-45 a CU-49, CU-75, CU-98, CU-104) y de los pre-registros del panel. Se
 * renderizan con datos de ejemplo, sin base de datos, para detectar errores de las vistas antes de la batería de
 * punta a punta del cierre de la iteración (D-41).
 *
 * @internal
 */
final class VistasPortalTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        service('routes')->loadRoutes();
        session()->set('cuenta', ['id' => 7, 'tipo' => 'Cliente', 'nombre' => 'Martina Díaz', 'cliente_id' => 3, 'permisos' => ['clientes.registrar']]);
    }

    /** @return array<string, mixed> */
    private static function cuota(int $numero, int $dias, string $estado = 'Pendiente'): array
    {
        $c = ['Punitorio' => $dias > 3 ? '335.64' : '0', 'InteresOrdinario' => '25000', 'GastosCargos' => '500', 'IVA' => '5425.48', 'NoCapitalizable' => '0', 'Capital' => '31412.71'];
        $conceptos = array_map(static fn ($v) => Decimal::of($v), $c);

        return [
            'id' => 100 + $numero, 'numero' => $numero, 'credito_id' => 9, 'credito_numero' => 21, 'vencimiento' => '2026-12-10', 'estado' => $estado,
            'capital' => '31412.71', 'interes' => '25000.00', 'gastos' => '500.00', 'iva' => '5355.00', 'con_parcial' => 0,
            'conceptos' => $conceptos, 'total' => $estado === 'Pagada' ? null : Decimal::sum(array_values($conceptos)), 'dias_atraso' => $dias, 'orden_pendiente' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function cliente(): array
    {
        return [
            'id' => 3, 'tipo_cliente' => 'PersonaHumana', 'nombre' => 'Martina', 'apellido' => 'Díaz', 'tipo_documento' => 'DNI', 'nro_documento' => '39444552',
            'cuit_cuil' => '27394445520', 'fecha_nacimiento' => '1995-03-12', 'estado_civil' => 'Soltero', 'correo' => 'martina.diaz@prueba.local',
            'correo_cuenta' => 'martina.diaz@prueba.local', 'correo_verificado' => 1, 'correo_pendiente' => null, 'telefono' => '3758 41-2020',
            'domicilio' => 'Av. San Martín 2020', 'localidad' => 'Apóstoles', 'provincia' => 'Misiones', 'codigo_postal' => '3350', 'ocupacion' => 'Contadora',
            'empleador' => 'Estudio Contable del Litoral', 'categoria_ingresos' => 'RelacionDependencia', 'ingresos_declarados' => '1500000.00',
            'consentimientos' => ['ComunicacionesComerciales' => ['otorgado' => 1, 'fecha' => '2026-11-11 10:00:00.000 +00:00', 'medio' => 'Sucursal'], 'PoliticaDatos' => null],
        ];
    }

    public function testPerfilPreferenciasYCambioDeCorreo(): void
    {
        $html = view('portal/perfil', ['titulo' => 'Mi perfil', 'c' => self::cliente()]);
        $this->assertStringContainsString('Martina Díaz', $html);
        $this->assertStringContainsString('27-39444552-0', $html);
        $this->assertStringContainsString('Relación de dependencia', $html);
        $this->assertStringContainsString(site_url('portal/perfil/correo'), $html);
        $this->assertStringContainsString(site_url('portal/perfil/preferencias'), $html);

        $html = view('portal/preferencias', ['titulo' => 'Preferencias', 'c' => self::cliente()]);
        $this->assertStringContainsString('value="Revocar"', $html);
        $this->assertStringContainsString('data-confirmar', $html);
        $sin = ['consentimientos' => ['ComunicacionesComerciales' => null]] + self::cliente();
        $this->assertStringContainsString('value="Otorgar"', view('portal/preferencias', ['titulo' => 'Preferencias', 'c' => $sin]));

        $this->assertStringContainsString('name="correo_nuevo"', view('portal/cambio_correo', ['titulo' => 'Correo', 'c' => self::cliente()]));
        $html = view('portal/confirmar_cambio_correo', ['titulo' => 'Confirmar', 'codigo' => str_repeat('a', 43), 'nuevo' => 'nuevo@prueba.local', 'resultado' => null]);
        $this->assertStringContainsString(site_url('portal/cambio-correo/' . str_repeat('a', 43)), $html);
        $this->assertStringContainsString('expiró', view('portal/confirmar_cambio_correo', ['titulo' => 'Confirmar', 'codigo' => 'x', 'nuevo' => null, 'resultado' => null]));
    }

    public function testSolicitudesYDetalle(): void
    {
        $this->assertStringContainsString('No tiene solicitudes', view('portal/solicitudes', ['titulo' => 'Mis solicitudes', 'solicitudes' => []]));
        $html = view('portal/solicitudes', ['titulo' => 'Mis solicitudes', 'solicitudes' => [[
            'id' => 5, 'numero' => 12, 'linea' => 'Motos', 'importe' => '500000.00', 'cantidad_cuotas' => 12, 'fecha_registro' => '2026-11-11 12:00:00.000 +00:00',
            'estado' => 'PendienteSeleccionPlan', 'etiqueta' => 'Pendiente de selección de plan', 'accion' => 'Acérquese a la sucursal para elegir y confirmar la alternativa de financiación.',
        ]]]);
        $this->assertStringContainsString(site_url('portal/solicitudes/5'), $html);
        $this->assertStringContainsString('elegir y confirmar', $html);

        $s = [
            'numero' => 12, 'linea' => 'Motos', 'importe' => '500000.00', 'cantidad_cuotas' => 12, 'fecha_registro' => '2026-11-11 12:00:00.000 +00:00',
            'estado' => 'Liquidada', 'etiqueta' => 'Liquidada', 'ultimo_movimiento' => '2026-11-11 15:00:00.000 +00:00', 'accion' => null,
            'condicion_final' => 'Solicitud liquidada: el crédito ya está en su estado de cuenta.', 'motivo_cancelacion' => null,
            'documentos' => [['tipo' => 'Documento de identidad', 'fecha_incorporacion' => '2026-11-11 12:00:00.000 +00:00']], 'faltantes' => [], 'faltantes_nombres' => [],
            'revisiones' => [['estado' => 'Verificada', 'fecha' => '2026-11-11 13:00:00.000 +00:00', 'observaciones' => null, 'verificaciones' => [['resultado' => 'Conforme', 'motivo_observacion' => null, 'observacion' => null]]]],
            'evaluacion' => ['modalidad' => 'Automatica', 'procesamiento' => 'Finalizada', 'resultado' => 'Favorable', 'fecha_inicio' => null, 'fecha_finalizacion' => '2026-11-11 13:05:00.000 +00:00', 'vence' => '2026-12-11 13:05:00.000 +00:00', 'vigencia' => 'Vigente'],
            'confirmada' => ['importe' => '500000.00', 'cantidad_cuotas' => 12, 'valor_cuota' => '62267.71', 'cftea' => '1.1824', 'fecha_conformidad' => '2026-11-11', 'medio_conformidad' => 'PresencialFirma'],
            'aprobacion' => ['fecha' => '2026-11-11 13:10:00.000 +00:00', 'vence_aprobacion' => '2026-11-25 13:10:00.000 +00:00'],
            'liquidacion' => ['fecha' => '2026-11-11 15:00:00.000 +00:00', 'importe_neto' => '481850.00', 'credito_numero' => 21], 'credito' => ['id' => 9, 'numero' => 21, 'estado' => 'Activo'],
        ];
        $html = view('portal/solicitud', ['titulo' => 'Solicitud', 's' => $s]);
        $this->assertStringContainsString('Favorable', $html);
        $this->assertStringContainsString(site_url('portal/estado-cuenta/creditos/9'), $html);
    }

    public function testEstadoDeCuentaPlanYCuota(): void
    {
        $vencida = self::cuota(1, 13);
        $proxima = self::cuota(2, 0);
        $html = view('portal/estado_cuenta', [
            'titulo' => 'Estado de cuenta',
            'creditos' => [['id' => 9, 'numero' => 21, 'estado' => 'EnMora', 'capital' => '500000.00', 'cantidad_cuotas' => 12, 'fecha_liquidacion' => '2026-11-11 15:00:00.000 +00:00', 'linea' => 'Motos', 'impagas' => 12]],
            'deuda' => ['conceptos' => ['Punitorio' => '335.64', 'InteresOrdinario' => '50000.00', 'GastosCargos' => '1000.00', 'NoCapitalizable' => '0.00', 'IVA' => '10850.96', 'Capital' => '62825.42'], 'total' => '125012.02', 'pendientes' => 1, 'vencidas' => 1],
            'vencidas' => [$vencida], 'proximos' => [$proxima], 'saldo_disponible' => '1500.00', 'saldo_reservado' => '0.00',
            'ordenes' => [['id' => 4, 'numero' => 8, 'importe' => '62673.83', 'vence' => '2026-11-12 03:00:00.000 +00:00', 'estado' => 'PendienteConfirmacion', 'url_pago' => 'https://pasarela/8']],
            'morosidad_avanzada' => false,
        ]);
        $this->assertStringContainsString('13 días', $html);
        $this->assertStringContainsString(site_url('portal/estado-cuenta/creditos/9/cuotas/1'), $html);
        $this->assertStringContainsString('Saldo a favor', $html);
        $this->assertStringContainsString(site_url('portal/pagar/ordenes/4'), $html);
        $this->assertStringNotContainsString('Concepto no capitalizable', $html);

        $credito = ['id' => 9, 'numero' => 21, 'linea' => 'Motos', 'capital' => '500000.00', 'cantidad_cuotas' => 12, 'estado' => 'EnMora', 'tolerancia_dias' => 3];
        $html = view('portal/plan', ['titulo' => 'Crédito', 'credito' => $credito, 'cuotas' => [$vencida, $proxima, self::cuota(3, 0, 'Pagada')], 'cancelacion' => '470000.00', 'fecha' => '2026-11-11']);
        $this->assertStringContainsString('Cancelación anticipada', $html);
        $this->assertStringContainsString('Pagada', $html);

        $html = view('portal/cuota', ['titulo' => 'Cuota', 'credito' => $credito, 'cuota' => $vencida + ['situacion' => 'Vencida hace 13 día(s): superada la tolerancia.'], 'fecha' => '2026-11-11']);
        $this->assertStringContainsString('superada la tolerancia', $html);
        $this->assertStringContainsString('Capital pendiente', $html);
    }

    public function testComprobantesYComprobante(): void
    {
        $html = view('portal/comprobantes', ['titulo' => 'Comprobantes', 'cobros' => [[
            'id' => 3, 'numero' => 10, 'comprobante' => 15, 'fecha_hora' => '2026-11-11 16:00:00.000 +00:00', 'importe' => '62673.83', 'estado' => 'Confirmado',
            'canal' => 'Ventanilla', 'tipo' => 'Cuotas', 'medios' => 'Transferencia', 'cuotas' => '21/1',
        ]]]);
        $this->assertStringContainsString('Transferencia o depósito', $html);
        $this->assertStringContainsString(site_url('portal/comprobantes/3'), $html);

        $c = [
            'id' => 3, 'numero' => 10, 'comprobante' => 15, 'denominacion' => 'Díaz, Martina', 'cuit_cuil' => '27394445520', 'estado' => 'Anulado',
            'fecha_anulacion' => '2026-11-11 17:00:00.000 +00:00', 'motivo_anulacion' => 'Error de imputación', 'fecha_hora' => '2026-11-11 16:00:00.000 +00:00',
            'importe' => '62673.83', 'canal' => 'Ventanilla', 'cajero' => 'Romero, Paula', 'fecha_emision' => '2026-11-11 16:00:00.000 +00:00', 'cantidad_reemisiones' => 1,
            'medios' => [['medio' => 'Transferencia', 'numero_operacion' => 'TRF-DEMO-01', 'importe' => '62673.83']],
            'cuotas' => [['credito' => 21, 'cuota' => 1, 'vencimiento' => '2026-10-29', 'conceptos' => ['Punitorio' => '335.64', 'Capital' => '31412.71'], 'total' => '31748.35']],
        ];
        $html = view('portal/comprobante', ['titulo' => 'Comprobante', 'c' => $c, 'imprimir' => true]);
        $this->assertStringContainsString('Cobro anulado', $html);
        $this->assertStringContainsString(site_url('portal/comprobantes/3/descargar'), $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function testPagoElectronico(): void
    {
        $this->assertStringContainsString('No tiene cuotas pendientes', view('portal/pagar', ['titulo' => 'Pagar', 'creditos' => [], 'ordenes_pendientes' => []]));
        $enOrden = ['orden_pendiente' => 8] + self::cuota(2, 0);
        $html = view('portal/pagar', ['titulo' => 'Pagar', 'creditos' => [['id' => 9, 'numero' => 21, 'linea' => 'Motos', 'cuotas' => [self::cuota(1, 13), $enOrden]]], 'ordenes_pendientes' => [8]]);
        $this->assertStringContainsString('name="cuotas[9][]"', $html);
        $this->assertStringContainsString('En la orden N.º 8', $html);

        $o = ['numero' => 8, 'importe' => '62673.83', 'vence' => '2099-01-01 03:00:00.000 +00:00', 'estado' => 'PendienteConfirmacion', 'referencia_externa' => 'SIM-8', 'url_pago' => 'https://pasarela/8',
            'cuotas' => [['credito' => 21, 'numero' => 1, 'vencimiento' => '2026-10-29', 'importe' => '62673.83']]];
        $this->assertStringContainsString('Pagar en la pasarela', view('portal/orden', ['titulo' => 'Orden', 'o' => $o]));
        $this->assertStringContainsString('venció sin pago', view('portal/orden', ['titulo' => 'Orden', 'o' => ['estado' => 'Vencida'] + $o]));
    }

    public function testPreRegistroDelPortalYDelPanel(): void
    {
        $html = view('portal/pre_registro', ['titulo' => 'Pre-registrarse']);
        foreach (['nombre', 'apellido', 'tipo_documento', 'nro_documento', 'cuit_cuil', 'correo', 'telefono', 'acepta_politica'] as $campo) {
            $this->assertStringContainsString('name="' . $campo . '"', $html, $campo);
        }
        $this->assertStringContainsString('martina@prueba.local', view('portal/pre_registro_enviado', ['titulo' => 'Verifique', 'correo' => 'martina@prueba.local']));
        $this->assertStringContainsString(site_url('portal/pre-registro/verificar/abc'), view('portal/pre_registro_verificar', ['titulo' => 'Verificar', 'codigo' => 'abc', 'resultado' => null]));
        $this->assertStringContainsString(site_url('portal/pre-registro'), view('acceso/login', ['titulo' => 'Iniciar sesión', 'base' => 'portal']));

        $p = [
            'id' => 4, 'nombre' => 'Martina', 'apellido' => 'Díaz', 'tipo_documento' => 'DNI', 'nro_documento' => '39444552', 'cuit_cuil' => '27394445520',
            'correo' => 'martina.diaz@prueba.local', 'telefono' => null, 'creado_en' => '2026-11-11 12:00:00.000 +00:00', 'fecha_verificacion' => '2026-11-11 12:05:00.000 +00:00',
            'fecha_politica' => '2026-11-11 12:00:00.000 +00:00', 'estado' => 'PendienteValidacion', 'cliente_id' => null, 'cliente' => null, 'analista' => null,
            'motivo_descarte' => null, 'version_fila' => 2,
        ];
        $html = view('clientes/pre_registros', ['titulo' => 'Pre-registros', 'estado' => 'PendienteValidacion', 'texto' => '', 'pre' => [$p]]);
        $this->assertStringContainsString(site_url('admin/pre-registros/4'), $html);
        $html = view('clientes/pre_registro', ['titulo' => 'Pre-registro', 'p' => $p]);
        $this->assertStringContainsString('?pre=4', $html);
        $this->assertStringContainsString(site_url('admin/pre-registros/4/descartar'), $html);
    }
}
