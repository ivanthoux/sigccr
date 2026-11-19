<?php

declare(strict_types=1);

namespace Tests\Feature\Solicitudes;

use App\Libraries\Solicitudes\ModeloContrato;
use App\Services\Solicitudes\ContratoService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-71 · Modelo de contrato configurable (CU-106, D-56; RN-15, RN-78). El Gerente registra una versión de las
 * cláusulas; los contratos que se generan desde entonces la usan y los anteriores conservan la suya. Plan de pruebas
 * 7.9.2 y Anexo B.19.
 *
 * Las clases de datos se prueban en la cláusula 9 del modelo inicial («Jurisdicción», numerada y sin variables); cada
 * versión aceptada se reemplaza al terminar por el modelo inicial.
 *
 * @internal
 */
final class ModeloContratoTest extends IntegracionTestCase
{
    private const SEGURO = 'El cliente puede contratar un seguro de vida sobre saldo deudor con la aseguradora que elija ({linea}).';

    /** @param list<array{estilo: string, titulo: string, texto: string}> $clausulas */
    private static function formulario(array $clausulas, array $extra = []): array
    {
        return $extra + [
            'estilo' => array_column($clausulas, 'estilo'), 'titulo' => array_column($clausulas, 'titulo'), 'texto' => array_column($clausulas, 'texto'),
            'fecha_vigencia' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'), 'motivo' => 'Cláusula de seguro de vida sobre saldo deudor pedida por la entidad.',
        ];
    }

    /** El modelo inicial con una cláusula de seguro agregada antes de la jurisdicción. */
    private static function conSeguro(): array
    {
        $c = ModeloContrato::INICIAL;
        array_splice($c, 8, 0, [['estilo' => 'numerada', 'titulo' => 'Seguro', 'texto' => self::SEGURO]]);

        return $c;
    }

    public function testRechazos(): void
    {
        $this->comoRol('Gerente');
        $sinCft = array_values(array_filter(ModeloContrato::INICIAL, static fn ($c) => !str_contains($c['texto'], '{cuadro_cft}')));
        $this->assertCampoRechazado($this->enviar('admin/parametros/contrato', self::formulario($sinCft)), 'clausulas', 'Falta {cuadro_cft}: el costo financiero total debe informarse destacado (art. 36 de la Ley 24.240).');
        $desconocida = self::formulario([...ModeloContrato::INICIAL, ['estilo' => 'numerada', 'titulo' => 'Extra', 'texto' => 'Monto {monto}']]);
        $this->assertCampoRechazado($this->enviar('admin/parametros/contrato', $desconocida), 'clausula_12_texto', 'Variable desconocida: {monto}. Use las de la lista.');
        $this->assertCampoRechazado($this->enviar('admin/parametros/contrato', self::formulario(ModeloContrato::INICIAL, ['motivo' => 'abc'])), 'motivo', null);
        // Un campo enviado como texto en lugar de lista no responde 500: se rechaza sin registrar (H-15)
        $this->assertCampoRechazado($this->enviar('admin/parametros/contrato', ['texto' => 'una sola cláusula'] + self::formulario([])), 'clausulas', 'El contrato debe tener al menos una cláusula.');
        $this->enviar('admin/parametros/contrato', self::formulario(ModeloContrato::INICIAL, ['fecha_vigencia' => Time::now(config('Sigccr')->zonaNegocio)->subDays(1)->format('d/m/Y')]));
        $this->assertSame('La nueva configuración no puede regir desde una fecha anterior a hoy: no se admite retroactividad.', $_SESSION['error'] ?? null);
        // El Analista no configura el modelo
        $this->comoRol('AnalistaCredito');
        $this->assertSame(403, $this->enviar('admin/parametros/contrato', self::formulario(self::conSeguro()))->response()->getStatusCode());
    }

