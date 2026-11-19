<?php

declare(strict_types=1);

namespace Tests\Feature\Parametros;

use App\Libraries\Validacion\ValidadorParametrosCampanas as C;
use App\Libraries\Validacion\ValidadorParametrosEstructuracion as E;
use App\Libraries\Validacion\ValidadorParametrosEvaluacion as V;
use App\Libraries\Validacion\ValidadorParametrosMorosidad as M;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-30 a IN-33 · Parámetros de negocio (CU-58, CU-59, CU-60, CU-51; RN-15, RN-69). Plan de pruebas 7.9.2 y
 * Anexo B.11. Cada formulario registra una versión nueva de su grupo de parámetros (D-48).
 *
 * @internal
 */
final class ParametrosTest extends IntegracionTestCase
{
    private const TIPOS = ['evaluacion' => 'Evaluacion', 'estructuracion' => 'Estructuracion', 'morosidad' => 'Morosidad', 'campanas' => 'Campanas'];

    /** @return array<string, array<string, array<string, mixed>>> formulario => campo => definición (Anexo B.11) */
    public static function campos(): array
    {
        $afectacion = 'El porcentaje máximo de afectación debe estar entre 1 % y 100 %.';
        $iva = 'La alícuota de IVA debe estar entre 0 % (operación exenta) y 100 %.';
        $dias = 'La vigencia de las alternativas debe estar entre 1 y 30 días corridos.';
        $paso = 'Ingrese el múltiplo de redondeo del monto máximo (por ejemplo, 1.000,00).';
        $tope = 'Ingrese un importe mayor que cero o déjelo vacío para no aplicar tope.';
        $punitoria = 'La tasa punitoria diaria debe estar entre 0 % y 1 % por día.';
        $topeTna = 'El tope debe estar entre 0 % y 50 % de la TNA: los punitorios no pueden superar la mitad de la tasa compensatoria (art. 769 del Código Civil y Comercial).';
        $tolerancia = 'La tolerancia debe ser un número de días corridos entre 0 y 30.';
        $umbral = 'El umbral de morosidad avanzada debe ser un número de días entre 1 y 365.';
        $condonacion = 'El límite de condonación debe estar entre 0 % y 100 % de los punitorios pendientes (RN-38).';
        $minima = 'Ingrese la TNA mínima de refinanciación (entre 0 % y 500 %).';
        $maxima = 'Ingrese la TNA máxima de refinanciación (entre 0 % y 500 %).';
        $cuotas = 'La cantidad máxima de cuotas de una refinanciación debe estar entre 1 y 120.';
        $m = static fn (string $msg, array $extra = []): array => $extra + ['obligatorio' => $msg, 'formato' => $msg, 'maximo' => $msg, 'minimo' => $msg];

        return [
            'estructuracion' => [
                'porcentaje_afectacion' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => '100', 'mensajes' => $m($afectacion, ['cero' => $afectacion])],
                'alicuota_iva' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => '100', 'mensajes' => $m($iva)],
                'vigencia_alternativas_dias' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 1, 'maximo' => 30, 'normal' => '7', 'mensajes' => $m($dias)],
                'paso_redondeo' => ['tipo' => 'IMP', 'obligatorio' => true, 'mensajes' => $m($paso, ['cero' => $paso])],
                'tope_global_cliente' => ['tipo' => 'IMP', 'obligatorio' => false, 'mensajes' => $m($tope, ['cero' => $tope])],
            ],
            'morosidad' => [
                'tasa_punitoria_diaria' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 4, 'maximo' => '1', 'muestra' => '0', 'mensajes' => $m($punitoria)],
                'tope_sobre_tna' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => '50', 'mensajes' => $m($topeTna)],
                'tolerancia_dias' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 0, 'maximo' => 30, 'normal' => '3', 'mensajes' => $m($tolerancia)],
                'umbral_morosidad_avanzada_dias' => [
                    'tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 4, 'maximo' => 365, 'normal' => '60',
                    'mensajes' => $m($umbral, ['minimo' => 'El umbral de morosidad avanzada debe ser mayor que la tolerancia (RN-65).']),
                ],
                'limite_condonacion' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => '100', 'mensajes' => $m($condonacion)],
                // La mínima debe ser menor que la máxima (80 %): su límite de 500 % se prueba aparte
                'refinanciacion_tasa_minima' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => null, 'mensajes' => $m($minima)],
                'refinanciacion_tasa_maxima' => [
                    'tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => '500', 'otros' => ['refinanciacion_tasa_minima' => '0'],
                    'mensajes' => $m($maxima, ['cero' => 'La TNA máxima de refinanciación debe ser mayor que la mínima.']),
                ],
                'refinanciacion_cuotas_maximas' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 1, 'maximo' => 120, 'normal' => '36', 'mensajes' => $m($cuotas)],
            ],
            'campanas' => [
                'umbral_disponibilidad' => ['tipo' => 'IMP', 'obligatorio' => true, 'mensajes' => $m('Ingrese el umbral de disponibilidad: un importe mayor o igual a cero.')],
                'periodo_proyeccion_dias' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 1, 'maximo' => 180, 'normal' => '30', 'mensajes' => $m('El período de proyección debe ser de 1 a 180 días.')],
                'factor_cobrabilidad' => ['tipo' => 'PCT', 'obligatorio' => true, 'decimales' => 2, 'maximo' => '100', 'mensajes' => $m('El factor de cobrabilidad debe estar entre 0 % y 100 %.')],
                'reserva_minima' => ['tipo' => 'IMP', 'obligatorio' => true, 'mensajes' => $m('Ingrese la reserva mínima de fondos: un importe mayor o igual a cero.')],
                'capital_maximo' => ['tipo' => 'IMP', 'obligatorio' => true, 'mensajes' => $m('El capital máximo de la campaña debe ser mayor que cero.', ['cero' => 'El capital máximo de la campaña debe ser mayor que cero.'])],
                'cantidad_maxima_ofertas' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 1, 'maximo' => 10000, 'normal' => '200', 'mensajes' => $m('La cantidad máxima de ofertas debe estar entre 1 y 10.000.')],
                'antiguedad_minima_meses' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 0, 'maximo' => 240, 'normal' => '12', 'mensajes' => $m('La antigüedad mínima debe ser de 0 a 240 meses.')],
                'morosidad_maxima_dias' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 0, 'maximo' => 365, 'normal' => '30', 'mensajes' => $m('La morosidad máxima admitida debe ser de 0 a 365 días de atraso.')],
                'mensaje' => ['tipo' => 'TXT', 'obligatorio' => false, 'max' => 500, 'vacio_guardado' => '', 'mensajes' => ['largo' => 'El mensaje admite hasta 500 caracteres.']],
                'duracion_dias' => ['tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 1, 'maximo' => 90, 'normal' => '15', 'mensajes' => $m('La duración de la campaña debe ser de 1 a 90 días.')],
                'admite_obligaciones_vigentes' => ['tipo' => 'CHK'],
                'cierre_por_disponibilidad' => ['tipo' => 'CHK'],
            ],
            // Comunes a los cuatro formularios
            'comunes' => [
                'fecha_vigencia' => [
                    'tipo' => 'FEC', 'obligatorio' => true, 'anio_valido' => (int) date('Y') + 2, 'admite_pasado' => false,
                    'mensajes' => ['obligatorio' => 'Ingrese la fecha desde la que rige la nueva configuración.', 'formato' => 'Ingrese la fecha desde la que rige la nueva configuración.'],
                ],
                'motivo' => ['tipo' => 'MOT', 'obligatorio' => true, 'max' => 500],
            ],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: mixed, 3: Esperado, 4: array<string, mixed>}> */
    public static function casos(): array
    {
        $r = [];
        $numero = ['estructuracion' => 'IN-31', 'morosidad' => 'IN-32', 'campanas' => 'IN-33'];
        foreach (self::campos() as $formulario => $campos) {
            if ($formulario === 'comunes') {
                foreach (array_keys(self::TIPOS) as $f) {
                    foreach (self::casosDelCatalogo('IN-30 ' . $f, $campos) as $nombre => $c) {
                        $r[$nombre] = [$f, ...$c];
                    }
                }
                continue;
            }
            foreach (self::casosDelCatalogo($numero[$formulario], $campos) as $nombre => $c) {
                $r[$nombre] = [$formulario, ...$c];
            }
        }

        return $r;
    }

    /**
     * IN-30 a IN-33: cada campo con cada clase de dato.
     *
     * @dataProvider casos
     * @param array<string, mixed> $otros
     */
    public function testCampoConCadaClaseDeDato(string $formulario, string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Gerente');
        $antes = $this->ultimaVersion($formulario);

        $r = $this->enviar("admin/parametros/{$formulario}", self::conValor($this->base($formulario), $campo, $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$formulario}.{$campo}: {$esperado}");
            $v = $this->ultimaVersion($formulario);
            $this->assertSame((int) $antes['numero'] + 1, (int) $v['numero'], 'Debe registrarse una versión nueva');
            $this->assertValorGuardado($esperado->guardado, $this->guardado($v, $campo), "{$formulario}.{$campo}");

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$formulario}.{$campo}: {$esperado}");
        $this->assertSame($antes['numero'], $this->ultimaVersion($formulario)['numero'], 'Un rechazo no debe registrar una versión');
    }

    /** RN-15: la fecha de vigencia no puede ser anterior a hoy; hoy rige desde el momento del registro. */
    public function testFechaDeVigencia(): void
    {
        $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        foreach (array_keys(self::TIPOS) as $f) {
            $antes = $this->ultimaVersion($f)['numero'];
            $this->enviar("admin/parametros/{$f}", ['fecha_vigencia' => $hoy->subDays(1)->format('d/m/Y')] + $this->base($f));
            $this->assertSame('La nueva configuración no puede regir desde una fecha anterior a hoy: no se admite retroactividad.', $_SESSION['error'] ?? null, $f);
            $this->assertSame($antes, $this->ultimaVersion($f)['numero']);
            $this->assertAceptado($this->enviar("admin/parametros/{$f}", ['fecha_vigencia' => $hoy->format('d/m/Y')] + $this->base($f)), "{$f} desde hoy");
        }
    }

    /**
     * Forma de los datos: un grupo enviado como texto o un valor enviado como lista no debe responder error 500 (H-15);
     * se rechaza sin registrar una versión.
     */
    public function testFormaDeLosDatos(): void
    {
        $this->comoRol('Gerente');
        foreach (array_keys(self::TIPOS) as $f) {
            $base = $this->base($f);
            foreach (self::hojas($base) as $ruta) {
                foreach (['como lista' => ['x'], 'como texto' => 'x'] as $forma => $valor) {
                    $datos = self::reemplazar($base, $ruta, $valor);
                    $antes = $this->ultimaVersion($f)['numero'];
                    $r = $this->enviar("admin/parametros/{$f}", $datos);
                    $this->assertTrue($r->isRedirect(), "{$f} " . implode('.', $ruta) . " {$forma}");
                    if (($_SESSION['errores'] ?? []) === [] && !isset($_SESSION['error'])) {
                        // Aceptado: solo si el campo admite vacío o ignora la forma (por ejemplo, una casilla)
                        $this->assertSame((int) $antes + 1, (int) $this->ultimaVersion($f)['numero'], "{$f} " . implode('.', $ruta) . " {$forma}: aceptado sin versión");
                    } else {
                        $this->assertSame($antes, $this->ultimaVersion($f)['numero'], "{$f} " . implode('.', $ruta) . " {$forma}: rechazado con versión");
                    }
                }
            }
        }
    }

    /** IN-30 (CU-58): pesos, situación BCRA, tramos, categorías y vigencia de la evaluación (RN-69). */
    public function testEvaluacion(): void
    {
        $this->comoRol('Gerente');
        $base = $this->base('evaluacion');
        $pesos = 'Los pesos deben sumar 100 % (suman ';
        $casos = [
            'pesos 2 decimales que suman 100' => [['pesos' => ['bcra' => '33,33', 'comportamiento' => '33,33', 'endeudamiento' => '33,34', 'antiguedad' => '0']], null, null],
            'peso con 3 decimales (H-20)' => [['pesos' => ['bcra' => '40,001'] + $base['pesos']], 'pesos.bcra', 'El peso de Situación BCRA debe estar entre 0 % y 100 %.'],
            'peso mayor que 100' => [['pesos' => ['bcra' => '101'] + $base['pesos']], 'pesos.bcra', 'El peso de Situación BCRA debe estar entre 0 % y 100 %.'],
            'peso vacío' => [['pesos' => ['bcra' => ''] + $base['pesos']], 'pesos.bcra', 'El peso de Situación BCRA debe estar entre 0 % y 100 %.'],
            'pesos que suman 99,99' => [['pesos' => ['bcra' => '39,99'] + $base['pesos']], 'pesos', $pesos . '99.99 %).'],
            'BCRA 100 y 0' => [['bcra' => ['1' => '100', '6' => '0'] + $base['bcra']], null, null],
            'BCRA 101' => [['bcra' => ['1' => '101'] + $base['bcra']], 'bcra.1', 'Cada valor de la situación BCRA debe ser un entero entre 0 y 100.'],
            'BCRA decimal' => [['bcra' => ['2' => '60,5'] + $base['bcra']], 'bcra.2', 'Cada valor de la situación BCRA debe ser un entero entre 0 y 100.'],
            'BCRA sin deudas vacío' => [['bcra' => ['sin_deudas' => ''] + $base['bcra']], 'bcra.sin_deudas', 'Cada valor de la situación BCRA debe ser un entero entre 0 y 100.'],
            'tramos de atraso decrecientes' => [['comportamiento' => ['tramos' => [['limite' => '30', 'valor' => '70'], ['limite' => '0', 'valor' => '100']]] + $base['comportamiento']], 'comportamiento.tramos', 'Los días de atraso deben crecer de un tramo al siguiente.'],
            'tramo con valor 101' => [['comportamiento' => ['tramos' => [['limite' => '0', 'valor' => '101']]] + $base['comportamiento']], 'comportamiento.tramos', 'Complete cada tramo con un límite válido y un valor entero entre 0 y 100.'],
            'sin tramos' => [['antiguedad' => ['tramos' => [['limite' => '', 'valor' => '']]] + $base['antiguedad']], 'antiguedad.tramos', 'Defina al menos un tramo.'],
            'antigüedad creciente' => [['antiguedad' => ['tramos' => [['limite' => '6', 'valor' => '40'], ['limite' => '12', 'valor' => '70']]] + $base['antiguedad']], 'antiguedad.tramos', 'Los meses de antigüedad deben ir de mayor a menor.'],
            'endeudamiento con 3 decimales (H-20)' => [['endeudamiento' => ['tramos' => [['limite' => '10,005', 'valor' => '100']]] + $base['endeudamiento']], 'endeudamiento.tramos', 'Complete cada tramo con un límite válido y un valor entero entre 0 y 100.'],
            'categorías no decrecientes' => [['categorias' => ['B' => '80'] + $base['categorias']], 'categorias.B', 'El puntaje mínimo de la categoría B debe ser menor que el de la categoría anterior.'],
            'E distinta de 0' => [['categorias' => ['E' => '5'] + $base['categorias']], 'categorias.E', 'La categoría E debe comenzar en 0 para que todo puntaje tenga categoría.'],
            'resultado inexistente' => [['resultados' => ['C' => 'Aprobado'] + $base['resultados']], 'resultados.C', 'Seleccione el resultado de la categoría C.'],
            'A desfavorable' => [['resultados' => ['A' => 'Desfavorable'] + $base['resultados']], 'resultados', 'Los resultados deben ser coherentes con el riesgo: A no puede ser Desfavorable ni E Favorable.'],
            'situaciones fuera de rango se descartan' => [['situaciones_excluyentes' => ['4', '7', 'x', ['5']]], null, null],
            'vigencia 1' => [['vigencia_dias' => '1'], null, null],
            'vigencia 365' => [['vigencia_dias' => '365'], null, null],
            'vigencia 0' => [['vigencia_dias' => '0'], 'vigencia_dias', 'La vigencia de la evaluación debe estar entre 1 y 365 días.'],
            'vigencia 366' => [['vigencia_dias' => '366'], 'vigencia_dias', 'La vigencia de la evaluación debe estar entre 1 y 365 días.'],
        ];
        foreach ($casos as $caso => [$cambios, $campo, $mensaje]) {
            $antes = $this->ultimaVersion('evaluacion')['numero'];
            $r = $this->enviar('admin/parametros/evaluacion', $cambios + $base);
            if ($campo === null) {
                $this->assertAceptado($r, $caso);
                continue;
            }
            $this->assertCampoRechazado($r, $campo, $mensaje, $caso);
            $this->assertSame($antes, $this->ultimaVersion('evaluacion')['numero'], $caso);
        }
        $v = json_decode((string) $this->ultimaVersion('evaluacion')['valores'], true);
        $this->assertSame(365, $v['vigencia_dias']);
        $this->assertSame(['bcra' => '0.4000', 'comportamiento' => '0.2500', 'endeudamiento' => '0.2500', 'antiguedad' => '0.1000'], $v['pesos'], 'pesos sin redondeo');
    }

    /** IN-31 (CU-59): escalones de la estructuración automática. */
    public function testEscalones(): void
    {
        $this->comoRol('Gerente');
        $formato = 'Ingrese los escalones como porcentajes separados por espacios (por ejemplo, 100 75 50).';
        $primero = 'El primer escalón debe ser 100 % (el monto máximo) y los escalones no pueden repetirse.';
        $orden = 'Los escalones deben ser mayores que 0 % e ir de mayor a menor.';
        foreach (
            [
            '100 75 50' => ['1.0000', '0.7500', '0.5000'],
            '100% ; 75,5%;50' => ['1.0000', '0.7550', '0.5000'],
            '100' => ['1.0000'],
            '100 90 80 70 60 50' => ['1.0000', '0.9000', '0.8000', '0.7000', '0.6000', '0.5000'],
            '' => $formato, 'abc' => $formato, '100 75,123' => $formato, '100 -5' => $formato,
            '90 75' => $primero, '100 75 75' => $primero, '100 100' => $primero,
            '100 50 75' => $orden, '100 0' => $orden,
            '100 90 80 70 60 50 40' => 'Se admiten hasta 6 escalones.',
            ] as $valor => $esperado
        ) {
            $r = $this->enviar('admin/parametros/estructuracion', ['escalones' => (string) $valor] + $this->base('estructuracion'));
            if (is_array($esperado)) {
                $this->assertAceptado($r, "escalones «{$valor}»");
                $this->assertSame($esperado, json_decode((string) $this->ultimaVersion('estructuracion')['valores'], true)['escalones'], "escalones «{$valor}»");
            } else {
                $this->assertCampoRechazado($r, 'escalones', $esperado, "escalones «{$valor}»");
            }
        }
    }

    /** IN-32 (CU-60): reglas entre campos de morosidad. */
    public function testReglasDeMorosidad(): void
    {
        $this->comoRol('Gerente');
        $base = $this->base('morosidad');
        $this->assertCampoRechazado($this->enviar('admin/parametros/morosidad', ['tolerancia_dias' => '10', 'umbral_morosidad_avanzada_dias' => '10'] + $base), 'umbral_morosidad_avanzada_dias', 'El umbral de morosidad avanzada debe ser mayor que la tolerancia (RN-65).');
        $this->assertAceptado($this->enviar('admin/parametros/morosidad', ['tolerancia_dias' => '10', 'umbral_morosidad_avanzada_dias' => '11'] + $base));
        $this->assertCampoRechazado($this->enviar('admin/parametros/morosidad', ['refinanciacion_tasa_minima' => '80'] + $base), 'refinanciacion_tasa_maxima', 'La TNA máxima de refinanciación debe ser mayor que la mínima.');
        $this->assertAceptado($this->enviar('admin/parametros/morosidad', ['refinanciacion_tasa_minima' => '499,99', 'refinanciacion_tasa_maxima' => '500'] + $base), 'mínima 499,99');
        $this->assertCampoRechazado($this->enviar('admin/parametros/morosidad', ['refinanciacion_tasa_minima' => '500,01', 'refinanciacion_tasa_maxima' => '500'] + $base), 'refinanciacion_tasa_minima', 'Ingrese la TNA mínima de refinanciación (entre 0 % y 500 %).');
    }

    /** IN-33 (CU-51): línea, categorías y canales de las campañas. */
    public function testCampanasLineaYListas(): void
    {
        $this->comoRol('Gerente');
        $base = $this->base('campanas');
        $linea = 'Seleccione una línea de crédito activa para las campañas.';
        foreach (['', 'abc', '999999999', ['1'], '-1', '1.5'] as $valor) {
            $this->assertCampoRechazado($this->enviar('admin/parametros/campanas', ['linea_id' => $valor] + $base), 'linea_id', $linea, 'línea ' . json_encode($valor));
        }
        $inactiva = $this->fabrica->linea()['id'];
        $this->db->table('lineas_credito')->where('id', $inactiva)->update(['estado' => 'Suspendida']);
        $this->assertCampoRechazado($this->enviar('admin/parametros/campanas', ['linea_id' => (string) $inactiva] + $base), 'linea_id', $linea, 'línea suspendida');

        $categorias = 'Seleccione al menos una categoría de ingresos admitida.';
        // Un único valor enviado como texto equivale a una casilla marcada
        foreach ([[], ['Inexistente'], '', [['RelacionDependencia']]] as $valor) {
            $this->assertCampoRechazado($this->enviar('admin/parametros/campanas', ['categorias_admitidas' => $valor] + $base), 'categorias_admitidas', $categorias, 'categorías ' . json_encode($valor));
        }
        $this->assertAceptado($this->enviar('admin/parametros/campanas', ['categorias_admitidas' => ['Monotributista', 'Inexistente', 'Monotributista']] + $base));
        $this->assertSame(['Monotributista'], json_decode((string) $this->ultimaVersion('campanas')['valores'], true)['categorias_admitidas']);

        foreach ([[], ['Sms'], '', [['Correo']]] as $valor) {
            $this->assertCampoRechazado($this->enviar('admin/parametros/campanas', ['canales' => $valor] + $base), 'canales', 'Seleccione al menos un canal de comunicación.', 'canales ' . json_encode($valor));
        }
    }

    // ── Apoyo ────────────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> el formulario con los valores sugeridos, desde hoy y con motivo */
    private function base(string $formulario): array
    {
        $datos = match ($formulario) {
            'evaluacion' => V::aFormulario(V::sugeridos()),
            'estructuracion' => E::aFormulario(E::SUGERIDOS),
            'morosidad' => M::aFormulario(M::SUGERIDOS),
            'campanas' => ['linea_id' => (string) $this->fabrica->linea()['id']] + C::aFormulario(C::SUGERIDOS),
        };
        // El navegador envía todo como texto; las casillas, como «1» o vacías
        array_walk_recursive($datos, static function (mixed &$v): void {
            $v = is_bool($v) ? ($v ? '1' : '') : (string) $v;
        });

        return $datos + [
            'fecha_vigencia' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'),
            'motivo' => 'Ajuste de parámetros de prueba', 'confirmar_cambio' => '1',
        ];
    }

    /** @return array{numero: int|string, valores: string} */
    private function ultimaVersion(string $formulario): array
    {
        return $this->db->table('versiones_parametros')->select('numero, valores, motivo, vigente_desde')->where('tipo', self::TIPOS[$formulario])
            ->orderBy('numero', 'DESC')->get(1)->getRowArray() ?? ['numero' => 0, 'valores' => '{}'];
    }

    /** Valor guardado de un campo del formulario, como texto. @param array<string, mixed> $version */
    private function guardado(array $version, string $campo): ?string
    {
        if ($campo === 'motivo') {
            return $version['motivo'];
        }
        if ($campo === 'fecha_vigencia') {
            return Time::parse((string) $version['vigente_desde'])->setTimezone(config('Sigccr')->zonaNegocio)->format('Y-m-d');
        }
        $v = json_decode((string) $version['valores'], true);
        $valor = str_starts_with($campo, 'refinanciacion_') ? $v['refinanciacion'][substr($campo, 15)] : $v[$campo];

        return match (true) {
            is_bool($valor) => $valor ? '1' : '0',
            $valor === null => null,
            default => (string) $valor,
        };
    }

    /** @param array<string, mixed> $datos @return list<list<string>> rutas de cada valor del formulario */
    private static function hojas(array $datos, array $prefijo = []): array
    {
        $r = [];
        foreach ($datos as $k => $v) {
            if (in_array($k, ['confirmar_cambio'], true)) {
                continue;
            }
            $ruta = [...$prefijo, (string) $k];
            $grupo = is_array($v) && $v !== [] && (!array_is_list($v) || is_array(reset($v)));
            if ($grupo) {
                $r[] = $ruta;
                array_push($r, ...self::hojas($v, $ruta));
            } else {
                $r[] = $ruta;
            }
        }

        return $r;
    }

    /** @param array<string, mixed> $datos @param list<string> $ruta */
    private static function reemplazar(array $datos, array $ruta, mixed $valor): array
    {
        $ref = &$datos;
        foreach ($ruta as $i => $k) {
            if ($i === count($ruta) - 1) {
                $ref[$k] = $valor;
                break;
            }
            $ref = &$ref[$k];
        }

        return $datos;
    }
}
