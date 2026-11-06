<?php

declare(strict_types=1);

namespace Tests\Unit\Solicitudes;

use App\Libraries\Lineas\Elegibilidad;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\RevisionLegajo;
use App\Libraries\Solicitudes\Simulador;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** D01: simulación (CU-21), registro (CU-22), verificación del legajo (CU-23) y correcciones (CU-84). */
final class SolicitudesTest extends TestCase
{
    private const HOY = '2026-11-04';

    /** @return array<string, mixed> */
    private function version(array $cambios = []): array
    {
        return $cambios + [
            'estado' => 'Activa', 'vigencia_inicio' => '2026-01-01', 'vigencia_fin' => '2026-12-31',
            'admite_persona_humana' => 1, 'admite_persona_juridica' => 0,
            'edad_minima' => 18, 'edad_maxima' => 75, 'antiguedad_minima_meses' => null,
            'categorias' => ['RelacionDependencia', 'JubiladoPensionado'],
            'tna' => '0.60', 'gasto_otorgamiento_pct' => '0.02', 'gasto_administrativo_cuota' => '500.00',
            'monto_minimo' => '50000.00', 'monto_maximo' => '1500000.00', 'cuotas_minimas' => 3, 'cuotas_maximas' => 24,
        ];
    }

    /** @return array<string, mixed> */
    private function cliente(array $cambios = []): array
    {
        return $cambios + ['tipo_cliente' => 'PersonaHumana', 'categoria_ingresos' => 'RelacionDependencia', 'estado' => 'Activo',
            'fecha_nacimiento' => '1981-05-02', 'fecha_constitucion' => null];
    }

    public function testLineaAdmitida(): void
    {
        $this->assertNull(Elegibilidad::motivoNoAdmitida($this->version(), $this->cliente(), self::HOY));
    }

    public function testMotivosDeNoAdmision(): void
    {
        $casos = [
            'La línea está suspendida (RN-16).' => [$this->version(['estado' => 'Suspendida']), $this->cliente()],
            'La línea superó su período de vigencia (RN-13).' => [$this->version(['vigencia_fin' => '2026-11-03']), $this->cliente()],
            'La línea rige desde el 01/12/2026 (RN-13).' => [$this->version(['vigencia_inicio' => '2026-12-01']), $this->cliente()],
            'La línea no admite personas jurídicas.' => [$this->version(), $this->cliente(['tipo_cliente' => 'PersonaJuridica', 'categoria_ingresos' => 'Empresa'])],
            'La línea no admite la categoría de ingresos del cliente.' => [$this->version(), $this->cliente(['categoria_ingresos' => 'Monotributista'])],
            'El cliente no alcanza la edad mínima de la línea (21 años).' => [$this->version(['edad_minima' => 21]), $this->cliente(['fecha_nacimiento' => '2006-11-05'])],
            'El cliente supera la edad máxima de la línea (40 años).' => [$this->version(['edad_maxima' => 40]), $this->cliente()],
            'El cliente está desactivado.' => [$this->version(), $this->cliente(['estado' => 'Desactivado'])],
        ];
        foreach ($casos as $esperado => [$v, $c]) {
            $this->assertSame($esperado, Elegibilidad::motivoNoAdmitida($v, $c, self::HOY), $esperado);
        }
    }

    public function testEdadExactaYAntiguedadDeEmpresa(): void
    {
        // Cumple 21 el mismo día de la simulación: se admite
        $this->assertNull(Elegibilidad::motivoNoAdmitida($this->version(['edad_minima' => 21]), $this->cliente(['fecha_nacimiento' => '2005-11-04']), self::HOY));
        $pyme = ['admite_persona_juridica' => 1, 'antiguedad_minima_meses' => 24, 'categorias' => ['Empresa']];
        $empresa = $this->cliente(['tipo_cliente' => 'PersonaJuridica', 'categoria_ingresos' => 'Empresa', 'fecha_nacimiento' => null]);
        $this->assertNull(Elegibilidad::motivoNoAdmitida($this->version($pyme), ['fecha_constitucion' => '2024-11-04'] + $empresa, self::HOY));
        $this->assertSame(
            'La empresa no alcanza la antigüedad mínima de la línea (24 meses).',
            Elegibilidad::motivoNoAdmitida($this->version($pyme), ['fecha_constitucion' => '2024-11-05'] + $empresa, self::HOY)
        );
        $this->assertSame(23, Elegibilidad::mesesCumplidos('2024-11-05', self::HOY));
    }