    public function testNuevaVersionRigeParaLosContratosNuevos(): void
    {
        $servicio = new ContratoService($this->db);
        $anterior = $this->fabrica->solicitud('AprobadaPendienteLiquidacion');
        $versionAnterior = $servicio->obtener($anterior['id'])['contrato']['version_contrato_id'];

        $this->comoRol('Gerente');
        $this->assertAceptado($this->enviar('admin/parametros/contrato', self::formulario(self::conSeguro())));
        $vigente = $servicio->modeloVigente();
        $this->assertNotNull($vigente['id']);
        $this->assertSame(self::conSeguro(), $vigente['clausulas']);

        // D-48: la auditoría guarda cada cláusula que cambió
        $detalle = json_decode((string) $this->db->table('registros_auditoria')->select('detalle')->where('accion', 'parametros.contrato')->orderBy('id', 'DESC')->get(1)->getRow()->detalle, true);
        $this->assertSame(self::SEGURO, $detalle['cambios']['clausulas.9.texto']['nuevo'] ?? null);

        // El contrato nuevo usa la versión vigente y numera la cláusula agregada
        $nueva = $this->fabrica->solicitud('AprobadaPendienteLiquidacion');
        $k = $servicio->obtener($nueva['id']);
        $this->assertSame($vigente['id'], (int) $k['contrato']['version_contrato_id']);
        $seguro = array_values(array_filter($k['clausulas'], static fn ($c) => $c['titulo'] === 'Seguro'))[0];
        $this->assertSame(8, $seguro['numero']);
        $this->assertStringContainsString('aseguradora que elija (' . esc($k['linea']) . ').', $seguro['parrafos'][0]['html']);
        $this->assertSame(9, array_column($k['clausulas'], 'numero', 'titulo')['Jurisdicción']);

        // El contrato generado antes conserva su modelo (RN-15)
        $viejo = $servicio->obtener($anterior['id']);
        $this->assertSame($versionAnterior, $viejo['contrato']['version_contrato_id']);
        $this->assertNotContains('Seguro', array_column($viejo['clausulas'], 'titulo'));

        // La pantalla del contrato muestra la cláusula nueva
        $this->comoRol('AnalistaCredito');
        $html = (string) $this->pedir("admin/contratos/{$nueva['id']}")->response()->getBody();
        $this->assertStringContainsString('<strong>8. Seguro.</strong>', $html);
        $this->assertStringContainsString('9. Jurisdicción.', $html);

        // Se restituye el modelo inicial para no afectar a las demás pruebas
        $this->comoRol('Gerente');
        $this->assertAceptado($this->enviar('admin/parametros/contrato', self::formulario(ModeloContrato::INICIAL, ['motivo' => 'Se vuelve al modelo inicial al terminar la prueba.'])));
    }

    // ── Clases de datos por campo ────────────────────────────────────────────────────────────────────────────────

    private const FILA = 8;

