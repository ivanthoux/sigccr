<?php

declare(strict_types=1);

namespace Tests\Unit\Reportes;

use App\Exceptions\ValidacionException;
use App\Libraries\Cartera\ReglasRegimen;
use App\Libraries\Reportes\Exportacion;
use App\Libraries\Reportes\Indicadores;
use App\Libraries\Reportes\Periodo;
use App\Libraries\Reportes\Xlsx;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * SS-09 Reportes (D-44): período de referencia, indicadores de RN-74, clasificación de RN-84, exportación a Excel y
 * régimen informativo de deudores (CU-105).
 */
final class ReportesTest extends TestCase
{
    private const ZONA = 'America/Argentina/Buenos_Aires';

    public function testPeriodos(): void
    {
        $hoy = '2026-11-12';
        $mes = Periodo::desde([], $hoy);
        $this->assertSame(['mes', '2026-11-01', '2026-11-30', 'dia'], [$mes['tipo'], $mes['desde'], $mes['hasta'], $mes['granularidad']]);
        $semana = Periodo::desde(['tipo' => 'semana', 'fecha' => '2026-11-12'], $hoy);
        $this->assertSame(['2026-11-09', '2026-11-15', 'dia'], [$semana['desde'], $semana['hasta'], $semana['granularidad']]);
        $trimestre = Periodo::desde(['tipo' => 'trimestre', 'fecha' => '12/08/2026'], $hoy);
        $this->assertSame(['2026-07-01', '2026-09-30'], [$trimestre['desde'], $trimestre['hasta']]);
        $dia = Periodo::desde(['tipo' => 'dia'], $hoy);
        $this->assertSame([$hoy, $hoy], [$dia['desde'], $dia['hasta']]);
        $rango = Periodo::desde(['tipo' => 'rango', 'desde' => '2026-01-01', 'hasta' => '2026-11-12'], $hoy);
        $this->assertSame('mes', $rango['granularidad']);
        $proyeccion = Periodo::desde([], $hoy, true, 30);
        $this->assertSame(['rango', $hoy, '2026-12-12'], [$proyeccion['tipo'], $proyeccion['desde'], $proyeccion['hasta']]);
    }

    public function testRangoIncoherente(): void
    {
        try {
            Periodo::desde(['tipo' => 'rango', 'desde' => '2026-11-12', 'hasta' => '2026-11-01'], '2026-11-12');
            $this->fail('Debía rechazar un rango con la fecha final anterior a la inicial (A2).');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('hasta', $e->errores);
        }
        try {
            Periodo::desde(['tipo' => 'rango', 'desde' => '2026-11-01'], '2026-11-12', true);
            $this->fail('La proyección no puede empezar antes de hoy (CU-70 A2).');
        } catch (ValidacionException $e) {
            $this->assertArrayHasKey('desde', $e->errores);
        }
    }

    public function testIntervalosDeLaEvolucion(): void
    {
        $i = Periodo::intervalos('2026-11-01', '2026-11-03', 'dia');
        $this->assertCount(3, $i);
        $this->assertSame(Periodo::clave('2026-11-02', 'dia'), array_keys($i)[1]);
        $m = Periodo::intervalos('2026-01-15', '2026-03-02', 'mes');
        $this->assertCount(3, $m);
        $this->assertArrayHasKey(Periodo::clave('2026-02-20', 'mes'), $m);
    }

    public function testIndicadores(): void
    {
        $this->assertSame('25.00', Indicadores::porcentaje('250', '1000'));
        $this->assertSame('0.00', Indicadores::porcentaje(3, 0));
        $this->assertSame('1-30', Indicadores::tramo(1));
        $this->assertSame('31-60', Indicadores::tramo(31));
        $this->assertSame('61-90', Indicadores::tramo(90));
        $this->assertSame('90+', Indicadores::tramo(91));
        foreach ([0 => 1, 31 => 1, 32 => 2, 90 => 2, 91 => 3, 180 => 3, 181 => 4, 365 => 4, 366 => 5] as $dias => $situacion) {
            $this->assertSame($situacion, Indicadores::situacion($dias), "{$dias} días");
        }
    }

