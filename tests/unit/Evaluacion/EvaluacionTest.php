<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluacion;

use App\Adapters\InformacionCrediticia\BcraInformacionCrediticia;
use App\Adapters\ServicioNoDisponibleException;
use App\Libraries\Evaluacion\AntecedentesBcra;
use App\Libraries\Evaluacion\Contingencia;
use App\Libraries\Evaluacion\DimensionesEvaluacion;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\ModeloEvaluacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** D02: evaluación automática (CU-86), adaptador de la Central de Deudores (RNF-08) y contingencia (CU-25). */
final class EvaluacionTest extends TestCase
{
    private const HOY = '2026-11-04';

    private const CUIT = '20304050607';

    /** @return list<array{entidad: string, periodo: string, situacion: int, monto: string}> */
    private function deudas(): array
    {
        return [
            ['entidad' => 'BANCO A', 'periodo' => '202609', 'situacion' => 1, 'monto' => '150000.00'],
            ['entidad' => 'BANCO B', 'periodo' => '202609', 'situacion' => 2, 'monto' => '80000.00'],
            ['entidad' => 'BANCO A', 'periodo' => '202603', 'situacion' => 3, 'monto' => '120000.00'],
            ['entidad' => 'BANCO C', 'periodo' => '202510', 'situacion' => 5, 'monto' => '10000.00'], // fuera de los 12 meses
        ];
    }

    public function testResumenBcraTomaLaPeorSituacionDe12MesesYLaDelUltimoPeriodo(): void
    {
        $r = AntecedentesBcra::resumir($this->deudas(), self::HOY);
        $this->assertSame('202511', AntecedentesBcra::periodoDesde(self::HOY));
        $this->assertSame(3, $r['peor_12m']);
        $this->assertSame(2, $r['peor_vigente']);
        $this->assertSame('202609', $r['ultimo_periodo']);
        $this->assertCount(3, $r['deudas']);
    }

    public function testSinDeudasInformadasLasSituacionesSonNulas(): void
    {
        $r = AntecedentesBcra::resumir([['entidad' => 'X', 'periodo' => '202401', 'situacion' => 4, 'monto' => '1.00']], self::HOY);
        $this->assertNull($r['peor_12m']);
        $this->assertNull($r['peor_vigente']);
        $this->assertSame([], $r['deudas']);
    }

    public function testElResultadoDefineElEstadoDeLaSolicitudYQuienRecibeLaAlerta(): void
    {
        // Ejemplo de RN-69: situación 1, sin atraso, endeudamiento del 15 % y 8 meses de antigüedad = 86,5 (A)
        $e = ModeloEvaluacion::evaluar(1, 1, 0, Decimal::of('15000'), Decimal::of('100000'), 8);
        $this->assertSame('86.50', $e['puntaje']->toFixed(2));
        $this->assertSame('PendienteSeleccionPlan', DimensionesEvaluacion::estadoSolicitud($e['resultado']));
        $this->assertSame('AnalistaCredito', DimensionesEvaluacion::rolAlerta('PendienteSeleccionPlan'));

        // Categoría C: requiere análisis gerencial
        $c = ModeloEvaluacion::evaluar(2, 2, 20, Decimal::of('25000'), Decimal::of('100000'), 12);
        $this->assertSame('C', $c['categoria']);
        $this->assertSame('PendienteResolucion', DimensionesEvaluacion::estadoSolicitud($c['resultado']));
        $this->assertSame('Gerente', DimensionesEvaluacion::rolAlerta('PendienteResolucion'));

        // Situación 5 vigente: desfavorable cualquiera sea el puntaje
        $d = ModeloEvaluacion::evaluar(5, 5, 0, Decimal::zero(), Decimal::of('100000'), 30);
        $this->assertSame('Desfavorable', $d['resultado']);
        $this->assertSame('Rechazada', DimensionesEvaluacion::estadoSolicitud($d['resultado']));
        $this->assertSame('AnalistaCredito', DimensionesEvaluacion::rolAlerta('PendienteContingencia'));
    }