    public function testValidacionDeImporteYCuotas(): void
    {
        $ok = Simulador::validar($this->version(), '850.000,00', '12');
        $this->assertSame(['importe' => '850000.00', 'cuotas' => 12, 'errores' => []], $ok);
        $mal = Simulador::validar($this->version(), '20.000', '36');
        $this->assertSame('La línea presta entre $ 50.000,00 y $ 1.500.000,00 (RN-76).', $mal['errores']['importe']);
        $this->assertSame('La línea admite entre 3 y 24 cuotas (RN-14).', $mal['errores']['cantidad_cuotas']);
        $this->assertNull($mal['importe']);
        $vacio = Simulador::validar($this->version(), '', 'doce')['errores'];
        $this->assertSame(['importe' => 'Ingrese el importe a financiar.', 'cantidad_cuotas' => 'La cantidad de cuotas debe ser un número entero.'], $vacio);
        $this->assertSame('Ingrese un importe válido, por ejemplo 850.000,00.', Simulador::validar($this->version(), '12a', '6')['errores']['importe']);
        $this->assertSame('La línea admite solo 6 cuotas (RN-14).', Simulador::validar($this->version(['cuotas_minimas' => 6, 'cuotas_maximas' => 6]), '100000', '5')['errores']['cantidad_cuotas']);
        // Los límites se aceptan
        $this->assertSame([], Simulador::validar($this->version(), '50000', '3')['errores']);
        $this->assertSame([], Simulador::validar($this->version(), '1500000', '24')['errores']);
    }

    public function testCalculoConElEjemploDeRn68(): void
    {
        $r = Simulador::calcular($this->version(['gasto_administrativo_cuota' => '500.00']), '100000.00', 6, '0.21', new DateTimeImmutable('2026-11-04'));
        $this->assertSame('19701.75', $r['plan']->cuotaOrdinaria->toFixed(2));
        $this->assertSame('21356.75', $r['primera_cuota']->toFixed(2));
        $primera = $r['plan']->cuotas[0];
        $this->assertSame(['14701.75', '5000.00', '500.00', '1155.00'], [$primera->capital->toFixed(2), $primera->interes->toFixed(2), $primera->gastos->toFixed(2), $primera->iva->toFixed(2)]);
        $this->assertSame('2026-12-04', $primera->vencimiento->format('Y-m-d'));
        $this->assertSame(['2000.00', '420.00', '97580.00'], [$r['gasto_otorgamiento']->toFixed(2), $r['iva_otorgamiento']->toFixed(2), $r['neto']->toFixed(2)]);
        $this->assertSame('0.05', $r['tasa_mensual']->toFixed(2));
        $this->assertSame('100000.00', $r['plan']->totalCapital()->toFixed(2));
        // El CFTEA supera a la TEA porque incluye gastos e IVA (RN-63)
        $this->assertSame(1, $r['cftea']->compare($r['tea']));
    }

    public function testFormatoDeImporteSinFloat(): void
    {
        $this->assertSame('1.234.567,89', Simulador::monto(\App\Libraries\Finanzas\Decimal::of('1234567.89')));
        $this->assertSame('-5,00', Simulador::monto(\App\Libraries\Finanzas\Decimal::of('-5')));
        $this->assertSame('0,10', Simulador::monto(\App\Libraries\Finanzas\Decimal::of('0.1')));
    }

