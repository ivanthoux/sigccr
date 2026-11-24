<?php

declare(strict_types=1);

namespace Tests\Rutas;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pantallas de CU-108 (derechos del titular de los datos personales, D-64) renderizadas con datos de ejemplo, sin base
 * de datos, para detectar errores de las vistas antes de la batería de punta a punta.
 *
 * @internal
 */
final class VistasDerechosTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        service('routes')->loadRoutes();
        session()->set('cuenta', ['id' => 2, 'tipo' => 'UsuarioInterno', 'nombre' => 'Analista', 'cliente_id' => 3, 'permisos' => ['derechos.atender', 'solicitudes.resolver', 'clientes.consultar-perfil']]);
    }

    /** @return array<string, mixed> */
    private static function solicitud(string $tipo = 'Acceso', string $estado = 'Pendiente'): array
    {
        $resuelta = $estado !== 'Pendiente';

        return [
            'id' => 8, 'cliente_id' => 3, 'cliente' => 'Díaz, Martina', 'tipo' => $tipo, 'detalle' => "Quiero ver mis datos\ny corregir mi domicilio.", 'solicitud_id' => $tipo === 'RevisionAutomatizada' ? 40 : null,
            'solicitud_numero' => $tipo === 'RevisionAutomatizada' ? 33 : null, 'canal' => 'Portal', 'estado' => $estado, 'vence' => '2026-12-03', 'creado_en' => '2026-11-23 12:00:00.000 +00:00',
            'fecha_resolucion' => $resuelta ? '2026-11-25 12:00:00.000 +00:00' : null, 'respuesta' => $resuelta ? 'Se entregó la copia de sus datos.' : null, 'responsable' => $resuelta ? 'Pérez, Ana' : null,
            'situacion' => $resuelta ? null : 'PorVencer', 'version_fila' => 1,
        ];
    }

    public function testPortalMuestraElFormularioYLasSolicitudes(): void
    {
        $vacia = view('portal/derechos', ['titulo' => 'Derechos', 'solicitudes' => [], 'revisables' => []]);
        foreach (['name="tipo"', 'name="detalle"', 'name="solicitud_id"', 'Todavía no hizo ninguna solicitud', 'data-leyenda-aaip', site_url('politica-de-datos')] as $esperado) {
            $this->assertStringContainsString($esperado, $vacia, $esperado);
        }
        $this->assertStringContainsString('No tiene solicitudes rechazadas por motivos crediticios', $vacia);
        $pendiente = self::solicitud('RevisionAutomatizada') + ['solicitud_numero' => 33];
        $html = view('portal/derechos', ['titulo' => 'Derechos', 'solicitudes' => [$pendiente, self::solicitud('Acceso', 'Atendida')], 'revisables' => [['id' => 40, 'numero' => 33, 'fecha_registro' => '2026-11-10 10:00:00.000 +00:00']]]);
        $this->assertStringContainsString('Revisión de una decisión automatizada (solicitud N.º 33)', $html);
        $this->assertStringContainsString('Se entregó la copia de sus datos.', $html);
        $this->assertStringContainsString('N.º 33', $html);
    }

    public function testPanelListaPorVencimiento(): void
    {
        $this->assertStringContainsString('No hay solicitudes con esos criterios', view('derechos/index', ['titulo' => 'Derechos', 'estado' => 'Pendiente', 'tipo' => '', 'solicitudes' => []]));
        $html = view('derechos/index', ['titulo' => 'Derechos', 'estado' => 'Pendiente', 'tipo' => '', 'solicitudes' => [self::solicitud(), self::solicitud('Supresion', 'Denegada')]]);
        $this->assertStringContainsString(site_url('admin/derechos/8'), $html);
        $this->assertStringContainsString('Por vencer', $html);
        $this->assertStringContainsString('Supresión de datos', $html);
    }

    public function testPanelDetalleYRespuesta(): void
    {
        $pendiente = view('derechos/ver', ['titulo' => 'Solicitud', 's' => self::solicitud(), 'puedeRevisar' => false]);
        foreach (['name="estado"', 'name="respuesta"', 'name="version_fila"', site_url('admin/derechos/8/resolver'), 'Quiero ver mis datos'] as $esperado) {
            $this->assertStringContainsString($esperado, $pendiente, $esperado);
        }
        $this->assertStringContainsString('Entregue una copia de los datos', $pendiente, 'El acceso indica cómo responder.');

        $revision = self::solicitud('RevisionAutomatizada');
        $this->assertStringContainsString('la resuelve el Gerente', view('derechos/ver', ['titulo' => 'Solicitud', 's' => $revision, 'puedeRevisar' => false]));
        $this->assertStringNotContainsString('name="respuesta"', view('derechos/ver', ['titulo' => 'Solicitud', 's' => $revision, 'puedeRevisar' => false]));
        $this->assertStringContainsString('name="respuesta"', view('derechos/ver', ['titulo' => 'Solicitud', 's' => $revision, 'puedeRevisar' => true]));

        $resuelta = view('derechos/ver', ['titulo' => 'Solicitud', 's' => self::solicitud('Acceso', 'Atendida'), 'puedeRevisar' => true]);
        $this->assertStringContainsString('Se entregó la copia de sus datos.', $resuelta);
        $this->assertStringNotContainsString('name="respuesta"', $resuelta);
    }

    public function testPanelRegistraUnaSolicitudDeLaSucursal(): void
    {
        $html = view('derechos/nueva', ['titulo' => 'Registrar', 'c' => ['id' => 3, 'denominacion' => 'Díaz, Martina'], 'revisables' => []]);
        foreach (['name="cliente_id"', 'name="tipo"', 'name="detalle"', site_url('admin/derechos'), 'Díaz, Martina'] as $esperado) {
            $this->assertStringContainsString($esperado, $html, $esperado);
        }
    }
}