    public function testUnaEvaluacionVigenteVencidaSeInformaComoVencida(): void
    {
        $ahora = new DateTimeImmutable('2026-11-04T12:00:00+00:00');
        $this->assertSame('Vencida', DimensionesEvaluacion::vigenciaEfectiva('Vigente', '2026-11-04 11:59:59+00:00', $ahora));
        $this->assertSame('Vigente', DimensionesEvaluacion::vigenciaEfectiva('Vigente', '2026-12-04 12:00:00+00:00', $ahora));
        $this->assertSame('Invalidada', DimensionesEvaluacion::vigenciaEfectiva('Invalidada', '2026-11-01 00:00:00+00:00', $ahora));
        $this->assertNull(DimensionesEvaluacion::vigenciaEfectiva(null, null, $ahora));
    }

    /** @param list<array{status: int, body: string}|\Throwable> $respuestas */
    private function adaptador(array &$respuestas, array &$urls): BcraInformacionCrediticia
    {
        return new BcraInformacionCrediticia('https://bcra.prueba/v1.0', 10, 2, static function (string $url) use (&$respuestas, &$urls): array {
            $urls[] = $url;
            $r = array_shift($respuestas);
            if ($r instanceof \Throwable) {
                throw $r;
            }

            return $r;
        }, static function (int $ms): void {
        });
    }

    public function testElAdaptadorCombinaDeudasEHistoricasConMontosEnPesos(): void
    {
        $actual = '{"status":200,"results":{"identificacion":20304050607,"denominacion":"PRUEBA","periodos":[{"periodo":"202609","entidades":[{"entidad":"BANCO A","situacion":1,"fechaSit1":"2020-01-01","monto":150.5,"diasAtrasoPago":0}]}]}}';
        $historicas = '{"status":200,"results":{"identificacion":20304050607,"periodos":[{"periodo":"202609","entidades":[{"entidad":"BANCO A","situacion":1,"monto":150.5}]},{"periodo":"202603","entidades":[{"entidad":"BANCO A","situacion":3,"monto":0.125}]}]}}';
        $respuestas = [['status' => 200, 'body' => $actual], ['status' => 200, 'body' => $historicas]];
        $urls = [];
        $deudas = $this->adaptador($respuestas, $urls)->consultar(self::CUIT);
        $this->assertSame(['https://bcra.prueba/v1.0/Deudas/' . self::CUIT, 'https://bcra.prueba/v1.0/Deudas/Historicas/' . self::CUIT], $urls);
        $this->assertCount(2, $deudas);
        $this->assertSame(['entidad' => 'BANCO A', 'periodo' => '202609', 'situacion' => 1, 'monto' => '150500.00'], $deudas[0]);
        $this->assertSame('125.00', $deudas[1]['monto']);
        $this->assertSame('1.00', BcraInformacionCrediticia::milesAPesos('0.001'));
        $this->assertSame('0.00', BcraInformacionCrediticia::milesAPesos('0'));
    }

    public function testRespuesta404SignificaSinDeudasInformadas(): void
    {
        $respuestas = [['status' => 404, 'body' => '{"status":404,"errorMessages":["No se encontró datos"]}'], ['status' => 404, 'body' => '']];
        $urls = [];
        $a = $this->adaptador($respuestas, $urls);
        $this->assertSame([], $a->consultar(self::CUIT));
        $this->assertCount(2, $a->intercambios());
    }