    public function testTransicionesDeLaSolicitud(): void
    {
        $this->assertTrue(EstadosSolicitud::puede('PendienteVerificacion', 'PendienteEvaluacion'));
        $this->assertTrue(EstadosSolicitud::puede('PendienteVerificacion', 'PendienteDocumentacion'));
        $this->assertTrue(EstadosSolicitud::puede('PendienteDocumentacion', 'PendienteVerificacion'));
        $this->assertTrue(EstadosSolicitud::puede('EnEvaluacion', 'PendienteVerificacion'));
        $this->assertTrue(EstadosSolicitud::puede('DevueltaRevision', 'Cancelada'));
        $this->assertFalse(EstadosSolicitud::puede('PendienteDocumentacion', 'PendienteEvaluacion'));
        $this->assertFalse(EstadosSolicitud::puede('PendienteVerificacion', 'AprobadaPendienteLiquidacion'));
        foreach (EstadosSolicitud::FINALES as $final) {
            $this->assertFalse(EstadosSolicitud::puede($final, 'PendienteVerificacion'), $final);
            $this->assertFalse(EstadosSolicitud::puede($final, 'Cancelada'), $final);
        }
        // Todo destino de la tabla es un estado conocido
        foreach (EstadosSolicitud::TRANSICIONES as $origen => $destinos) {
            foreach ([$origen, ...$destinos] as $e) {
                $this->assertArrayHasKey($e, EstadosSolicitud::ETIQUETAS, $e);
            }
        }
    }

    public function testEstadoTrasCorreccion(): void
    {
        $this->assertSame('PendienteVerificacion', EstadosSolicitud::trasCorreccion('PendienteDocumentacion', true, true, null));
        // A1: faltantes no atendidos, la solicitud conserva su estado
        $this->assertNull(EstadosSolicitud::trasCorreccion('PendienteDocumentacion', true, false, null));
        $this->assertNull(EstadosSolicitud::trasCorreccion('PendienteDocumentacion', false, true, null));
        $this->assertSame('PendienteVerificacion', EstadosSolicitud::trasCorreccion('DevueltaRevision', true, true, 'Resolucion'));
        $this->assertSame('PendienteResolucion', EstadosSolicitud::trasCorreccion('DevueltaRevision', false, true, 'Resolucion'));
        $this->assertSame('PendienteSeleccionPlan', EstadosSolicitud::trasCorreccion('DevueltaRevision', false, true, 'SeleccionPlan'));
        $this->assertSame('PendienteEvaluacion', EstadosSolicitud::trasCorreccion('DevueltaRevision', false, true, 'Evaluacion'));
        $this->assertNull(EstadosSolicitud::trasCorreccion('PendienteEvaluacion', true, true, null));
    }

    public function testRevisionVerificada(): void
    {
        $r = RevisionLegajo::validar(
            ['DOC_IDENTIDAD', 'RECIBO_SUELDO'],
            ['DOC_IDENTIDAD' => [10], 'RECIBO_SUELDO' => [11]],
            [10 => ['resultado' => 'Conforme'], 11 => ['resultado' => 'Conforme']],
            'Verificada',
            '',
            ''
        );
        $this->assertSame([], $r['errores']);
        $this->assertSame([], $r['faltantes']);
        $this->assertSame(['resultado' => 'Conforme', 'motivo' => null, 'observacion' => null], $r['verificaciones'][10]);
        $this->assertSame('PendienteEvaluacion', RevisionLegajo::estadoSolicitud('Verificada'));
    }

