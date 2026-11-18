<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use App\Services\Reportes\ReporteService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-64 a IN-68 · Reportes, tablero, caja diaria, auditoría y régimen informativo (iteración 4, bloque C): tablero
 * gerencial (CU-55), reportes de gestión (CU-65 a CU-71), informe de caja diaria (CU-56), consulta de la auditoría
 * (CU-57) y régimen informativo de deudores (CU-105). Plan de pruebas 7.9.2 y Anexo B.17.
 *
 * Son consultas: cada criterio se prueba con cada clase de dato y la respuesta debe ser la pantalla o el formulario
 * con el mensaje del criterio, nunca un error 500. Ninguna consulta modifica las operaciones (RN-47).
 *
 * @internal
 */
final class ReportesTest extends IntegracionTestCase
{
    /** @return array<string, mixed> valor de cada clase para un criterio libre (no fecha) */
    private static function clasesDeCriterio(): array
    {
        return [
            'vacío' => '', 'como lista (COM-02)' => ['1'], 'código HTML (COM-03)' => '<b>1</b><script>alert(1)</script>',
            'texto SQL (COM-04)' => "1'); DROP TABLE creditos;--", 'negativo' => '-1', 'decimal' => '1.5', 'inexistente' => '999999999',
            'mayor que INT' => '2147483648', 'muy grande' => '99999999999999999999', 'emoji' => '😀', 'largo' => str_repeat('x', 5000),
        ];
    }

    /** @return array<string, mixed> valor de cada clase para una fecha */
    private static function clasesDeFecha(): array
    {
        return [
            'vacía' => '', 'como lista' => ['01/01/2026'], 'inválida' => '31/02/2026', 'texto' => 'ayer', 'año 1899' => '31/12/1899',
            'año 1900' => '01/01/1900', 'año 9999' => '31/12/9999', 'formato ISO' => '2026-01-15', 'texto SQL' => "2026-01-01'; DROP TABLE creditos;--",
        ];
    }

    /** La respuesta es la pantalla (200) o vuelve al formulario con un mensaje: nunca un error. */
    private function assertConsultaAtendida(\CodeIgniter\Test\TestResponse $r, string $contexto): void
    {
        if ($r->isRedirect()) {
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), "{$contexto}: al volver debe informar el motivo");