    /** @return array<string, array<string, mixed>> campos de una cláusula y del registro (Anexo B.19) */
    public static function camposDelModelo(): array
    {
        return [
            'estilo' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => array_keys(ModeloContrato::ESTILOS),
                'mensajes' => ['obligatorio' => 'Elija la forma de la cláusula.', 'formato' => 'Elija la forma de la cláusula.'],
            ],
            'titulo' => [
                'tipo' => 'TXT', 'obligatorio' => true, 'max' => ModeloContrato::MAX_TITULO,
                'mensajes' => ['obligatorio' => 'Una cláusula numerada lleva título.', 'largo' => 'El título admite hasta 100 caracteres.'],
            ],
            'texto' => [
                'tipo' => 'TXT', 'obligatorio' => true, 'max' => ModeloContrato::MAX_TEXTO,
                'mensajes' => ['obligatorio' => 'Escriba el texto de la cláusula.', 'largo' => 'El texto admite hasta 5.000 caracteres.'],
            ],
            'motivo' => ['tipo' => 'MOT', 'obligatorio' => true, 'max' => 500],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosDelModelo(): array
    {
        return self::casosDelCatalogo('IN-71', self::camposDelModelo());
    }

    /**
     * IN-71: cada campo de la cláusula 9 y el motivo con cada clase de dato. Aceptado, la versión vigente guarda el
     * valor; rechazado, no se registra ninguna versión.
     *
     * @dataProvider casosDelModelo
     * @param array<string, mixed> $otros
     */
    public function testModeloConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Gerente');
        $datos = self::formulario(ModeloContrato::INICIAL);
        if ($campo === 'motivo') {
            $datos = self::conValor($datos, 'motivo', $valor, $otros);
            $clave = 'motivo';
        } else {
            $clave = 'clausula_' . (self::FILA + 1) . "_{$campo}";
            if ($valor === Esperado::AUSENTE) {
                // H-32: sin un elemento de una de las listas paralelas, las demás se correrían
                unset($datos[$campo][self::FILA]);
                $clave = 'clausulas';
                $esperado = Esperado::rechaza('Las cláusulas llegaron incompletas: vuelva a cargar la página e intente de nuevo.');
            } else {
                $datos[$campo][self::FILA] = $valor;
            }
        }
        $antes = $this->ultimoId('versiones_parametros');
        $r = $this->enviar('admin/parametros/contrato', $datos);

        if (!$esperado->acepta) {
            $this->assertCampoRechazado($r, $clave, $esperado->mensaje, "{$campo}: {$esperado}");
            $this->assertSame($antes, $this->ultimoId('versiones_parametros'), 'Un rechazo no registra la versión');

            return;
        }
        $this->assertAceptado($r, "{$campo}: {$esperado}");
        $vigente = (new ContratoService($this->db))->modeloVigente();
        if ($campo === 'motivo') {
            $this->assertValorGuardado($esperado->guardado, $this->db->table('versiones_parametros')->where('id', $vigente['id'])->get()->getRow()->motivo, 'motivo');
        } else {
            $this->assertValorGuardado($esperado->guardado, $vigente['clausulas'][self::FILA][$campo], $campo);
        }
        (new ContratoService($this->db))->registrarModelo(ModeloContrato::INICIAL, Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d'), 'Se vuelve al modelo inicial.', $this->fabrica->responsable());
    }

    /** IN-71: la fecha de vigencia y los controles del modelo completo (A1 a A3). */
    public function testFechaYModeloCompleto(): void
    {
        $this->comoRol('Gerente');
        $antes = $this->ultimoId('versiones_parametros');
        foreach (['vacía' => '', 'inválida' => '31/02/2026', 'texto' => 'mañana', 'como lista' => ['01/01/2030']] as $caso => $fecha) {
            $r = $this->enviar('admin/parametros/contrato', self::formulario(ModeloContrato::INICIAL, ['fecha_vigencia' => $fecha]));
            $this->assertCampoRechazado($r, 'fecha_vigencia', 'Ingrese la fecha desde la que rige el nuevo modelo.', $caso);
        }
        $sin = static fn (string $variable): array => array_map(static fn ($c) => ['texto' => str_replace('{' . $variable . '}', '', $c['texto'])] + $c, ModeloContrato::INICIAL);
        $doble = ModeloContrato::INICIAL;
        $doble[8]['texto'] = "{plan_cuotas}\n\nOtra vez.";
        $pegado = ModeloContrato::INICIAL;
        $pegado[8]['texto'] = 'Texto {cuadro_cft} pegado.';
        $llave = ModeloContrato::INICIAL;
        $llave[8]['texto'] = 'Una llave { sin cerrar.';
        $muchas = array_merge(ModeloContrato::INICIAL, array_fill(0, ModeloContrato::MAX_CLAUSULAS, ['estilo' => 'sin_numero', 'titulo' => '', 'texto' => 'Párrafo.']));
        $casos = [
            'sin el plan de cuotas' => [$sin('plan_cuotas'), 'clausulas', 'Falta {plan_cuotas}: el contrato debe incluir el plan de pago (art. 36 de la Ley 24.240).'],
            'plan de cuotas dos veces' => [$doble, 'clausulas', '{plan_cuotas} va una sola vez: el contrato debe incluir el plan de pago (art. 36 de la Ley 24.240).'],
            'sin el plazo de revocación' => [$sin('plazo_revocacion'), 'clausulas', 'Falta la cláusula del derecho de revocación con {plazo_revocacion}: el derecho es irrenunciable (art. 1110 del Código Civil y Comercial, RN-79).'],
            'bloque dentro de un párrafo' => [$pegado, 'clausula_9_texto', '{cuadro_cft} va solo en un párrafo: deje una línea en blanco antes y después.'],
            'llave sin cerrar' => [$llave, 'clausula_9_texto', 'Hay una llave sin cerrar: las variables se escriben entre { y }.'],
            'más de 40 cláusulas' => [$muchas, 'clausulas', 'El contrato admite hasta 40 cláusulas.'],
        ];
        foreach ($casos as $caso => [$clausulas, $clave, $mensaje]) {
            $this->assertCampoRechazado($this->enviar('admin/parametros/contrato', self::formulario($clausulas)), $clave, $mensaje, $caso);
        }
        // H-32: listas paralelas de distinto largo se rechazan completas
        foreach (['estilo', 'titulo', 'texto'] as $lista) {
            $corto = self::formulario(ModeloContrato::INICIAL);
            array_splice($corto[$lista], 4, 1);
            $this->assertCampoRechazado($this->enviar('admin/parametros/contrato', $corto), 'clausulas', 'Las cláusulas llegaron incompletas: vuelva a cargar la página e intente de nuevo.', $lista);
        }
        $this->assertSame($antes, $this->ultimoId('versiones_parametros'));

        // Las filas vacías se descartan, el texto se guarda sin las líneas vacías de más y el HTML se muestra como texto
        $html = ModeloContrato::INICIAL;
        $html[8]['texto'] = "Primer párrafo <script>alert(1)</script>.\r\n\r\n\r\n\r\nSegundo párrafo.";
        $datos = self::formulario($html);
        $datos['estilo'][] = 'numerada';
        $datos['titulo'][] = '  ';
        $datos['texto'][] = '';
        $this->assertAceptado($this->enviar('admin/parametros/contrato', $datos));
        $vigente = (new ContratoService($this->db))->modeloVigente();
        $this->assertCount(count(ModeloContrato::INICIAL), $vigente['clausulas']);
        $this->assertSame("Primer párrafo <script>alert(1)</script>.\n\nSegundo párrafo.", $vigente['clausulas'][8]['texto']);
        $solicitud = $this->fabrica->solicitud('AprobadaPendienteLiquidacion');
        $this->comoRol('AnalistaCredito');
        $pagina = (string) $this->pedir("admin/contratos/{$solicitud['id']}")->response()->getBody();
        $this->assertStringContainsString('Primer párrafo &lt;script&gt;alert(1)&lt;/script&gt;.', $pagina);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $pagina);
        $this->comoRol('Gerente');
        $this->assertAceptado($this->enviar('admin/parametros/contrato', self::formulario(ModeloContrato::INICIAL, ['motivo' => 'Se vuelve al modelo inicial al terminar la prueba.'])));
    }
}