    public function testNoSeVerificaConFaltantesODocumentosObservados(): void
    {
        $faltante = RevisionLegajo::validar(['DOC_IDENTIDAD', 'RECIBO_SUELDO'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Conforme']], 'Verificada', '', '');
        $this->assertSame('No puede verificarse: falta documentación exigida por la línea (RN-11, RN-19).', $faltante['errores']['resultado']);
        $this->assertSame(['RECIBO_SUELDO'], $faltante['faltantes']);
        $observado = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Observado', 'motivo' => 'Ilegible']], 'Verificada', '', '');
        $this->assertSame('No puede verificarse: hay documentación exigida que no está conforme.', $observado['errores']['resultado']);
    }

    public function testRevisionObservadaYRechazada(): void
    {
        $obs = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Observado', 'motivo' => 'Ilegible', 'observacion' => 'Foto movida']], 'Observada', 'corto', '');
        $this->assertSame(['observaciones'], array_keys($obs['errores']));
        $obs = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Observado', 'motivo' => 'Ilegible']], 'Observada', 'El DNI se lee con dificultad: presentar una copia nítida.', '');
        $this->assertSame([], $obs['errores']);
        $this->assertSame('PendienteDocumentacion', RevisionLegajo::estadoSolicitud('Observada'));
        // Observar con todo conforme es contradictorio
        $todoBien = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Conforme']], 'Observada', 'Sin observaciones reales', '');
        $this->assertSame('Toda la documentación está conforme: corresponde verificar el legajo.', $todoBien['errores']['resultado']);
        $rech = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Rechazado', 'motivo' => 'NoCorresponde']], 'Rechazada', '', '');
        $this->assertArrayHasKey('motivo_rechazo', $rech['errores']);
        $rech = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10]], [10 => ['resultado' => 'Rechazado', 'motivo' => 'NoCorresponde']], 'Rechazada', '', 'El documento presentado pertenece a otra persona.');
        $this->assertSame([], $rech['errores']);
        $this->assertSame('Rechazada', RevisionLegajo::estadoSolicitud('Rechazada'));
    }

    public function testVariosDocumentosDelMismoTipo(): void
    {
        // Tres recibos de sueldo: el requisito se cumple solo si los tres están conformes
        $presentados = ['DOC_IDENTIDAD' => [10], 'RECIBO_SUELDO' => [11, 12, 13]];
        $conformes = [10 => ['resultado' => 'Conforme'], 11 => ['resultado' => 'Conforme'], 12 => ['resultado' => 'Conforme']];
        $uno = RevisionLegajo::validar(['DOC_IDENTIDAD', 'RECIBO_SUELDO'], $presentados, $conformes + [13 => ['resultado' => 'Observado', 'motivo' => 'Vencido']], 'Verificada', '', '');
        $this->assertSame('No puede verificarse: hay documentación exigida que no está conforme.', $uno['errores']['resultado']);
        $todos = RevisionLegajo::validar(['DOC_IDENTIDAD', 'RECIBO_SUELDO'], $presentados, $conformes + [13 => ['resultado' => 'Conforme']], 'Verificada', '', '');
        $this->assertSame([], $todos['errores']);
        // Un documento no exigido observado no impide verificar, pero sí permite observar
        $extra = RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10], 'ESTATUTO' => [20]], [10 => ['resultado' => 'Conforme'], 20 => ['resultado' => 'Observado', 'motivo' => 'NoCorresponde']], 'Verificada', '', '');
        $this->assertSame([], $extra['errores']);
        $this->assertSame([], RevisionLegajo::validar(['DOC_IDENTIDAD'], ['DOC_IDENTIDAD' => [10], 'ESTATUTO' => [20]], [10 => ['resultado' => 'Conforme'], 20 => ['resultado' => 'Observado', 'motivo' => 'NoCorresponde']], 'Observada', 'El estatuto no corresponde a esta empresa.', '')['errores']);
    }

    public function testCadaDocumentoNecesitaResultadoYMotivo(): void
    {
        $r = RevisionLegajo::validar(
            ['DOC_IDENTIDAD', 'RECIBO_SUELDO'],
            ['DOC_IDENTIDAD' => [10], 'RECIBO_SUELDO' => [11]],
            [10 => ['resultado' => 'Observado'], 11 => []],
            'Observada',
            'Faltan datos del recibo de sueldo de octubre.',
            ''
        );
        $this->assertSame('Indique el motivo de la observación del documento.', $r['errores']['doc_10']);
        $this->assertSame('Indique el resultado de este documento.', $r['errores']['doc_11']);
        $this->assertSame(
            'Seleccione el resultado de la revisión: verificada, observada o rechazada.',
            RevisionLegajo::validar([], [], [], '', '', '')['errores']['resultado']
        );
    }
}
