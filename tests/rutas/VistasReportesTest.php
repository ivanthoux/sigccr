<?php

declare(strict_types=1);

namespace Tests\Rutas;

use App\Libraries\Reportes\Periodo;
use App\Libraries\Reportes\Indicadores;
use App\Services\Reportes\AuditoriaConsultaService;
use App\Services\Reportes\ReporteService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pantallas de SS-09 (CU-55 a CU-57, CU-65 a CU-71) y del régimen informativo (CU-105) renderizadas con datos de
 * ejemplo, sin base de datos (D-41, D-44).
 *
 * @internal
 */
final class VistasReportesTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'sigccr']);
        service('routes')->loadRoutes();
        $permisos = ['tablero.consultar', 'cajas.informe-diario', 'auditoria.consultar', 'regimen.consultar', 'cajas.consultar-movimientos', ...array_column(ReporteService::REPORTES, 2)];
        session()->set('cuenta', ['id' => 2, 'tipo' => 'Interna', 'nombre' => 'Gerente', 'rol' => 'Gerente', 'permisos' => $permisos]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function opciones(): array
    {
        return ['lineas' => [['id' => 4, 'nombre' => 'Personal']], 'analistas' => [['id' => 7, 'nombre' => 'Gómez, Ana']], 'cajeros' => [['id' => 8, 'nombre' => 'Ruiz, Juan']], 'cajas' => [['id' => 1, 'nombre' => 'C01 · Central']]];
    }

    /** @return array<string, mixed> */
    private static function reporte(): array
    {
        return [
            'titulo' => 'Reporte de cartera crediticia', 'cu' => 'CU-65', 'periodo' => Periodo::desde([], '2026-11-12'), 'referencia' => '2026-11-12 15:00:00.000+00:00',
            'filtros' => ['Línea: Personal'], 'aviso' => null,
            'kpis' => [['Créditos activos', 1234, 'entero'], ['Saldo deudor total', '1500000.50', 'importe'], ['Índice de morosidad (RN-74)', '12.50', 'porcentaje']],
            'secciones' => [
                ['titulo' => 'Distribución por línea', 'columnas' => [['Línea', 'texto'], ['Créditos', 'entero'], ['Capital', 'importe'], ['Liquidación', 'fecha']], 'filas' => [['Personal', 3, '500000.00', '2026-11-10 13:30:00.000 +00:00']], 'nota' => 'Nota de prueba.'],
                ['titulo' => 'Vacía', 'columnas' => [['Texto', 'texto']], 'filas' => []],
            ],
        ];
    }

    public function testIndiceDeReportes(): void
    {
        $html = view('reportes/index', ['titulo' => 'Reportes', 'reportes' => ReporteService::REPORTES]);
        foreach (ReporteService::REPORTES as [$cu, $titulo, $permiso]) {
            $this->assertStringContainsString($cu, $html);
            $this->assertStringContainsString(url_to($permiso), $html);
        }
    }

    public function testCriteriosDeCadaReporte(): void
    {
        $campos = [
            'cartera' => ['tipo', 'fecha', 'desde', 'hasta', 'linea', 'estado', 'riesgo', 'cliente'],
            'morosidad' => ['antiguedad', 'riesgo', 'cliente'],
            'cobranzas' => ['caja', 'cajero', 'medio', 'cliente'],
            'solicitudes' => ['estado', 'analista', 'cliente'],
            'refinanciaciones' => ['estado', 'analista'],
            'proyeccion' => ['desde', 'hasta', 'linea', 'campana'],
            'campanas' => ['estado', 'canal', 'categoria'],
        ];
        foreach ($campos as $clave => $lista) {
            $html = view('reportes/reporte', ['titulo' => ReporteService::REPORTES[$clave][1], 'clave' => $clave, 'f' => [], 'opciones' => self::opciones(), 'r' => null]);
            foreach ($lista as $c) {
                $this->assertStringContainsString('name="' . $c . '"', $html, "{$clave}: {$c}");
            }
            $this->assertStringContainsString('name="generar"', $html);
            $this->assertStringNotContainsString('Exportar a Excel', $html);
        }
        $this->assertStringNotContainsString('name="fecha"', view('reportes/reporte', ['titulo' => 'P', 'clave' => 'proyeccion', 'f' => [], 'opciones' => self::opciones(), 'r' => null]));
        $this->assertStringContainsString('Portal (ofertas)', view('reportes/reporte', ['titulo' => 'S', 'clave' => 'solicitudes', 'f' => [], 'opciones' => self::opciones(), 'r' => null]));
    }

    public function testReporteGenerado(): void
    {
        $html = view('reportes/reporte', ['titulo' => 'Cartera', 'clave' => 'cartera', 'f' => ['linea' => '4', 'tipo' => 'mes'], 'opciones' => self::opciones(), 'r' => self::reporte()]);
        $this->assertStringContainsString('del 01/11/2026 al 30/11/2026', $html);
        $this->assertStringContainsString('Criterios: Línea: Personal', $html);
        $this->assertStringContainsString('1.234', $html);
        $this->assertStringContainsString('$ 1.500.000,50', $html);
        $this->assertStringContainsString('12,50 %', $html);
        $this->assertStringContainsString('500.000,00', $html);
        $this->assertStringContainsString('10/11/2026', $html);
        $this->assertStringContainsString('Nota de prueba.', $html);
        $this->assertStringContainsString('Sin datos para el período', $html);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertMatchesRegularExpression('/formato=xlsx/', $html);
        $this->assertMatchesRegularExpression('/linea=4/', $html);
        $aviso = view('reportes/reporte', ['titulo' => 'Cartera', 'clave' => 'cartera', 'f' => [], 'opciones' => self::opciones(), 'r' => ['aviso' => 'Sin créditos (A1).'] + self::reporte()]);
        $this->assertStringContainsString('Sin créditos (A1).', $aviso);
    }

    public function testTablero(): void
    {
        $grupos = [];
        foreach (['cartera', 'morosidad', 'cobranzas', 'solicitudes', 'refinanciaciones', 'campanas', 'proyeccion'] as $r) {
            $grupos[] = ['Grupo ' . $r, $r, [['Indicador ' . $r, '100.00', 'importe'], ['Cantidad', 2, 'entero']]];
        }
        $t = ['periodo' => Periodo::desde([], '2026-11-12'), 'grupos' => $grupos, 'aviso' => 'No se registraron operaciones en el período (A1).'];
        $html = view('reportes/tablero', ['titulo' => 'Tablero', 'f' => ['tipo' => 'mes', 'fecha' => '', 'desde' => '', 'hasta' => ''], 't' => $t]);
        $this->assertStringContainsString('Grupo morosidad', $html);
        $this->assertStringNotContainsString('Indicadores clave', $html, 'Sin indicadores clave no se muestra la franja destacada');
        $grupos[1][2][] = ['Índice de morosidad', '8.24', 'porcentaje'];
        $conClave = view('reportes/tablero', ['titulo' => 'Tablero', 'f' => ['tipo' => 'mes', 'fecha' => '', 'desde' => '', 'hasta' => ''], 't' => ['grupos' => $grupos] + $t]);
        $this->assertStringContainsString('Indicadores clave', $conClave);
        $this->assertSame(2, substr_count($conClave, 'Índice de morosidad'), 'Destacado arriba y en su bloque');
        $this->assertStringContainsString('(A1)', $html);
        foreach (ReporteService::REPORTES as [, , $permiso]) {
            $this->assertStringContainsString(url_to($permiso), $html);
        }
        session()->set('cuenta', ['permisos' => ['tablero.consultar']] + session('cuenta'));
        $this->assertStringNotContainsString('Ver el reporte', view('reportes/tablero', ['titulo' => 'Tablero', 'f' => ['tipo' => 'mes', 'fecha' => '', 'desde' => '', 'hasta' => ''], 't' => $t]));
    }

    public function testInformeDeCajaDiaria(): void
    {
        $vacio = ['periodo' => Periodo::desde(['tipo' => 'rango', 'desde' => '2026-11-12', 'hasta' => '2026-11-12'], '2026-11-12'), 'aviso' => null, 'jornadas' => [], 'medios' => [], 'conciliaciones' => [], 'ordenes' => [],
            'totales' => ['cobrado' => '0.00', 'diferencias' => '0.00', 'ordenes' => '0.00', 'abiertas' => 0]];
        $f = ['desde' => '', 'hasta' => '', 'caja' => '', 'cajero' => ''];
        $datos = ['titulo' => 'Caja diaria', 'f' => $f, 'opciones' => self::opciones(), 'soloCajero' => false];
        $this->assertStringContainsString('No se registraron jornadas', view('reportes/caja_diaria', ['i' => $vacio] + $datos));
        $i = [
            'aviso' => 'Solo puede consultar sus propias jornadas: el informe se restringió a ellas (A2).',
            'jornadas' => [['id' => 31, 'apertura' => '2026-11-12 11:00:00.000 +00:00', 'cierre' => null, 'estado' => 'Abierta', 'codigo' => 'C01', 'caja' => 'Central', 'cajero' => 'Ruiz, Juan', 'cobrado' => '120000.00', 'cobros' => 2]],
            'medios' => [['medio' => 'TarjetaDebito', 'importe' => '120000.00', 'cantidad' => 2]],
            'conciliaciones' => [['jornada_caja_id' => 30, 'medio' => 'Transferencia', 'total_registrado' => '1000.00', 'total_acreditado' => '900.00', 'diferencia' => '-100.00', 'explicacion' => 'Acreditación demorada', 'codigo' => 'C01', 'apertura' => '2026-11-11 11:00:00.000 +00:00']],
            'ordenes' => [['numero' => 77, 'emision' => '2026-11-12 12:00:00.000 +00:00', 'vence' => '2026-11-13 03:00:00.000 +00:00', 'importe' => '5000.00', 'estado' => 'PendienteConfirmacion', 'denominacion' => 'Díaz, Martina']],
            'totales' => ['cobrado' => '120000.00', 'diferencias' => '-100.00', 'ordenes' => '5000.00', 'abiertas' => 1],
        ] + $vacio;
        $html = view('reportes/caja_diaria', ['i' => $i] + $datos);
        $this->assertStringContainsString('(A2)', $html);
        $this->assertStringContainsString('(A3)', $html);
        $this->assertStringContainsString('Tarjeta de débito', $html);
        $this->assertStringContainsString('Acreditación demorada', $html);
        $this->assertStringContainsString(site_url('admin/cajas/jornadas/31'), $html);
        $this->assertStringContainsString('name="cajero"', $html);
        $this->assertStringNotContainsString('name="cajero"', view('reportes/caja_diaria', ['i' => $i, 'soloCajero' => true] + $datos));
    }

    public function testRegistroDeAuditoria(): void
    {
        $f = ['desde' => '', 'hasta' => '', 'responsable' => '', 'categoria' => '', 'objeto' => '', 'resultado' => '', 'pagina' => '', 'por_pagina' => ''];
        $b = ['filas' => [], 'total' => 0, 'pagina' => 1, 'paginas' => 1, 'por_pagina' => 25, 'periodo' => Periodo::desde(['tipo' => 'rango'], '2026-11-12')];
        $html = view('auditoria/index', ['titulo' => 'Auditoría', 'f' => $f, 'b' => $b]);
        $this->assertStringContainsString('(A1)', $html);
        $this->assertStringContainsString('Decisiones automáticas', $html);
        $this->assertStringContainsString('value="2026-11-01"', $html);
        $fila = ['id' => 90, 'fecha_hora' => '2026-11-12 15:00:00.000 +00:00', 'responsable' => 'Proceso campanas:cerrar', 'proceso' => 'campanas:cerrar', 'accion' => 'campanas.cerrar', 'objeto_tipo' => 'campanas', 'objeto_id' => '2', 'resultado' => 'Exitoso', 'motivo' => null];
        $html = view('auditoria/index', ['titulo' => 'Auditoría', 'f' => ['categoria' => 'creditos'] + $f, 'b' => ['filas' => [$fila], 'total' => 120, 'paginas' => 3] + $b]);
        $this->assertStringContainsString('Créditos (automática)', $html);
        $this->assertStringContainsString('Campaña crediticia 2', $html);
        $this->assertStringContainsString('Siguiente', $html);
        $this->assertStringContainsString(site_url('admin/auditoria/90'), $html);
        // Como los listados con DataTables: «Mostrar [n] registros» arriba, «Mostrando X a Y de Z registros» abajo y las
        // páginas arriba y abajo de la tabla
        $this->assertSame(2, substr_count($html, '<ul class="pagination mb-0">'));
        $this->assertSame(1, substr_count($html, 'data-autoenviar'));
        $this->assertStringContainsString('Mostrando 1 a 25 de 120 registros', $html);
        $this->assertStringContainsString('badge rounded-pill text-bg-success">Exitoso', $html);
        foreach ([10, 25, 50, 100] as $n) {
            $this->assertStringContainsString("<option value=\"{$n}\"", $html);
        }
        $this->assertStringContainsString('pagina=3', $html, 'Enlace a la última página');

        $a = $fila + ['rol' => null, 'detalle_datos' => ['causa' => 'Cupo', 'aceptadas' => 1], 'ip_origen' => null];
        $ver = view('auditoria/ver', ['titulo' => 'Registro', 'a' => $a, 'volver' => 'pagina=2']);
        $this->assertStringContainsString('(A4)', $ver);
        $this->assertStringContainsString('Parámetros aplicados', $ver);
        $this->assertStringContainsString('&quot;causa&quot;: &quot;Cupo&quot;', $ver);
        $humano = view('auditoria/ver', ['titulo' => 'Registro', 'a' => ['proceso' => null, 'responsable' => 'Pérez, Ana', 'rol' => 'Gerente', 'motivo' => 'Corrección', 'detalle_datos' => null] + $a, 'volver' => '']);
        $this->assertStringContainsString('Pérez, Ana', $humano);
        $this->assertStringContainsString('(Gerente)', $humano);
        $this->assertStringContainsString('Corrección', $humano);
        $this->assertSame('Acceso', AuditoriaConsultaService::categoria('acceso.inicio', null));
        $this->assertSame('Parámetros', AuditoriaConsultaService::categoria('parametros.campanas', null));
        $this->assertSame('Cobranzas', AuditoriaConsultaService::categoria('cajas.informe-diario', null));
        $this->assertSame('Administración', AuditoriaConsultaService::categoria('cajas.registrar', null));
    }

    public function testRegimenInformativo(): void
    {
        $this->assertStringContainsString('Todavía no se generó', view('regimen/index', ['titulo' => 'Régimen', 'regimenes' => []]));
        $fila = ['id' => 3, 'periodo' => '2026-10', 'version' => 2, 'fecha_cierre' => '2026-10-31', 'fecha_generacion' => '2026-11-02 10:00:00.000 +00:00', 'cantidad_deudores' => 2, 'saldo_total' => '5750.50',
            'hash_archivo' => str_repeat('a', 64), 'estado' => 'Vigente', 'proceso' => null, 'motivo' => 'Pago imputado tarde', 'usuario' => 'Pérez, Ana'];
        $html = view('regimen/index', ['titulo' => 'Régimen', 'regimenes' => [$fila, ['id' => 2, 'version' => 1, 'estado' => 'Rectificado', 'usuario' => null, 'proceso' => 'regimen:generar', 'motivo' => null] + $fila]]);
        $this->assertStringContainsString('name="periodo"', $html);
        $this->assertStringContainsString('name="motivo"', $html);
        $this->assertStringContainsString('Proceso automático: Régimen informativo', $html);
        $this->assertStringNotContainsString('regimen:generar', $html);
        $this->assertStringContainsString(site_url('admin/regimen/3/archivo'), $html);
        $deudores = [['cliente_id' => 5, 'cuit_cuil' => '27394445520', 'denominacion' => 'Díaz, Martina', 'dias_atraso' => 123, 'situacion' => 3, 'saldo' => '750.50', 'creditos' => 2]];
        $r = $fila + ['deudores' => $deudores, 'por_situacion' => [1 => [1, '5000.00'], 2 => [0, '0.00'], 3 => [1, '750.50'], 4 => [0, '0.00'], 5 => [0, '0.00']], 'versiones' => [['id' => 2, 'version' => 1, 'estado' => 'Rectificado'], ['id' => 3, 'version' => 2, 'estado' => 'Vigente']]];
        $ver = view('regimen/ver', ['titulo' => 'Régimen', 'r' => $r]);
        $this->assertStringContainsString('27-39444552-0', $ver);
        $this->assertStringContainsString(Indicadores::SITUACIONES[3], $ver);
        $this->assertStringContainsString('Pago imputado tarde', $ver);
        $this->assertStringContainsString(url_to('regimen.ver', 2), $ver);
        $this->assertStringContainsString('(A2)', view('regimen/ver', ['titulo' => 'Régimen', 'r' => ['deudores' => [], 'versiones' => []] + $r]));
    }
}