    public function testExportacionYArchivoExcel(): void
    {
        $r = [
            'titulo' => 'Reporte de prueba', 'cu' => 'CU-65', 'periodo' => ['etiqueta' => 'Noviembre de 2026'], 'referencia' => '2026-11-12 15:00:00.000+00:00',
            'filtros' => ['Línea: Personal'], 'aviso' => null,
            'kpis' => [['Créditos', 3, 'entero'], ['Capital', '1500000.50', 'importe'], ['Índice', '12.50', 'porcentaje']],
            'secciones' => [['titulo' => 'Créditos', 'columnas' => [['N.º', 'entero'], ['Liquidación', 'fecha'], ['Cliente', 'texto'], ['Capital', 'importe']],
                'filas' => [[1, '2026-11-10 13:30:00.000 +00:00', 'Pérez & Cía <S.A.>', '500000.00'], [2, '2026-11-11', 'Gómez', null]], 'nota' => 'Nota de la sección.']],
        ];
        $h = Exportacion::hojas($r, self::ZONA);
        $this->assertSame(['Resumen', 'Créditos'], array_keys($h));
        $this->assertSame(['Generado', '12/11/2026 12:00'], $h['Resumen'][3]);
        $this->assertTrue(in_array(['Índice (%)', 12.5], $h['Resumen'], true));
        $this->assertSame([1, '10/11/2026', 'Pérez & Cía <S.A.>', 500000.0], $h['Créditos'][1]);
        $this->assertNull($h['Créditos'][2][3]);
        $this->assertSame(['Nota de la sección.'], end($h['Créditos']));

        $ruta = tempnam(sys_get_temp_dir(), 'test') . '.xlsx';
        file_put_contents($ruta, Xlsx::generar($h));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($ruta) === true);
        $libro = (string) $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('name="Resumen"', $libro);
        $this->assertStringContainsString('name="Créditos"', $libro);
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('Pérez &amp; Cía &lt;S.A.&gt;', $hoja);
        $this->assertStringContainsString('<v>500000</v>', $hoja);
        $zip->close();
        unlink($ruta);
    }

    public function testPeriodoDelRegimen(): void
    {
        // Noviembre de 2026 empieza en domingo: el primer día hábil es el lunes 2
        $this->assertNull(ReglasRegimen::periodoACerrar('2026-11-01', []));
        $this->assertSame('2026-10', ReglasRegimen::periodoACerrar('2026-11-02', []));
        $this->assertNull(ReglasRegimen::periodoACerrar('2026-11-02', ['2026-11-02']));
        $this->assertSame('2026-10', ReglasRegimen::periodoACerrar('2026-11-03', ['2026-11-02']));
        $this->assertSame('2025-12', ReglasRegimen::periodoACerrar('2026-01-05', ['2026-01-01']));
        $this->assertSame('2026-02-28', ReglasRegimen::cierre('2026-02'));
        $this->assertTrue(ReglasRegimen::periodoCerrado('2026-10', '2026-11-12'));
        $this->assertFalse(ReglasRegimen::periodoCerrado('2026-11', '2026-11-12'));
        $this->assertFalse(ReglasRegimen::periodoCerrado('2026-13', '2027-11-12'));
        // H-30: el año 0000 no es una fecha válida en SQL Server; se admiten períodos desde 1900
        $this->assertFalse(ReglasRegimen::periodoCerrado('0000-01', '2026-11-12'));
        $this->assertFalse(ReglasRegimen::periodoCerrado('1899-12', '2026-11-12'));
        $this->assertTrue(ReglasRegimen::periodoCerrado('1900-01', '2026-11-12'));
    }

    public function testClasificacionDeDeudoresYArchivo(): void
    {
        $q = static fn (int $cliente, int $credito, string $venc, string $pend): array => ['cliente_id' => $cliente, 'cuit_cuil' => '2739444552' . $cliente, 'denominacion' => "Cliente {$cliente}", 'credito_id' => $credito, 'vencimiento' => $venc, 'pendiente' => $pend];
        $d = ReglasRegimen::deudores([
            $q(1, 10, '2026-10-10', '1000.00'), $q(1, 10, '2026-11-10', '1000.00'),            // 21 días: situación 1
            $q(2, 20, '2026-06-30', '500.50'), $q(2, 21, '2026-09-30', '250.00'),              // 123 días: situación 3
            $q(3, 30, '2025-10-01', '99.99'),                                                  // 395 días: situación 5
            $q(4, 40, '2026-12-10', '3000.00'),                                                // al día: situación 1
        ], '2026-10-31');
        $this->assertSame([5, 3, 1, 1], array_column($d, 'situacion'));
        $this->assertSame([395, 123, 21, 0], array_column($d, 'dias_atraso'));
        $this->assertSame('750.50', $d[1]['saldo']);
        $this->assertSame(2, $d[1]['creditos']);
        $this->assertSame([1 => [2, '5000.00'], 2 => [0, '0.00'], 3 => [1, '750.50'], 4 => [0, '0.00'], 5 => [1, '99.99']], ReglasRegimen::porSituacion($d));

        $archivo = ReglasRegimen::archivo('2026-10', 2, '2026-10-31', [['cuit_cuil' => '27394445520', 'denominacion' => 'Pérez; Ana', 'situacion' => 3, 'saldo' => '750.50', 'dias_atraso' => 123, 'creditos' => 2]]);
        $this->assertSame("CAB;2026-10;2;2026-10-31;1;750.50\r\nDEU;27394445520;Pérez, Ana;3;750.50;123;2\r\n", $archivo);
        $this->assertSame("CAB;2026-10;1;2026-10-31;0;0.00\r\n", ReglasRegimen::archivo('2026-10', 1, '2026-10-31', []));
    }
}