    public function testReintentaHastaDosVecesAnteFallosTransitoriosYLuegoQuedaNoDisponible(): void
    {
        $ok = '{"status":200,"results":{"periodos":[]}}';
        // Primera consulta: sin respuesta y 503, luego 200; segunda: 200
        $respuestas = [new \RuntimeException('timeout'), ['status' => 503, 'body' => ''], ['status' => 200, 'body' => $ok], ['status' => 200, 'body' => $ok]];
        $urls = [];
        $this->assertSame([], $this->adaptador($respuestas, $urls)->consultar(self::CUIT));
        $this->assertCount(4, $urls);

        $respuestas = [['status' => 500, 'body' => ''], ['status' => 429, 'body' => ''], new \RuntimeException('timeout')];
        $urls = [];
        $a = $this->adaptador($respuestas, $urls);
        $this->expectException(ServicioNoDisponibleException::class);
        try {
            $a->consultar(self::CUIT);
        } finally {
            $this->assertCount(3, $urls);
            $this->assertSame(['500', '429', 'SinRespuesta'], array_column($a->intercambios(), 'codigo'));
        }
    }

    public function testUnaRespuestaNoValidaNoSeReintentaYQuedaNoDisponible(): void
    {
        $respuestas = [['status' => 200, 'body' => '<html>mantenimiento</html>']];
        $urls = [];
        $this->expectException(ServicioNoDisponibleException::class);
        $this->adaptador($respuestas, $urls)->consultar(self::CUIT);
    }

    /** @return array<string, mixed> */
    private function contingencia(array $cambios = []): array
    {
        return $cambios + [
            'antecedentes' => ['informacion_economica', 'documentacion'],
            'detalle_antecedentes' => 'Recibos de sueldo de los últimos tres meses verificados con el empleador.',
            'recomendacion' => 'Favorable',
            'justificacion' => 'Ingresos estables y sin atrasos internos; la cuota afecta el 18 % de los ingresos.',
            'deudas' => [],
        ];
    }

    public function testLaContingenciaCompletaEsValida(): void
    {
        $r = Contingencia::validar($this->contingencia(), self::HOY);
        $this->assertSame([], $r['errores']);
        $this->assertSame(['informacion_economica', 'documentacion'], $r['datos']['antecedentes']);
        $this->assertSame('Favorable', $r['datos']['recomendacion']);
    }

    public function testLaContingenciaExigeAntecedentesRecomendacionYJustificacion(): void
    {
        $r = Contingencia::validar(['antecedentes' => ['otro'], 'detalle_antecedentes' => 'corto', 'recomendacion' => 'Aprobar', 'justificacion' => ''], self::HOY);
        $this->assertSame(['antecedentes', 'detalle_antecedentes', 'recomendacion', 'justificacion'], array_keys($r['errores']));
    }

    public function testLasDeudasManualesExigenLaConsultaManualYFilasCompletas(): void
    {
        $fila = ['entidad' => 'BANCO A', 'situacion' => '2', 'monto' => '150.000,50', 'periodo' => '09/2026'];
        $r = Contingencia::validar($this->contingencia(['deudas' => [$fila]]), self::HOY);
        $this->assertArrayHasKey('deudas.0', $r['errores']);

        $antecedentes = ['consulta_manual_bcra'];
        $r = Contingencia::validar($this->contingencia(['antecedentes' => $antecedentes, 'deudas' => [$fila, ['entidad' => '', 'situacion' => '', 'monto' => '', 'periodo' => '']]]), self::HOY);
        $this->assertSame([], $r['errores']);
        $this->assertSame([['entidad' => 'BANCO A', 'situacion' => 2, 'monto' => '150000.50', 'periodo' => '202609']], $r['datos']['deudas']);

        $r = Contingencia::validar($this->contingencia(['antecedentes' => $antecedentes, 'deudas' => [
            ['entidad' => 'BANCO A', 'situacion' => '7', 'monto' => '1', 'periodo' => '202609'],
            ['entidad' => 'BANCO A', 'situacion' => '1', 'monto' => '1', 'periodo' => '202401'],
        ]]), self::HOY);
        $this->assertStringContainsString('1 a 6', $r['errores']['deudas.0']);
        $this->assertStringContainsString('12 meses', $r['errores']['deudas.1']);
    }
}
