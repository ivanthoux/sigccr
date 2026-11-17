<?php

declare(strict_types=1);

namespace Tests\Rutas;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * C03 · Las pantallas de acceso compartidas (CU-01, CU-02) envían cada formulario al ámbito que las muestra: una
 * cuenta del portal nunca se autentica contra el panel ni a la inversa (RN-08). Se renderizan sin base de datos.
 *
 * @internal
 */
final class VistasAccesoTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        service('routes')->loadRoutes();
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function ambitos(): array
    {
        return ['panel' => ['admin', 'admin/', 'portal/'], 'portal' => ['portal', 'portal/', 'admin/']];
    }

    /** @dataProvider ambitos */
    public function testLoginRecuperarYRestablecerApuntanAlAmbito(string $base, string $propio, string $ajeno): void
    {
        $vistas = [
            view('acceso/login', ['titulo' => 'Iniciar sesión', 'base' => $base]),
            view('acceso/recuperar', ['titulo' => 'Recuperar acceso', 'base' => $base]),
            view('acceso/restablecer', ['titulo' => 'Nueva contraseña', 'codigo' => 'abc', 'base' => $base]),
        ];
        foreach ($vistas as $html) {
            preg_match_all('/<form[^>]+action="([^"]+)"/', $html, $m);
            $this->assertNotEmpty($m[1]);
            foreach ($m[1] as $accion) {
                $this->assertStringContainsString('/' . $propio, $accion);
                $this->assertStringNotContainsString('/' . $ajeno, $accion);
            }
        }
        $this->assertStringContainsString(site_url($base . '/recuperar'), $vistas[0]);
        $this->assertStringContainsString(site_url($base . '/restablecer/abc'), $vistas[2]);
    }

    public function testLaPortadaOrientaAlPortalYOfreceElAccesoDelPersonal(): void
    {
        $html = view('portada', ['titulo' => 'Inicio', 'continuar' => null]);
        foreach (['portal/login', 'portal/activar', 'portal/recuperar', 'admin/login'] as $ruta) {
            $this->assertStringContainsString('href="' . site_url($ruta) . '"', $html, $ruta);
        }
        // Sin formularios ni datos: solo orienta (la autenticación ocurre en cada ámbito)
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('Portal de autogestión de clientes.', $html);
        $this->assertStringEndsWith('Portada::index', (string) (service('routes')->getRoutes('GET')['/'] ?? ''));
    }

    public function testElLoginDelPortalOfreceActivarElAcceso(): void
    {
        $this->assertStringContainsString(site_url('portal/activar'), view('acceso/login', ['base' => 'portal']));
        $this->assertStringNotContainsString(site_url('portal/activar'), view('acceso/login', ['base' => 'admin']));
    }

    public function testInicioDelPortalMuestraLasFuncionesComoProximas(): void
    {
        session()->set('cuenta', ['tipo' => 'Cliente', 'nombre' => 'Ana Pérez']);
        $html = view('portal/inicio', ['titulo' => 'Inicio', 'ultimoAcceso' => null, 'funciones' => [
            ['icono' => 'bi-wallet2', 'titulo' => 'Estado de cuenta', 'texto' => 'CU-47', 'ruta' => 'portal.estado-cuenta', 'url' => null],
            ['icono' => 'bi-person-vcard', 'titulo' => 'Mi perfil', 'texto' => 'CU-45', 'ruta' => 'portal.perfil', 'url' => 'https://x/portal/perfil'],
        ]]);
        $this->assertStringContainsString('Hola, Ana Pérez', $html);
        $this->assertStringContainsString('Es su primer ingreso al portal.', $html);
        $this->assertSame(1, substr_count($html, 'Próximamente'));
        $this->assertStringContainsString('href="' . esc('https://x/portal/perfil', 'attr') . '"', $html);
        $this->assertStringContainsString(site_url('portal/salir'), $html);
        $this->assertStringNotContainsString('Su cuenta hoy', $html, 'Sin resumen (cliente sin créditos o consulta fallida) no se muestra el bloque');
    }

    public function testInicioDelPortalResumeLaCuentaDelCliente(): void
    {
        session()->set('cuenta', ['tipo' => 'Cliente', 'nombre' => 'Ana Pérez']);
        $vencida = ['vencimiento' => '2026-10-10', 'total' => \App\Libraries\Finanzas\Decimal::of('70000.50')];
        $proxima = ['vencimiento' => '2026-12-10', 'total' => \App\Libraries\Finanzas\Decimal::of('62267.71')];
        $html = view('portal/inicio', ['titulo' => 'Inicio', 'ultimoAcceso' => null, 'funciones' => [], 'resumen' => [
            'creditos' => [['id' => 1]], 'deuda' => ['total' => '721372.45', 'conceptos' => [], 'pendientes' => 12, 'vencidas' => 1],
            'vencidas' => [$vencida], 'proximos' => [$proxima], 'saldo_disponible' => '0.00', 'saldo_reservado' => '0.00',
        ]]);
        $this->assertStringContainsString('Su cuenta hoy', $html);
        $this->assertStringContainsString('1 cuota vencida', $html);
        $this->assertStringContainsString('10/12/2026', $html);
        $this->assertStringContainsString(esc(formato_importe('721372.45')), $html);
        $this->assertStringContainsString(site_url('portal/pagar'), $html);
        $this->assertStringNotContainsString('Saldo a favor', $html);
    }
}
