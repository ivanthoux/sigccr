<?php

declare(strict_types=1);

namespace Tests\Feature\Solicitudes;

use App\Exceptions\ConflictoConcurrenciaException;
use App\Services\Solicitudes\LiquidacionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-36 a IN-43 · Solicitud de crédito de punta a punta: registro (CU-22), verificación del legajo (CU-23), corrección
 * (CU-84), evaluación de contingencia (CU-25), confirmación de la alternativa (CU-79), resolución (CU-26),
 * cancelación (CU-28) y liquidación (CU-27). Plan de pruebas 7.9.2 y Anexo B.13.
 *
 * Cada prueba lleva una solicitud nueva hasta el estado que necesita con los servicios (Fabrica::solicitud) y envía
 * el formulario por HTTP. Los rechazos se prueban sobre la misma solicitud, porque no la modifican.
 *
 * @internal
 */
final class SolicitudesTest extends IntegracionTestCase
{
    // ── Utilidades ───────────────────────────────────────────────────────────────────────────────────────────────

    private function estado(int $id): string
    {
        return (new SolicitudService($this->db))->estado($id);
    }

    private function version(int $id): string
    {
        return (string) (new SolicitudService($this->db))->obtener($id)['version_fila'];
    }

    /**
     * Envía cada caso y verifica el rechazo en el campo, sin cambios en la solicitud.
     *
     * @param array<string, mixed> $base
     * @param array<string, array{0: array<string, mixed>, 1: string, 2: ?string}> $casos nombre => [cambios, campo, mensaje]
     */
    private function rechazos(string $ruta, int $id, array $base, array $casos): void
    {
        $estado = $this->estado($id);
        $version = $this->version($id);
        foreach ($casos as $caso => [$cambios, $campo, $mensaje]) {
            $datos = $base;
            foreach ($cambios as $k => $v) {
                $datos = self::conValor($datos, $k, $v);
            }
            $r = $this->enviar($ruta, $datos);
            if ($mensaje === Esperado::PETICION) {
                $this->assertCampoRechazado($r, $campo, $mensaje, $caso);
            } elseif ($campo === '') {
                $this->assertTrue($r->isRedirect(), $caso);
                $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), "{$caso}: falta el mensaje");
            } else {
                $this->assertCampoRechazado($r, $campo, $mensaje, $caso);
            }
            $this->assertSame($estado, $this->estado($id), "{$caso}: la solicitud no debe cambiar de estado");
            $this->assertSame($version, $this->version($id), "{$caso}: la solicitud no debe modificarse");
        }
    }

    /** CBU válido de un banco de prueba (072, sucursal 0045). */
    private static function cbu(int $n = 1): string
    {
        $digito = static function (string $numero, array $pesos): string {
            $suma = 0;
            foreach ($pesos as $i => $peso) {
                $suma += (int) $numero[$i] * $peso;
            }

            return (string) ((10 - $suma % 10) % 10);
        };
        $bloque = '0720045';
        $cuenta = str_pad((string) (90000001 + $n * 137), 13, '0', STR_PAD_LEFT);

        return $bloque . $digito($bloque, [7, 1, 3, 9, 7, 1, 3]) . $cuenta . $digito($cuenta, [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3]);
    }

    private static function hoy(int $dias = 0): string
    {
        return Time::now(config('Sigccr')->zonaNegocio)->addDays($dias)->format('d/m/Y');
    }

    // ── IN-36 Registrar solicitud (CU-22) ────────────────────────────────────────────────────────────────────────

    public function testRegistrarSolicitud(): void
    {
        $this->comoRol('AnalistaCredito');
        $this->fabrica->fondos();
        $cliente = $this->fabrica->clienteParaCredito();
        $linea = $this->fabrica->linea()['id'];
        $simulacion = $this->fabrica->simulacion($cliente['id'], $linea);
        $vencida = $this->fabrica->simulacion($cliente['id'], $linea);
        $this->db->table('simulaciones')->where('id', $vencida)->update(['vence' => '2020-01-01 00:00:00.000 +00:00']);

        foreach (
            [
            'vacía' => '', 'no numérica' => 'abc', 'con letras al final (H-21)' => "{$simulacion}x", 'como lista (H-21)' => [(string) $simulacion],
            'decimal (H-21)' => "{$simulacion}.5", 'negativa' => "-{$simulacion}", 'inexistente' => '999999999', 'vencida (RN-48)' => (string) $vencida,
            ] as $caso => $valor
        ) {
            $antes = $this->ultimoId('solicitudes_credito');
            $this->enviar('admin/solicitudes', ['simulacion_id' => $valor]);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), "simulación {$caso}");
            $this->assertSame($antes, $this->ultimoId('solicitudes_credito'), "simulación {$caso}: no debe registrarse");
        }

        $this->assertAceptado($this->enviar('admin/solicitudes', ['simulacion_id' => " {$simulacion} "]));
        $id = $this->ultimoId('solicitudes_credito');
        $this->assertSame('PendienteVerificacion', $this->estado($id));
        $this->assertSame($cliente['id'], (int) $this->db->table('solicitudes_credito')->where('id', $id)->get()->getRow()->cliente_id);
        $antes = $this->ultimoId('solicitudes_credito');
        $this->enviar('admin/solicitudes', ['simulacion_id' => (string) $simulacion]);
        $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), 'la misma simulación dos veces');
        $this->assertSame($antes, $this->ultimoId('solicitudes_credito'), 'la misma simulación no genera otra solicitud');
    }

    // ── IN-37 Verificar legajo (CU-23) ───────────────────────────────────────────────────────────────────────────

    /** @return array{id: int, docs: list<int>, base: array<string, mixed>} */
    private function paraVerificar(): array
    {
        $s = $this->fabrica->solicitud('PendienteVerificacion');
        $docs = array_map(static fn ($d) => (int) $d['id'], (new SolicitudService($this->db))->obtener($s['id'])['documentos']);
        $verificacion = [];
        foreach ($docs as $d) {
            $verificacion[$d] = ['resultado' => 'Conforme', 'motivo' => '', 'observacion' => ''];
        }

        return ['id' => $s['id'], 'docs' => $docs, 'base' => [
            'version_fila' => $this->version($s['id']), 'resultado' => 'Verificada', 'verificacion' => $verificacion, 'observaciones' => '', 'motivo_rechazo' => '',
        ]];
    }

    public function testVerificarRechazos(): void
    {
        $this->comoRol('AnalistaCredito');
        $v = $this->paraVerificar();
        $d = $v['docs'][0];
        $resultado = 'Seleccione el resultado de la revisión: verificada, observada o rechazada.';
        $doc = 'Indique el resultado de este documento.';
        $motivoDoc = 'Indique el motivo de la observación del documento.';
        $observado = static fn (array $extra): array => ['verificacion' => [$d => $extra + ['resultado' => 'Observado', 'motivo' => 'Ilegible', 'observacion' => '']] + $v['base']['verificacion']];

        $this->rechazos("admin/solicitudes/{$v['id']}/verificar", $v['id'], $v['base'], [
            'resultado vacío' => [['resultado' => ''], 'resultado', $resultado],
            'resultado ausente' => [['resultado' => Esperado::AUSENTE], 'resultado', $resultado],
            'resultado como lista' => [['resultado' => ['Verificada']], 'resultado', $resultado],
            'resultado en minúsculas' => [['resultado' => 'verificada'], 'resultado', $resultado],
            'resultado inexistente' => [['resultado' => 'Aprobada'], 'resultado', $resultado],
            'verificación ausente' => [['verificacion' => Esperado::AUSENTE], "doc_{$d}", $doc],
            'verificación como texto' => [['verificacion' => 'Conforme'], "doc_{$d}", $doc],
            'documento como texto (H-15)' => [['verificacion' => [$d => 'Conforme'] + $v['base']['verificacion']], "doc_{$d}", $doc],
            'resultado del documento como lista (H-15)' => [['verificacion' => [$d => ['resultado' => ['Conforme']]] + $v['base']['verificacion']], "doc_{$d}", $doc],
            'resultado del documento inexistente' => [['verificacion' => [$d => ['resultado' => 'Bien']] + $v['base']['verificacion']], "doc_{$d}", $doc],
            'observado sin motivo' => [$observado(['motivo' => '']), "doc_{$d}", $motivoDoc],
            'observado con motivo inexistente' => [$observado(['motivo' => 'Roto']), "doc_{$d}", $motivoDoc],
            'observado con motivo como lista' => [$observado(['motivo' => ['Ilegible']]), "doc_{$d}", $motivoDoc],
            'observación de 501' => [$observado(['observacion' => str_repeat('o', 501)]), "doc_{$d}", 'La observación admite hasta 500 caracteres.'],
            'observación de 499 y un emoji (H-16)' => [$observado(['observacion' => str_repeat('o', 499) . '😀']), "doc_{$d}", 'La observación admite hasta 500 caracteres.'],
            'verificada con un documento observado' => [$observado([]), 'resultado', 'No puede verificarse: hay documentación exigida que no está conforme.'],
            'observada con todo conforme' => [['resultado' => 'Observada', 'observaciones' => 'Falta el recibo de sueldo'], 'resultado', 'Toda la documentación está conforme: corresponde verificar el legajo.'],
            'observada sin detalle' => [$observado([]) + ['resultado' => 'Observada', 'observaciones' => str_repeat('o', 9)], 'observaciones', 'Detalle los faltantes o inconsistencias que el cliente debe corregir (al menos 10 caracteres).'],
            'observada con 1001' => [$observado([]) + ['resultado' => 'Observada', 'observaciones' => str_repeat('o', 1001)], 'observaciones', 'Las observaciones admiten hasta 1000 caracteres.'],
            'observada con 999 y un emoji (H-16)' => [$observado([]) + ['resultado' => 'Observada', 'observaciones' => str_repeat('o', 999) . '😀'], 'observaciones', 'Las observaciones admiten hasta 1000 caracteres.'],
            'rechazada sin motivo' => [['resultado' => 'Rechazada', 'motivo_rechazo' => 'Baja'], 'motivo_rechazo', 'Indique el motivo del rechazo documental (RN-19). Indique el motivo de la operación (al menos 5 caracteres).'],
            'rechazada con 501 (H-19)' => [['resultado' => 'Rechazada', 'motivo_rechazo' => str_repeat('m', 501)], 'motivo_rechazo', 'Indique el motivo del rechazo documental (RN-19). El motivo admite hasta 500 caracteres.'],
            'rechazada con 499 y un emoji' => [['resultado' => 'Rechazada', 'motivo_rechazo' => str_repeat('m', 499) . '😀'], 'motivo_rechazo', 'Indique el motivo del rechazo documental (RN-19). El motivo admite hasta 500 caracteres.'],
            'caracteres de control' => [['observaciones' => "Ana\0"], 'observaciones', Esperado::PETICION],
        ]);

        // IN-C07 y H-21: una versión enviada como lista, con letras o vacía no pasa el control aunque la vigente sea 1
        $this->assertSame('1', $this->version($v['id']), 'la solicitud recién verificada tiene la versión 1');
        foreach (['como lista' => ['1'], 'con letras' => '1abc', 'decimal' => '1.0', 'vacía' => '', 'ausente' => Esperado::AUSENTE] as $caso => $version) {
            $this->enviar("admin/solicitudes/{$v['id']}/verificar", self::conValor($v['base'], 'version_fila', $version));
            $this->assertSame(ConflictoConcurrenciaException::MENSAJE, $_SESSION['error'] ?? null, "versión {$caso}");
            $this->assertSame('PendienteVerificacion', $this->estado($v['id']), "versión {$caso}");
        }
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function casosVerificar(): array
    {
        return [
            'verificada' => ['Verificada', [], 'Verificada'],
            'verificada con espacios (SEL-04)' => [' Verificada ', [], 'Verificada'],
            'observada con 10 caracteres' => ['Observada', ['observaciones' => str_repeat('o', 10)], 'Observada'],
            'observada con 1000 caracteres' => ['Observada', ['observaciones' => str_repeat('o', 1000)], 'Observada'],
            'observada con 998 y un emoji' => ['Observada', ['observaciones' => str_repeat('o', 998) . '😀'], 'Observada'],
            'observación del documento de 500' => ['Observada', ['observaciones' => 'Recibo de sueldo ilegible', 'observacion' => str_repeat('o', 500)], 'Observada'],
            'rechazada con 5 caracteres' => ['Rechazada', ['motivo_rechazo' => 'Falso'], 'Rechazada'],
            'rechazada con 500 caracteres' => ['Rechazada', ['motivo_rechazo' => str_repeat('m', 500)], 'Rechazada'],
            'rechazada con HTML' => ['Rechazada', ['motivo_rechazo' => '<b>Documento</b> adulterado'], 'Rechazada'],
        ];
    }

    /**
     * IN-37: resultados aceptados; lo ingresado se guarda completo en la revisión.
     *
     * @dataProvider casosVerificar
     * @param array<string, string> $extra
     */
    public function testVerificarAceptados(string $resultado, array $extra, string $revision): void
    {
        $this->comoRol('AnalistaCredito');
        $v = $this->paraVerificar();
        $datos = ['resultado' => $resultado] + $extra + $v['base'];
        if (trim($resultado) === 'Observada') {
            $d = $v['docs'][0];
            $datos['verificacion'][$d] = ['resultado' => 'Observado', 'motivo' => 'Ilegible', 'observacion' => $extra['observacion'] ?? ''];
        }
        unset($datos['observacion']);

        $this->assertAceptado($this->enviar("admin/solicitudes/{$v['id']}/verificar", $datos));
        $r = $this->db->table('revisiones_documentales')->where('solicitud_id', $v['id'])->orderBy('id', 'DESC')->get(1)->getRowArray();
        $this->assertSame($revision, $r['estado']);
        if (isset($extra['observaciones'])) {
            $this->assertSame($extra['observaciones'], $r['observaciones'], 'observaciones completas');
        }
        if (isset($extra['motivo_rechazo'])) {
            $this->assertSame($extra['motivo_rechazo'], $r['motivo_rechazo'], 'motivo completo');
            $this->assertSame('Rechazada', $this->estado($v['id']));
        }
        if (isset($extra['observacion'])) {
            $obs = $this->db->table('verificaciones_documento')->where('documento_legajo_id', $v['docs'][0])->orderBy('id', 'DESC')->get(1)->getRowArray();
            $this->assertSame($extra['observacion'], $obs['observacion']);
        }
    }

    // ── IN-38 Registrar corrección (CU-84) ───────────────────────────────────────────────────────────────────────

    public function testCorreccion(): void
    {
        $this->comoRol('AnalistaCredito');
        $v = $this->paraVerificar();
        $d = $v['docs'][0];
        $observar = ['resultado' => 'Observada', 'observaciones' => 'El recibo de sueldo es ilegible', 'verificacion' => [$d => ['resultado' => 'Observado', 'motivo' => 'Ilegible', 'observacion' => '']] + $v['base']['verificacion']] + $v['base'];
        $this->assertAceptado($this->enviar("admin/solicitudes/{$v['id']}/verificar", $observar));
        $this->assertSame('PendienteDocumentacion', $this->estado($v['id']));

        $falta = 'Adjunte la documentación pedida o registre la aclaración que corresponde (al menos 10 caracteres).';
        $largo = 'La aclaración admite hasta 500 caracteres.';
        $base = ['version_fila' => $this->version($v['id']), 'aclaracion' => 'Aclaración del cliente'];
        $this->rechazos("admin/solicitudes/{$v['id']}/corregir", $v['id'], $base, [
            'vacía' => [['aclaracion' => ''], 'aclaracion', $falta],
            'ausente' => [['aclaracion' => Esperado::AUSENTE], 'aclaracion', $falta],
            'como lista' => [['aclaracion' => ['Aclaración del cliente']], 'aclaracion', $falta],
            '9 caracteres' => [['aclaracion' => str_repeat('a', 9)], 'aclaracion', $falta],
            '9 con espacios' => [['aclaracion' => '  ' . str_repeat('a', 9) . '  '], 'aclaracion', $falta],
            '501 caracteres' => [['aclaracion' => str_repeat('a', 501)], 'aclaracion', $largo],
            '499 y un emoji (H-16)' => [['aclaracion' => str_repeat('a', 499) . '😀'], 'aclaracion', $largo],
            'versión como lista (H-21)' => [['version_fila' => [$base['version_fila']]], '', null],
        ]);
        $this->assertAceptado($this->enviar("admin/solicitudes/{$v['id']}/corregir", ['aclaracion' => str_repeat('a', 498) . '😀'] + $base));

        // Una solicitud que no está observada no admite correcciones
        $otra = $this->fabrica->solicitud('PendienteVerificacion');
        $this->enviar("admin/solicitudes/{$otra['id']}/corregir", ['version_fila' => $this->version($otra['id']), 'aclaracion' => 'Aclaración del cliente']);
        $this->assertStringStartsWith('La solicitud no admite correcciones en su estado actual', (string) ($_SESSION['error'] ?? ''));
    }

    // ── IN-39 Evaluación de contingencia (CU-25) ─────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private static function contingencia(string $version): array
    {
        return [
            'version_fila' => $version, 'antecedentes' => ['informacion_economica', 'documentacion'],
            'detalle_antecedentes' => 'Recibos de sueldo de los últimos tres meses.', 'recomendacion' => 'Favorable',
            'justificacion' => 'Ingresos estables y suficientes para la cuota solicitada.', 'deudas' => [],
        ];
    }

    public function testContingenciaRechazos(): void
    {
        $this->comoRol('AnalistaCredito');
        $s = $this->fabrica->solicitud('PendienteContingencia');
        $id = $s['id'];
        $base = self::contingencia($this->version($id));
        $antecedentes = 'Indique qué antecedentes verificables utilizó.';
        $detalle = 'Describa los antecedentes verificados (al menos 20 caracteres).';
        $recomendacion = 'Seleccione la recomendación: favorable o desfavorable.';
        $justificacion = 'Justifique la recomendación (al menos 20 caracteres).';
        $periodo = Time::now(config('Sigccr')->zonaNegocio)->format('m/Y');
        $deuda = static fn (array $c): array => ['antecedentes' => ['consulta_manual_bcra'], 'deudas' => [$c + ['entidad' => 'Banco de Prueba', 'situacion' => '1', 'monto' => '100.000', 'periodo' => $periodo]]];

        $this->rechazos("admin/solicitudes/{$id}/contingencia", $id, $base, [
            'sin antecedentes' => [['antecedentes' => []], 'antecedentes', $antecedentes],
            'antecedentes ausentes' => [['antecedentes' => Esperado::AUSENTE], 'antecedentes', $antecedentes],
            'antecedente inexistente' => [['antecedentes' => ['adivinanza']], 'antecedentes', $antecedentes],
            'antecedente anidado' => [['antecedentes' => [['documentacion']]], 'antecedentes', $antecedentes],
            'detalle de 19' => [['detalle_antecedentes' => str_repeat('d', 19)], 'detalle_antecedentes', $detalle],
            'detalle de 19 con espacios' => [['detalle_antecedentes' => '   ' . str_repeat('d', 19) . '  '], 'detalle_antecedentes', $detalle],
            'detalle como lista' => [['detalle_antecedentes' => [str_repeat('d', 30)]], 'detalle_antecedentes', $detalle],
            'detalle de 2001' => [['detalle_antecedentes' => str_repeat('d', 2001)], 'detalle_antecedentes', 'La descripción admite hasta 2000 caracteres.'],
            'detalle de 1999 y un emoji (H-16)' => [['detalle_antecedentes' => str_repeat('d', 1999) . '😀'], 'detalle_antecedentes', 'La descripción admite hasta 2000 caracteres.'],
            'recomendación vacía' => [['recomendacion' => ''], 'recomendacion', $recomendacion],
            'recomendación en minúsculas' => [['recomendacion' => 'favorable'], 'recomendacion', $recomendacion],
            'recomendación como lista (H-15)' => [['recomendacion' => ['Favorable']], 'recomendacion', $recomendacion],
            'recomendación inexistente' => [['recomendacion' => 'Neutral'], 'recomendacion', $recomendacion],
            'justificación de 19' => [['justificacion' => str_repeat('j', 19)], 'justificacion', $justificacion],
            'justificación de 2001' => [['justificacion' => str_repeat('j', 2001)], 'justificacion', 'La justificación admite hasta 2000 caracteres.'],
            'justificación de 1999 y un emoji (H-16)' => [['justificacion' => str_repeat('j', 1999) . '😀'], 'justificacion', 'La justificación admite hasta 2000 caracteres.'],
            'deuda sin la consulta manual' => [['deudas' => [['entidad' => 'Banco', 'situacion' => '1', 'monto' => '1000', 'periodo' => $periodo]]], 'deudas.0', 'Las deudas informadas corresponden a la consulta manual a la Central de Deudores: marque ese antecedente.'],
            'deuda sin entidad' => [$deuda(['entidad' => '']), 'deudas.0', 'Indique la entidad informante (hasta 150 caracteres).'],
            'entidad de 151' => [$deuda(['entidad' => str_repeat('e', 151)]), 'deudas.0', 'Indique la entidad informante (hasta 150 caracteres).'],
            'entidad de 149 y un emoji (H-16)' => [$deuda(['entidad' => str_repeat('e', 149) . '😀']), 'deudas.0', 'Indique la entidad informante (hasta 150 caracteres).'],
            'situación 0' => [$deuda(['situacion' => '0']), 'deudas.0', 'La situación debe ser un número de 1 a 6.'],
            'situación 7' => [$deuda(['situacion' => '7']), 'deudas.0', 'La situación debe ser un número de 1 a 6.'],
            'situación como lista (H-15)' => [$deuda(['situacion' => ['1']]), 'deudas.0', 'La situación debe ser un número de 1 a 6.'],
            'monto inválido' => [$deuda(['monto' => 'mucho']), 'deudas.0', 'Indique el monto adeudado en pesos.'],
            'monto como lista (H-15)' => [$deuda(['monto' => ['1000']]), 'deudas.0', 'Indique el monto adeudado en pesos.'],
            'período de hace 13 meses' => [$deuda(['periodo' => Time::now(config('Sigccr')->zonaNegocio)->subMonths(13)->format('m/Y')]), 'deudas.0', 'El período debe tener el formato mm/aaaa y estar dentro de los últimos 12 meses.'],
            'período futuro' => [$deuda(['periodo' => Time::now(config('Sigccr')->zonaNegocio)->addMonths(1)->format('m/Y')]), 'deudas.0', 'El período debe tener el formato mm/aaaa y estar dentro de los últimos 12 meses.'],
            'período inválido' => [$deuda(['periodo' => '13/2026']), 'deudas.0', 'El período debe tener el formato mm/aaaa y estar dentro de los últimos 12 meses.'],
            'período como lista (H-15)' => [$deuda(['periodo' => [$periodo]]), 'deudas.0', 'El período debe tener el formato mm/aaaa y estar dentro de los últimos 12 meses.'],
        ]);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function casosContingencia(): array
    {
        return [
            'mínimos de 20 caracteres' => [['detalle_antecedentes' => str_repeat('d', 20), 'justificacion' => str_repeat('j', 20)]],
            'máximos de 2000 caracteres' => [['detalle_antecedentes' => str_repeat('d', 2000), 'justificacion' => str_repeat('j', 2000)]],
            '1998 y un emoji' => [['detalle_antecedentes' => str_repeat('d', 1998) . '😀', 'justificacion' => str_repeat('j', 1998) . '😀']],
            'recomendación con espacios (SEL-04)' => [['recomendacion' => ' Desfavorable ']],
            'antecedente como texto' => [['antecedentes' => 'documentacion']],
            'deuda informada' => [['antecedentes' => ['consulta_manual_bcra'], 'deudas' => [['entidad' => 'Banco de Prueba', 'situacion' => ' 2 ', 'monto' => '$ 150.000,50', 'periodo' => 'MES']]]],
            'filas de deuda vacías y como texto' => [['deudas' => [['entidad' => '', 'situacion' => '', 'monto' => '', 'periodo' => ''], 'texto']]],
        ];
    }

    /**
     * IN-39: la contingencia registrada pasa la solicitud a resolución con lo ingresado completo.
     *
     * @dataProvider casosContingencia
     * @param array<string, mixed> $cambios
     */
    public function testContingenciaAceptada(array $cambios): void
    {
        $this->comoRol('AnalistaCredito');
        $s = $this->fabrica->solicitud('PendienteContingencia');
        if (($cambios['deudas'][0]['periodo'] ?? '') === 'MES') {
            $cambios['deudas'][0]['periodo'] = Time::now(config('Sigccr')->zonaNegocio)->format('m/Y');
        }
        $datos = $cambios + self::contingencia($this->version($s['id']));

        $this->assertAceptado($this->enviar("admin/solicitudes/{$s['id']}/contingencia", $datos));
        $this->assertSame('PendienteResolucion', $this->estado($s['id']));
        $e = $this->db->table('evaluaciones_crediticias')->where('solicitud_id', $s['id'])->where('modalidad', 'Contingencia')->get()->getRowArray();
        $this->assertSame(trim((string) $datos['recomendacion']), $e['recomendacion']);
        $this->assertSame($datos['justificacion'], $e['justificacion']);
        if (isset($cambios['deudas'][0]['entidad']) && $cambios['deudas'][0]['entidad'] !== '') {
            $a = $this->db->table('antecedentes_externos')->where('evaluacion_id', $e['id'])->get()->getRowArray();
            $this->assertSame(['Banco de Prueba', 2], [$a['entidad'], (int) $a['situacion']]);
            $this->assertValorGuardado('150000.50', $a['monto']);
        }
    }

    // ── IN-40 Confirmar alternativa (CU-79) ──────────────────────────────────────────────────────────────────────

    public function testConfirmarAlternativa(): void
    {
        $this->comoRol('AnalistaCredito');
        $s = $this->fabrica->solicitud('PendienteSeleccionPlan');
        $alternativa = (int) $this->fabrica->alternativas($s['id'])[0]['id'];
        $ruta = "admin/solicitudes/{$s['id']}/alternativas/{$alternativa}/confirmar";
        $base = ['version_fila' => $this->version($s['id']), 'fecha_conformidad' => self::hoy(), 'medio_conformidad' => 'PresencialFirma', 'observaciones' => ''];
        $fecha = 'Indique la fecha en que el cliente prestó su conformidad (dd/mm/aaaa).';
        $medio = 'Seleccione el medio de la conformidad.';

        $this->rechazos($ruta, $s['id'], $base, [
            'fecha vacía' => [['fecha_conformidad' => ''], 'fecha_conformidad', $fecha],
            'fecha como lista' => [['fecha_conformidad' => [self::hoy()]], 'fecha_conformidad', $fecha],
            'fecha inválida' => [['fecha_conformidad' => '31/02/2026'], 'fecha_conformidad', $fecha],
            'fecha futura' => [['fecha_conformidad' => self::hoy(1)], 'fecha_conformidad', 'La fecha de conformidad no puede ser futura.'],
            'fecha anterior a la alternativa' => [['fecha_conformidad' => self::hoy(-1)], 'fecha_conformidad', 'La conformidad no puede ser anterior a la generación de la alternativa.'],
            'medio vacío' => [['medio_conformidad' => ''], 'medio_conformidad', $medio],
            'medio inexistente' => [['medio_conformidad' => 'Telefono'], 'medio_conformidad', $medio],
            'medio en minúsculas' => [['medio_conformidad' => 'digital'], 'medio_conformidad', $medio],
            'medio como lista (H-15)' => [['medio_conformidad' => ['Digital']], 'medio_conformidad', $medio],
            'observaciones de 501' => [['observaciones' => str_repeat('o', 501)], 'observaciones', 'Las observaciones admiten hasta 500 caracteres.'],
            'observaciones de 499 y un emoji (H-16)' => [['observaciones' => str_repeat('o', 499) . '😀'], 'observaciones', 'Las observaciones admiten hasta 500 caracteres.'],
            'versión como lista (H-21)' => [['version_fila' => [$base['version_fila']]], '', null],
        ]);
        $this->enviar("admin/solicitudes/{$s['id']}/alternativas/999999999/confirmar", $base);
        $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), 'alternativa inexistente');

        $this->assertAceptado($this->enviar($ruta, ['medio_conformidad' => ' Digital ', 'observaciones' => str_repeat('o', 498) . '😀'] + $base));
        $this->assertSame('AprobadaPendienteLiquidacion', $this->estado($s['id']));
        $a = $this->db->table('alternativas_financiacion')->where('id', $alternativa)->get()->getRowArray();
        $this->assertSame(['Confirmada', 'Digital'], [$a['estado'], $a['medio_conformidad']]);
    }

    // ── IN-41 Resolver (CU-26) ───────────────────────────────────────────────────────────────────────────────────

    public function testResolverRechazos(): void
    {
        $this->comoRol('Gerente');
        $s = $this->fabrica->solicitud('PendienteResolucion');
        $base = ['version_fila' => $this->version($s['id']), 'decision' => 'Rechazada', 'fundamento' => 'Ingresos insuficientes para la cuota.'];
        $decision = 'Seleccione la decisión: aprobar, rechazar o devolver para revisión.';
        $plan = 'Seleccione el plan que se aprueba: una alternativa vigente o un importe y cuotas definidos.';
        $aprobar = ['decision' => 'Aprobada', 'plan' => 'definida', 'importe' => '500.000,00', 'cantidad_cuotas' => '12'];

        $this->rechazos("admin/solicitudes/{$s['id']}/resolver", $s['id'], $base, [
            'decisión vacía' => [['decision' => ''], 'decision', $decision],
            'decisión como lista (H-15)' => [['decision' => ['Rechazada']], 'decision', $decision],
            'decisión en minúsculas' => [['decision' => 'rechazada'], 'decision', $decision],
            'decisión inexistente' => [['decision' => 'Postergada'], 'decision', $decision],
            'rechazo con fundamento de 9' => [['fundamento' => str_repeat('f', 9)], 'fundamento', 'Indique el motivo del rechazo (al menos 10 caracteres).'],
            'aprobación con fundamento de 9' => [$aprobar + ['fundamento' => str_repeat('f', 9)], 'fundamento', 'Indique el fundamento de la decisión (al menos 10 caracteres).'],
            'fundamento como lista' => [['fundamento' => ['Ingresos insuficientes']], 'fundamento', 'Indique el motivo del rechazo (al menos 10 caracteres).'],
            'fundamento de 2001' => [['fundamento' => str_repeat('f', 2001)], 'fundamento', 'El fundamento admite hasta 2000 caracteres.'],
            'fundamento de 1999 y un emoji (H-16)' => [['fundamento' => str_repeat('f', 1999) . '😀'], 'fundamento', 'El fundamento admite hasta 2000 caracteres.'],
            'devolución sin etapa' => [['decision' => 'DevueltaRevision', 'etapa_retorno' => ''], 'etapa_retorno', 'Indique la etapa que debe retomarse.'],
            'devolución con etapa inexistente' => [['decision' => 'DevueltaRevision', 'etapa_retorno' => 'Liquidacion'], 'etapa_retorno', 'Indique la etapa que debe retomarse.'],
            'devolución con etapa como lista (H-15)' => [['decision' => 'DevueltaRevision', 'etapa_retorno' => ['Evaluacion']], 'etapa_retorno', 'Indique la etapa que debe retomarse.'],
            'aprobación sin plan' => [['plan' => ''] + $aprobar, 'plan', $plan],
            'aprobación con plan como lista (H-15)' => [['plan' => ['definida']] + $aprobar, 'plan', $plan],
            'aprobación de una confirmada inexistente' => [['plan' => 'confirmada'] + $aprobar, 'plan', $plan],
            'alternativa no numérica' => [['plan' => 'alternativa', 'alternativa_id' => 'abc'] + $aprobar, 'alternativa_id', 'Seleccione la alternativa que se aprueba.'],
            'alternativa como lista (H-15)' => [['plan' => 'alternativa', 'alternativa_id' => ['1']] + $aprobar, 'alternativa_id', 'Seleccione la alternativa que se aprueba.'],
            'importe vacío' => [['importe' => ''] + $aprobar, 'importe', 'Ingrese el importe que se aprueba, por ejemplo 500.000,00.'],
            'importe inválido' => [['importe' => 'abc'] + $aprobar, 'importe', 'Ingrese el importe que se aprueba, por ejemplo 500.000,00.'],
            'cuotas vacías' => [['cantidad_cuotas' => ''] + $aprobar, 'cantidad_cuotas', 'Ingrese la cantidad de cuotas.'],
            'cuotas cero' => [['cantidad_cuotas' => '0'] + $aprobar, 'cantidad_cuotas', 'Ingrese la cantidad de cuotas.'],
            'cuotas como lista (H-15)' => [['cantidad_cuotas' => ['12']] + $aprobar, 'cantidad_cuotas', 'Ingrese la cantidad de cuotas.'],
            'importe fuera de la línea' => [['importe' => '5.000.000,00'] + $aprobar, '', null],
            'cuotas fuera de la línea' => [['cantidad_cuotas' => '120'] + $aprobar, '', null],
            'versión como lista (H-21)' => [['version_fila' => [$base['version_fila']]], '', null],
        ]);
    }

    /** @return array<string, array{0: array<string, string>, 1: string}> */
    public static function casosResolver(): array
    {
        return [
            'rechazo con 10 caracteres' => [['decision' => 'Rechazada', 'fundamento' => str_repeat('f', 10)], 'Rechazada'],
            'rechazo con 2000 caracteres' => [['decision' => 'Rechazada', 'fundamento' => str_repeat('f', 2000)], 'Rechazada'],
            'rechazo con 1998 y un emoji' => [['decision' => 'Rechazada', 'fundamento' => str_repeat('f', 1998) . '😀'], 'Rechazada'],
            'decisión con espacios (SEL-04)' => [['decision' => ' Rechazada ', 'fundamento' => 'Ingresos insuficientes para la cuota.'], 'Rechazada'],
            'devolución a evaluación' => [['decision' => 'DevueltaRevision', 'etapa_retorno' => 'Evaluacion', 'fundamento' => 'Revisar los antecedentes informados.'], 'DevueltaRevision'],
            'aprobación de un plan definido' => [['decision' => 'Aprobada', 'plan' => 'definida', 'importe' => '400.000,00', 'cantidad_cuotas' => ' 12 ', 'fundamento' => 'Ingresos estables y suficientes.'], 'AprobadaPendienteLiquidacion'],
        ];
    }

    /**
     * IN-41: decisiones aceptadas; el fundamento se guarda completo en la resolución.
     *
     * @dataProvider casosResolver
     * @param array<string, string> $datos
     */
    public function testResolverAceptado(array $datos, string $estado): void
    {
        $this->comoRol('Gerente');
        $s = $this->fabrica->solicitud('PendienteResolucion');

        $this->assertAceptado($this->enviar("admin/solicitudes/{$s['id']}/resolver", ['version_fila' => $this->version($s['id'])] + $datos));
        $this->assertSame($estado, $this->estado($s['id']));
        $r = $this->db->table('resoluciones')->where('solicitud_id', $s['id'])->orderBy('id', 'DESC')->get(1)->getRowArray();
        $this->assertSame($datos['fundamento'], $r['fundamento'], 'fundamento completo');
        if ($estado === 'AprobadaPendienteLiquidacion') {
            $this->assertValorGuardado('400000.00', $r['importe_aprobado']);
        }
    }

    // ── IN-42 Cancelar (CU-28) ───────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: array<string, mixed>, 1: Esperado, 2: array<string, mixed>}> */
    public static function casosCancelar(): array
    {
        $r = [];
        foreach (self::casosDelCatalogo('IN-42', ['motivo' => ['tipo' => 'MOT', 'obligatorio' => true, 'max' => 1000]]) as $nombre => [$campo, $valor, $esperado, $otros]) {
            $r[$nombre] = [$valor, $esperado, $otros];
        }

        return $r;
    }

    /**
     * IN-42: motivo de la cancelación con cada clase de dato.
     *
     * @dataProvider casosCancelar
     * @param array<string, mixed> $otros
     */
    public function testCancelarConCadaClaseDeMotivo(mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('AnalistaCredito');
        $s = $this->fabrica->solicitud('PendienteVerificacion');

        $r = $this->enviar("admin/solicitudes/{$s['id']}/cancelar", self::conValor(['version_fila' => $this->version($s['id'])], 'motivo', $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "motivo: {$esperado}");
            $this->assertSame('Cancelada', $this->estado($s['id']));
            $auditoria = $this->db->table('registros_auditoria')->where('objeto_tipo', 'solicitudes_credito')->where('objeto_id', (string) $s['id'])->like('accion', 'cancelar')->get()->getRowArray();
            $this->assertValorGuardado($esperado->guardado, $auditoria['motivo'] ?? null, 'motivo en la auditoría');

            return;
        }
        $this->assertCampoRechazado($r, 'motivo', $esperado->mensaje, "motivo: {$esperado}");
        $this->assertSame('PendienteVerificacion', $this->estado($s['id']));
    }

    /** CU-28 A2 (RN-41): una aprobada exige confirmar que se libera la reserva; una finalizada no se cancela. */
    public function testCancelarAprobadaYFinalizada(): void
    {
        $this->comoRol('AnalistaCredito');
        $s = $this->fabrica->solicitud('AprobadaPendienteLiquidacion');
        $base = ['version_fila' => $this->version($s['id']), 'motivo' => 'El cliente desistió del crédito'];
        $this->assertCampoRechazado($this->enviar("admin/solicitudes/{$s['id']}/cancelar", $base), 'confirma_reserva', 'La solicitud está aprobada: confirme que la cancelación deja sin efecto la aprobación y libera la reserva de fondos (RN-41).');
        $this->assertAceptado($this->enviar("admin/solicitudes/{$s['id']}/cancelar", ['confirma_reserva' => '1'] + $base));
        $this->assertSame('Cancelada', $this->estado($s['id']));
        $this->enviar("admin/solicitudes/{$s['id']}/cancelar", ['version_fila' => $this->version($s['id'])] + $base);
        $this->assertStringStartsWith('La solicitud ya finalizó (cancelada)', (string) ($_SESSION['error'] ?? ''));
    }

    // ── IN-43 Liquidar (CU-27) ───────────────────────────────────────────────────────────────────────────────────

    /** @return array{id: int, base: array<string, mixed>} */
    private function paraLiquidar(): array
    {
        $s = $this->fabrica->solicitud('ConConstancia');
        $datos = (new LiquidacionService($this->db))->datos($s['id']);

        return ['id' => $s['id'], 'base' => [
            'version_fila' => (string) $datos['version_fila'], 'documento_verificado' => (string) $s['cliente']['datos']['nro_documento'],
            'conformidad' => '', 'cbu' => self::cbu(), 'cuit_titular' => (string) $s['cliente']['datos']['cuit_cuil'],
            'evidencia_titularidad' => 'Constancia de CBU presentada por el titular', 'comprobante' => 'OP-' . Fabrica::sufijo(),
            'fecha_transferencia' => self::hoy(), 'importe_transferencia' => str_replace('.', ',', $datos['calculo']['neto']->toFixed(2)),
        ]];
    }

    public function testLiquidarRechazos(): void
    {
        $this->comoRol('Cajero');
        $l = $this->paraLiquidar();
        $b = $l['base'];
        $cbu = 'Ingrese un CBU válido de 22 dígitos.';
        $importe = 'El importe transferido debe ser el neto a desembolsar.';
        $fecha = 'Ingrese la fecha de la transferencia (no futura).';
        $comprobante = 'Ingrese el número de operación de la transferencia.';

        $this->rechazos("admin/liquidaciones/{$l['id']}", $l['id'], $b, [
            'documento vacío' => [['documento_verificado' => ''], 'documento_verificado', 'Ingrese el número del documento que presenta el destinatario.'],
            'documento como lista' => [['documento_verificado' => [$b['documento_verificado']]], 'documento_verificado', 'Ingrese el número del documento que presenta el destinatario.'],
            'documento de otra persona' => [['documento_verificado' => '12345678'], 'documento_verificado', 'El documento no coincide con el del cliente: no se puede desembolsar a un tercero (RN-25).'],
            'CBU vacío' => [['cbu' => ''], 'cbu', $cbu],
            'CBU de 21 dígitos' => [['cbu' => substr($b['cbu'], 0, 21)], 'cbu', $cbu],
            'CBU con verificador incorrecto' => [['cbu' => substr($b['cbu'], 0, 21) . ((int) substr($b['cbu'], -1) + 1) % 10], 'cbu', $cbu],
            'CBU como lista (H-15)' => [['cbu' => [$b['cbu']]], 'cbu', $cbu],
            'CVU de billetera' => [['cbu' => '0000003100010000000001'], 'cbu', null],
            'titular vacío' => [['cuit_titular' => ''], 'cuit_titular', null],
            'titular de un tercero' => [['cuit_titular' => Fabrica::cuitValido('20')], 'cuit_titular', null],
            'titular como lista (H-15)' => [['cuit_titular' => [$b['cuit_titular']]], 'cuit_titular', null],
            'evidencia de 9' => [['evidencia_titularidad' => str_repeat('e', 9)], 'evidencia_titularidad', 'Describa cómo verificó la titularidad de la cuenta (al menos 10 caracteres).'],
            'evidencia como lista' => [['evidencia_titularidad' => ['Constancia de CBU']], 'evidencia_titularidad', 'Describa cómo verificó la titularidad de la cuenta (al menos 10 caracteres).'],
            'comprobante vacío' => [['comprobante' => ''], 'comprobante', $comprobante],
            'comprobante de 61' => [['comprobante' => str_repeat('c', 61)], 'comprobante', $comprobante],
            'comprobante con un carácter fuera de Latin-1 (H-17)' => [['comprobante' => 'OP-ł-1'], 'comprobante', null],
            'fecha vacía' => [['fecha_transferencia' => ''], 'fecha_transferencia', $fecha],
            'fecha futura' => [['fecha_transferencia' => self::hoy(1)], 'fecha_transferencia', $fecha],
            'fecha inválida' => [['fecha_transferencia' => '30/02/2026'], 'fecha_transferencia', $fecha],
            'importe vacío' => [['importe_transferencia' => ''], 'importe_transferencia', $importe],
            'importe distinto del neto' => [['importe_transferencia' => '1,00'], 'importe_transferencia', $importe],
            'importe como lista' => [['importe_transferencia' => [$b['importe_transferencia']]], 'importe_transferencia', $importe],
            'versión como lista (H-21)' => [['version_fila' => [$b['version_fila']]], '', null],
        ]);
    }

    public function testLiquidarAceptada(): void
    {
        $this->comoRol('Cajero');
        $l = $this->paraLiquidar();
        $cbu = $l['base']['cbu'];
        $datos = [
            'cbu' => substr($cbu, 0, 8) . ' ' . substr($cbu, 8), 'cuit_titular' => substr($l['base']['cuit_titular'], 0, 2) . '-' . substr($l['base']['cuit_titular'], 2, 8) . '-' . substr($l['base']['cuit_titular'], -1),
            'documento_verificado' => number_format((float) $l['base']['documento_verificado'], 0, '', '.'), 'comprobante' => str_repeat('c', 60),
            'evidencia_titularidad' => '  Constancia de CBU del titular  ',
        ] + $l['base'];

        $this->assertAceptado($this->enviar("admin/liquidaciones/{$l['id']}", $datos));
        $this->assertSame('Liquidada', $this->estado($l['id']));
        $liq = $this->db->table('liquidaciones')->where('solicitud_id', $l['id'])->get()->getRowArray();
        $this->assertSame($cbu, $liq['cbu'] ?? $cbu);
        $this->assertStringContainsString(str_repeat('c', 60), (string) $liq['comprobante'], 'el número de operación se guarda completo');

        $this->enviar("admin/liquidaciones/{$l['id']}", ['version_fila' => $this->version($l['id'])] + $datos);
        $this->assertStringStartsWith('La solicitud ya fue liquidada', (string) ($_SESSION['error'] ?? ''), 'RN-26: sin desembolso repetido');
    }
}