            return;
        }
        $this->assertSame(200, $r->response()->getStatusCode(), $contexto);
    }

    // ── IN-64 Reportes de gestión (CU-65 a CU-71) ────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string, 2: mixed}> «reporte · clase» => [reporte, tipo de clase, valor] */
    public static function criteriosDeReportes(): array
    {
        $r = [];
        foreach (array_keys(ReporteService::REPORTES) as $reporte) {
            foreach (self::clasesDeCriterio() as $clase => $valor) {
                $r["{$reporte} · criterios {$clase}"] = [$reporte, 'criterios', $valor];
            }
            foreach (self::clasesDeFecha() as $clase => $valor) {
                $r["{$reporte} · fecha {$clase}"] = [$reporte, 'fecha', $valor];
            }
            foreach (['vacío' => '', 'inexistente' => 'anual', 'como lista' => ['mes'], 'otras mayúsculas' => 'MES'] as $clase => $valor) {
                $r["{$reporte} · tipo {$clase}"] = [$reporte, 'tipo', $valor];
            }
        }

        return $r;
    }

    /**
     * IN-64: cada reporte con cada clase de dato en sus criterios (todos los criterios no fecha a la vez), en la fecha
     * de referencia con cada tipo de período, y en el tipo de período.
     *
     * @dataProvider criteriosDeReportes
     */
    public function testReporteConCadaClaseDeCriterio(string $reporte, string $clase, mixed $valor): void
    {
        $this->comoRol('Gerente');
        $ruta = 'admin/reportes/' . $reporte;
        if ($clase === 'criterios') {
            $criterios = array_fill_keys(['linea', 'estado', 'riesgo', 'antiguedad', 'cliente', 'analista', 'caja', 'cajero', 'medio', 'canal', 'categoria', 'campana'], $valor);
            $this->assertConsultaAtendida($this->pedir($ruta, ['generar' => '1'] + $criterios), "{$reporte} criterios");

            return;
        }
        if ($clase === 'tipo') {
            $this->assertConsultaAtendida($this->pedir($ruta, ['generar' => '1', 'tipo' => $valor]), "{$reporte} tipo");

            return;
        }
        foreach (['dia', 'semana', 'mes', 'trimestre'] as $tipo) {
            $this->assertConsultaAtendida($this->pedir($ruta, ['generar' => '1', 'tipo' => $tipo, 'fecha' => $valor]), "{$reporte} {$tipo} fecha");
        }
        foreach (['desde', 'hasta'] as $extremo) {
            $this->assertConsultaAtendida($this->pedir($ruta, ['generar' => '1', 'tipo' => 'rango', $extremo => $valor]), "{$reporte} rango {$extremo}");
        }
    }

    /** IN-64 A2: rango incoherente, de más de dos años y proyección que empieza en el pasado; formato Excel. */
    public function testReportesPeriodoYFormato(): void
    {
        $this->comoRol('Gerente');
        $this->pedir('admin/reportes/cartera', ['generar' => '1', 'tipo' => 'rango', 'desde' => '10/01/2026', 'hasta' => '01/01/2026']);
        $this->assertSame('La fecha final no puede ser anterior a la inicial (A2).', (string) (($_SESSION['errores'] ?? [])['hasta'] ?? ''));
        $this->pedir('admin/reportes/cartera', ['generar' => '1', 'tipo' => 'rango', 'desde' => '01/01/2020', 'hasta' => '01/01/2026']);
        $this->assertSame('El rango no puede superar los dos años.', (string) (($_SESSION['errores'] ?? [])['hasta'] ?? ''));
        $ayer = Time::now(config('Sigccr')->zonaNegocio)->subDays(1)->format('d/m/Y');
        $this->pedir('admin/reportes/proyeccion', ['generar' => '1', 'tipo' => 'rango', 'desde' => $ayer]);
        $this->assertSame('La proyección no puede empezar antes de hoy: elija un período futuro (A2).', (string) (($_SESSION['errores'] ?? [])['desde'] ?? ''));

        foreach (array_keys(ReporteService::REPORTES) as $reporte) {
            $r = $this->pedir("admin/reportes/{$reporte}", ['generar' => '1', 'formato' => 'xlsx']);
            $r->assertOK();
            $this->assertStringStartsWith('application/vnd.openxmlformats', $r->response()->getHeaderLine('Content-Type'), $reporte);
            $this->assertStringStartsWith('PK', (string) $r->response()->getBody(), "{$reporte}: el archivo es un xlsx");
        }
        $this->pedir('admin/reportes/inexistente', ['generar' => '1']);
        $this->assertSame('El reporte no existe.', (string) ($_SESSION['error'] ?? ''));
    }

    // ── IN-65 Tablero gerencial (CU-55) ──────────────────────────────────────────────────────────────────────────

    public function testTableroConCadaClaseDeCriterio(): void
    {
        $this->comoRol('Gerente');
        foreach (['vacío' => '', 'inexistente' => 'anual', 'como lista (H-15)' => ['mes'], 'código HTML' => '<b>mes</b>'] as $clase => $valor) {
            $this->assertConsultaAtendida($this->pedir('admin/tablero', ['tipo' => $valor]), "tipo {$clase}");
        }
        foreach (self::clasesDeFecha() as $clase => $valor) {
            foreach (['dia', 'semana', 'mes', 'trimestre'] as $tipo) {
                $this->assertConsultaAtendida($this->pedir('admin/tablero', ['tipo' => $tipo, 'fecha' => $valor]), "{$tipo} fecha {$clase}");
            }
            foreach (['desde', 'hasta'] as $extremo) {
                $this->assertConsultaAtendida($this->pedir('admin/tablero', ['tipo' => 'rango', $extremo => $valor]), "rango {$extremo} {$clase}");
            }
        }
        $this->pedir('admin/tablero', ['tipo' => 'rango', 'desde' => '10/01/2026', 'hasta' => '01/01/2026']);
        $this->assertSame('La fecha final no puede ser anterior a la inicial (A2).', (string) (($_SESSION['errores'] ?? [])['hasta'] ?? ''));
    }

    // ── IN-66 Informe de caja diaria (CU-56) ─────────────────────────────────────────────────────────────────────

    public function testCajaDiariaConCadaClaseDeCriterio(): void
    {
        $this->comoRol('Gerente');
        foreach (self::clasesDeCriterio() as $clase => $valor) {
            $this->assertConsultaAtendida($this->pedir('admin/caja-diaria', ['caja' => $valor, 'cajero' => $valor]), "caja y cajero {$clase}");
        }
        foreach (self::clasesDeFecha() as $clase => $valor) {
            foreach (['desde', 'hasta'] as $extremo) {
                $this->assertConsultaAtendida($this->pedir('admin/caja-diaria', [$extremo => $valor]), "{$extremo} {$clase}");
            }
        }

        // A2: el Cajero solo consulta sus jornadas
        $otro = $this->fabrica->usuario('Cajero');
        $this->comoRol('Cajero');
        $this->pedir('admin/caja-diaria')->assertOK();
        $r = $this->pedir('admin/caja-diaria', ['cajero' => (string) $otro['id']]);
        $r->assertOK();
        $this->assertStringContainsString('Solo puede consultar sus propias jornadas', (string) $r->response()->getBody());
    }

    // ── IN-67 Registro de auditoría (CU-57) ──────────────────────────────────────────────────────────────────────

    public function testAuditoriaConCadaClaseDeCriterio(): void
    {
        $this->comoRol('Administrador');
        foreach (['responsable', 'categoria', 'objeto', 'resultado', 'pagina'] as $criterio) {
            foreach (self::clasesDeCriterio() as $clase => $valor) {
                $this->assertConsultaAtendida($this->pedir('admin/auditoria', [$criterio => $valor]), "{$criterio} {$clase}");
            }
        }
        foreach (self::clasesDeFecha() as $clase => $valor) {
            foreach (['desde', 'hasta'] as $extremo) {
                $this->assertConsultaAtendida($this->pedir('admin/auditoria', [$extremo => $valor]), "{$extremo} {$clase}");
            }
        }
        $manana = Time::now(config('Sigccr')->zonaNegocio)->addDays(1)->format('d/m/Y');
        $this->pedir('admin/auditoria', ['hasta' => $manana]);
        $this->assertSame('La fecha final no puede ser posterior a hoy.', (string) (($_SESSION['errores'] ?? [])['hasta'] ?? ''));

        // El responsable se busca como texto literal: % y _ no son comodines
        $this->assertStringContainsString('No hay registros', (string) $this->pedir('admin/auditoria', ['responsable' => '%_%' . bin2hex(random_bytes(4))])->response()->getBody());
        $id = $this->ultimoId('registros_auditoria');
        $this->pedir("admin/auditoria/{$id}")->assertOK();
        $this->pedir("admin/auditoria/{$id}", ['volver' => ['x']])->assertOK();
        $r = $this->pedir('admin/auditoria/999999999');
        $this->assertTrue($r->isRedirect());
        $this->assertSame('El registro de auditoría no existe.', (string) ($_SESSION['error'] ?? ''));
    }

    // ── IN-68 Régimen informativo de deudores (CU-105) ───────────────────────────────────────────────────────────

    public function testRegimenInformativo(): void
    {
        $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $actual = $hoy->format('Y-m');
        // Un período cerrado distinto en cada ejecución de la suite, para no rectificar uno de otra prueba
        $periodo = sprintf('19%02d-%02d', random_int(0, 99), random_int(1, 12));
        $cerrado = 'Seleccione un mes ya cerrado.';
        $this->assertRechazos('admin/regimen', ['periodo' => $periodo], [
            'período vacío' => [['periodo' => ''], 'periodo', $cerrado],
            'período ausente' => [['periodo' => Esperado::AUSENTE], 'periodo', $cerrado],
            'período como lista' => [['periodo' => [$periodo]], 'periodo', $cerrado],
            'mes 13' => [['periodo' => '2025-13'], 'periodo', $cerrado],
            'mes 00' => [['periodo' => '2025-00'], 'periodo', $cerrado],
            'mes en curso' => [['periodo' => $actual], 'periodo', $cerrado],
            'mes futuro' => [['periodo' => $hoy->addMonths(1)->format('Y-m')], 'periodo', $cerrado],
            'año 0000 (H-30)' => [['periodo' => '0000-01'], 'periodo', $cerrado],
            'año 1899' => [['periodo' => '1899-12'], 'periodo', $cerrado],
            'formato local' => [['periodo' => '10/2025'], 'periodo', $cerrado],
            'con espacios internos' => [['periodo' => '2025 -10'], 'periodo', $cerrado],
            'texto SQL' => [['periodo' => "2025-10'; DROP TABLE regimenes_informativos;--"], 'periodo', $cerrado],
        ], ['regimenes_informativos']);

        $this->enviar('admin/regimen', ['periodo' => " {$periodo} "]);
        $id = $this->ultimoId('regimenes_informativos');
        $r = $this->db->table('regimenes_informativos')->where('id', $id)->get()->getRowArray();
        $this->assertSame($periodo, $r['periodo']);
        $this->assertSame('Vigente', $r['estado']);

        // Rectificación: el motivo es obligatorio y admite hasta 500 caracteres (H-30)
        $this->assertRechazos('admin/regimen', ['periodo' => $periodo], [
            'sin motivo' => [['motivo' => ''], 'motivo', 'Indique el motivo de la rectificación.'],
            'motivo como lista' => [['motivo' => ['Prueba de rectificación']], 'motivo', 'Indique el motivo de la rectificación.'],
            'motivo de 4' => [['motivo' => 'Baja'], 'motivo', 'Indique el motivo de la operación (al menos 5 caracteres).'],
            'motivo de 501' => [['motivo' => str_repeat('m', 501)], 'motivo', 'El motivo admite hasta 500 caracteres.'],
            'motivo de 251 emojis (H-16)' => [['motivo' => str_repeat('😀', 251)], 'motivo', 'El motivo admite hasta 500 caracteres.'],
        ], ['regimenes_informativos']);
        $this->assertSame('Vigente', $this->db->table('regimenes_informativos')->where('id', $id)->get()->getRow()->estado);
        $this->enviar('admin/regimen', ['periodo' => $periodo, 'motivo' => str_repeat('😀', 250)]);
        $this->assertSame($id + 1, $this->ultimoId('regimenes_informativos'));
        $this->assertSame('Rectificado', $this->db->table('regimenes_informativos')->where('id', $id)->get()->getRow()->estado);

        $this->pedir("admin/regimen/{$id}")->assertOK();
        $archivo = $this->pedir('admin/regimen/' . ($id + 1) . '/archivo');
        $archivo->assertOK();
        $this->assertStringStartsWith("CAB;{$periodo};2;", (string) $archivo->response()->getBody());
        foreach (['admin/regimen/999999999', 'admin/regimen/999999999/archivo'] as $ruta) {
            $this->assertTrue($this->pedir($ruta)->isRedirect(), $ruta);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), $ruta);
        }
    }
}
