<?php

declare(strict_types=1);

namespace Tests\Rutas;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pantallas de las copias de seguridad (CU-61 a CU-63) y del modo mantenimiento de la restauración, renderizadas con
 * datos de ejemplo, sin base de datos (D-41, D-45).
 *
 * @internal
 */
final class VistasCopiasTest extends CIUnitTestCase
{
    private const ARCHIVO = 'sigccr_20261112_050005_manual_ab12.sccr';

    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        service('routes')->loadRoutes();
        session()->set('cuenta', ['id' => 1, 'tipo' => 'Interna', 'nombre' => 'Administrador', 'rol' => 'Administrador', 'permisos' => ['copias.generar', 'copias.descargar', 'copias.restaurar']]);
    }

    /** @return array<string, mixed> */
    private static function copia(string $estado = 'Verificada'): array
    {
        return [
            'id' => 7, 'archivo' => self::ARCHIVO, 'fecha_generacion' => '2026-11-12 05:00:05.000 +00:00', 'tamano_bytes' => 1572864, 'origen' => 'Manual', 'hash_verificacion' => str_repeat('c', 64),
            'estado' => $estado, 'usuario_id' => 1, 'descripcion' => 'Antes del cierre de mes', 'responsable' => 'Pérez, Ana', 'incluye_adjuntos' => 1, 'fecha_fin' => '2026-11-12 05:01:00.000 +00:00',
            'detalle' => $estado === 'Fallida' ? 'El volumen de copias no tiene espacio suficiente (A2).' : null, 'restauraciones' => 0, 'disponible' => $estado === 'Verificada', 'historial' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function destino(bool $enCurso = false): array
    {
        return ['ruta' => '/var/opt/sigccr/backups', 'disponible' => true, 'libre' => 10737418240, 'en_curso' => $enCurso, 'restauracion' => false, 'adjuntos' => true];
    }

    public function testListadoYGeneracion(): void
    {
        $html = view('copias/index', ['titulo' => 'Copias', 'copias' => [], 'destino' => self::destino(), 'ultima' => null]);
        $this->assertStringContainsString('Todavía no se generó', $html);
        $this->assertStringContainsString('name="descripcion"', $html);
        $this->assertStringContainsString('/var/opt/sigccr/backups', $html);
        $this->assertStringContainsString('10,0 GB libres', $html);
        $this->assertStringNotContainsString('btn-primary" disabled', $html);
        $html = view('copias/index', ['titulo' => 'Copias', 'copias' => [self::copia(), ['id' => 8, 'origen' => 'Automatica', 'responsable' => 'Proceso copias:generar', 'descripcion' => null] + self::copia('Fallida')], 'destino' => self::destino(true), 'ultima' => [
            'exitosa' => true, 'mensaje' => 'Base restaurada al 12/11/2026 02:00.', 'responsable' => 'Pérez, Ana', 'fin' => '2026-11-12 15:00:00.000 +00:00', 'posteriores' => ['3 cobros'], 'copia_previa' => 'sigccr_x_previa_1.sccr',
        ]]);
        $this->assertStringContainsString('(A1)', $html);
        $this->assertStringContainsString('btn-primary" disabled', $html);
        $this->assertStringContainsString('setTimeout', $html);
        $this->assertStringContainsString('1,5 MB', $html);
        $this->assertStringContainsString('Automática', $html);
        $this->assertStringContainsString('espacio suficiente (A2)', $html);
        $this->assertStringContainsString('3 cobros', $html);
        $this->assertStringContainsString(url_to('copias.descargar', 7), $html);
    }

    public function testDetalleYDescarga(): void
    {
        $html = view('copias/ver', ['titulo' => 'Copia', 'c' => self::copia()]);
        $this->assertStringContainsString(str_repeat('c', 64), $html);
        $this->assertStringContainsString(site_url('admin/copias/7/descargar'), $html);
        $this->assertStringContainsString(url_to('copias.restaurar', 7), $html);
        $this->assertStringContainsString('archivos del legajo', $html);
        $this->assertStringContainsString('copias:descifrar', $html);
        $corrupta = view('copias/ver', ['titulo' => 'Copia', 'c' => self::copia('Corrupta')]);
        $this->assertStringContainsString('(A1)', $corrupta);
        $this->assertStringNotContainsString('/descargar', $corrupta);
        $this->assertStringContainsString('setTimeout', view('copias/ver', ['titulo' => 'Copia', 'c' => self::copia('Solicitada')]));
        session()->set('cuenta', ['permisos' => ['copias.descargar']] + session('cuenta'));
        $this->assertStringNotContainsString('Restaurar esta copia', view('copias/ver', ['titulo' => 'Copia', 'c' => self::copia()]));
    }

    public function testConfirmacionDeLaRestauracion(): void
    {
        $html = view('copias/restaurar', ['titulo' => 'Restaurar', 'c' => self::copia()]);
        $this->assertStringContainsString('name="confirmacion"', $html);
        $this->assertStringContainsString('name="sin_respaldo"', $html);
        $this->assertStringContainsString('RESTAURAR', $html);
        $this->assertStringContainsString('Se reemplazará la información actual', $html);
        $this->assertStringContainsString(site_url('admin/copias/7/restaurar'), $html);
    }

    public function testModoMantenimiento(): void
    {
        $html = view('errores/restauracion', ['estado' => ['estado' => 'EnCurso', 'etapa' => 'Restaurando la base de datos']]);
        $this->assertStringContainsString('Sistema en mantenimiento', $html);
        $this->assertStringContainsString('Restaurando la base de datos', $html);
        $this->assertStringContainsString('setTimeout', $html);
        $fin = view('errores/restauracion', ['estado' => null, 'ultima' => ['exitosa' => false, 'mensaje' => 'La copia seleccionada está corrupta o incompleta: no se restauró. Seleccione otra copia (A1).']]);
        $this->assertStringContainsString('no realizada', $fin);
        $this->assertStringContainsString('(A1)', $fin);
        $this->assertStringContainsString(site_url('admin/login'), $fin);
        $this->assertStringContainsString('Sin restauraciones en curso', view('errores/restauracion', ['estado' => null, 'ultima' => null]));
    }
}
