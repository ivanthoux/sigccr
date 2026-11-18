<?php

declare(strict_types=1);

namespace Tests\Feature\Cobranzas;

use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-45 a IN-54 · Cobranzas, cartera y saldos (iteración 3): cobro en ventanilla (CU-33), anulación (CU-37), cancelación
 * anticipada (CU-97), orden de pago electrónico (CU-34), revocación (CU-102), condonación (CU-74), refinanciación
 * (CU-39 a CU-44), aplicación de saldo a favor (CU-76) y devolución (CU-77, CU-78). Plan de pruebas 7.9.2 y Anexo B.14.
 *
 * Cada prueba parte de un crédito liquidado por el camino de CU-21 a CU-27 (Fabrica::credito). Los rechazos se
 * envían sobre el mismo crédito y no deben dejar ningún registro.
 *
 * @internal
 */
final class CobranzasTest extends IntegracionTestCase
{
    private static function hoy(int $dias = 0): string
    {
        return Time::now(config('Sigccr')->zonaNegocio)->addDays($dias)->format('d/m/Y');
    }

    private static function operacion(): string
    {
        return 'OP-' . strtoupper(Fabrica::sufijo());
    }

    /** Total exigible de la cuota (capital, interés, gastos, punitorios e IVA). */
    private function totalCuota(array $cuota): string
    {
        return \App\Libraries\Caja\CobroCuotas::total($cuota)->toFixed(2);
    }

    // ── IN-45 Registrar cobro (CU-33) ────────────────────────────────────────────────────────────────────────────

    /** @return array{credito: array<string, mixed>, base: array<string, mixed>, total: string} */
    private function paraCobrar(): array
    {
        $cajero = $this->comoRol('Cajero');
        $this->fabrica->cajaAbierta($cajero['id']);
        $credito = $this->fabrica->credito();
        $total = $this->totalCuota($credito['cuotas'][0]);

        return ['credito' => $credito, 'total' => $total, 'base' => [
            'cliente_id' => (string) $credito['cliente']['id'], 'cuotas' => [$credito['id'] => ['1']], 'importe' => '',
            'medios' => [['medio' => 'Transferencia', 'importe' => str_replace('.', ',', $total), 'numero_operacion' => self::operacion()]],
        ]];
    }

    public function testCobroRechazos(): void
    {
        $c = $this->paraCobrar();
        $credito = $c['credito']['id'];
        $total = $c['total'];
        $medio = static fn (array $m): array => ['medios' => [$m + ['medio' => 'Transferencia', 'importe' => str_replace('.', ',', $total), 'numero_operacion' => 'OP-1']]];
        $otro = $this->fabrica->credito();

        $this->rechazos('admin/cobros', $c['base'], [
            'sin cuotas' => [['cuotas' => []], 'seleccion', 'Seleccione al menos una cuota.'],
            'cuotas ausentes' => [['cuotas' => Esperado::AUSENTE], 'seleccion', 'Seleccione al menos una cuota.'],
            'cuotas como texto' => [['cuotas' => '1'], 'seleccion', null],
            'cuota 2 sin la 1 (RN-29)' => [['cuotas' => [$credito => ['2']]], 'seleccion', "Crédito *"],
            'cuota inexistente' => [['cuotas' => [$credito => ['999']]], 'seleccion', null],
            'cuota como lista anidada' => [['cuotas' => [$credito => [['1']]]], 'seleccion', null],
            'crédito de otro cliente' => [['cuotas' => [$otro['id'] => ['1']]], 'seleccion', null],
            'cliente como lista (H-21)' => [['cliente_id' => [(string) $c['credito']['cliente']['id']]], 'seleccion', null],
            'importe cero' => [['importe' => '0'], 'importe', 'Ingrese un importe a cobrar mayor que cero.'],
            'importe negativo' => [['importe' => '-1'], 'importe', 'Ingrese un importe a cobrar mayor que cero.'],
            'importe con letras' => [['importe' => 'abc'], 'importe', 'Ingrese un importe a cobrar mayor que cero.'],
            'importe mayor que el total' => [['importe' => str_replace('.', ',', bcadd($total, '0.01', 2))], 'importe', 'El importe a cobrar no puede superar el total de las cuotas seleccionadas *'],
            'sin medios' => [['medios' => []], 'medios', 'Indique al menos un medio de pago.'],
            'medios como texto' => [['medios' => 'Transferencia'], 'medios', 'Indique al menos un medio de pago.'],
            'efectivo (RN-77)' => [$medio(['medio' => 'Efectivo']), 'medios', 'Medio 1: no se reciben pagos en efectivo; el cliente paga por transferencia, depósito o tarjeta (RN-77).'],
            'medio inexistente' => [$medio(['medio' => 'Cheque']), 'medios', 'Medio 1: seleccione un medio de pago válido.'],
            'medio en minúsculas' => [$medio(['medio' => 'transferencia']), 'medios', 'Medio 1: seleccione un medio de pago válido.'],
            'medio como lista (H-15)' => [$medio(['medio' => ['Transferencia']]), 'medios', 'Medio 1: seleccione un medio de pago válido.'],
            'importe del medio cero' => [$medio(['importe' => '0']), 'medios', 'Medio 1: el importe debe ser mayor que cero.'],
            'importe del medio como lista (H-15)' => [$medio(['importe' => [$total]]), 'medios', null],
            'sin número de operación' => [$medio(['numero_operacion' => '']), 'medios', 'Medio 1: informe el número de operación o comprobante de *'],
            'número de operación como lista (H-15)' => [$medio(['numero_operacion' => ['OP-1']]), 'medios', 'Medio 1: informe el número de operación o comprobante de *'],
            'número de operación de 61 (VARCHAR(60))' => [$medio(['numero_operacion' => str_repeat('9', 61)]), 'medios', 'Medio 1: el número de operación admite hasta 60 letras, números o signos, sin tildes.'],
            'número de operación con eñe (VARCHAR)' => [$medio(['numero_operacion' => 'OP-Ñ-1']), 'medios', 'Medio 1: el número de operación admite hasta 60 letras, números o signos, sin tildes.'],
            'operación repetida' => [['medios' => [
                ['medio' => 'Transferencia', 'importe' => '1,00', 'numero_operacion' => 'OP-R'],
                ['medio' => 'Transferencia', 'importe' => str_replace('.', ',', bcsub($total, '1', 2)), 'numero_operacion' => 'OP-R'],
            ]], 'medios', 'Medio 2: el número de operación OP-R está repetido.'],
            'medios que no suman' => [$medio(['importe' => '1,00']), 'medios', 'Los medios suman $ 1.00: faltan $ *'],
            'cliente sin crédito' => [['cliente_id' => '999999999'], 'seleccion', null],
        ], ['cobros', 'cobros_medios', 'imputaciones']);
    }

    public function testCobroAceptado(): void
    {
        $c = $this->paraCobrar();
        $operacion = str_repeat('A', 57) . '-/9';
        $datos = ['medios' => [
            ['medio' => ' TarjetaDebito ', 'importe' => '1,00', 'numero_operacion' => "  {$operacion}  "],
            ['medio' => 'Transferencia', 'importe' => str_replace('.', ',', bcsub($c['total'], '1', 2)), 'numero_operacion' => 'OP-2'],
            ['medio' => '', 'importe' => '', 'numero_operacion' => ''],
        ]] + $c['base'];

        $this->assertAceptado($this->enviar('admin/cobros', $datos));
        $cobro = $this->ultimoId('cobros');
        $medios = $this->db->table('cobros_medios')->where('cobro_id', $cobro)->orderBy('id')->get()->getResultArray();
        $this->assertSame(['TarjetaDebito', 'Transferencia'], array_column($medios, 'medio'), 'SEL-04 y fila vacía descartada');
        $this->assertSame($operacion, $medios[0]['numero_operacion'], '60 caracteres guardados completos');
        $this->assertSame('Pagada', $this->fabrica->cuotas($c['credito']['id'])[0]['estado']);

        // Pago parcial de la cuota 2 (D-12)
        $parcial = ['cuotas' => [$c['credito']['id'] => ['2']], 'importe' => '1.000,00', 'medios' => [['medio' => 'Transferencia', 'importe' => '1.000,00', 'numero_operacion' => self::operacion()]]] + $c['base'];
        $this->assertAceptado($this->enviar('admin/cobros', $parcial), 'pago parcial');
        $this->assertNotSame('Pagada', $this->fabrica->cuotas($c['credito']['id'])[1]['estado']);
    }

    // ── IN-46 Anular cobro (CU-37) ───────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: mixed, 1: Esperado, 2: array<string, mixed>}> */
    public static function casosAnulacion(): array
    {
        $r = [];
        foreach (self::casosDelCatalogo('IN-46', ['motivo' => ['tipo' => 'MOT', 'obligatorio' => true, 'max' => 500]]) as $nombre => [, $valor, $esperado, $otros]) {
            $r[$nombre] = [$valor, $esperado, $otros];
        }

        return $r;
    }

    /**
     * IN-46: motivo de la anulación con cada clase de dato (hasta 500 caracteres, RN-33).
     *
     * @dataProvider casosAnulacion
     * @param array<string, mixed> $otros
     */
    public function testAnularConCadaClaseDeMotivo(mixed $valor, Esperado $esperado, array $otros): void
    {
        $c = $this->paraCobrar();
        $this->assertAceptado($this->enviar('admin/cobros', $c['base']));
        $cobro = $this->ultimoId('cobros');
        $this->comoRol('Gerente');

        $r = $this->enviar("admin/cobros/{$cobro}/anular", self::conValor([], 'motivo', $valor, $otros));

        $estado = $this->db->table('cobros')->where('id', $cobro)->get()->getRow()->estado;
        if ($esperado->acepta) {
            $this->assertAceptado($r, "motivo: {$esperado}");
            $this->assertSame('Anulado', $estado);
            $this->assertNotSame('Pagada', $this->fabrica->cuotas($c['credito']['id'])[0]['estado'], 'la cuota vuelve a quedar impaga');

            return;
        }
        $this->assertCampoRechazado($r, 'motivo', $esperado->mensaje, "motivo: {$esperado}");
        $this->assertSame('Confirmado', $estado);
    }

    // ── IN-47 Cancelación anticipada (CU-97) ─────────────────────────────────────────────────────────────────────

    public function testCancelacionAnticipada(): void
    {
        $cajero = $this->comoRol('Cajero');
        $this->fabrica->cajaAbierta($cajero['id']);
        $credito = $this->fabrica->credito();
        $total = (new \App\Services\Caja\CancelacionAnticipadaService($this->db))->calcular($credito['id'])['calculo']['total']->toFixed(2);
        $base = ['importe' => str_replace('.', ',', $total), 'medio' => 'Transferencia', 'numero_operacion' => self::operacion()];
        $parcial = 'El importe debe ser igual al total de la cancelación: *';

        $this->rechazos("admin/creditos/{$credito['id']}/cancelacion-anticipada", $base, [
            'importe vacío' => [['importe' => ''], 'importe', $parcial],
            'importe parcial (RN-67)' => [['importe' => '1,00'], 'importe', $parcial],
            'importe un centavo más' => [['importe' => str_replace('.', ',', bcadd($total, '0.01', 2))], 'importe', $parcial],
            'importe como lista' => [['importe' => [$total]], 'importe', $parcial],
            'efectivo' => [['medio' => 'Efectivo'], 'medios', 'Medio 1: no se reciben pagos en efectivo; *'],
            'medio vacío' => [['medio' => ''], 'medios', 'Medio 1: seleccione un medio de pago válido.'],
            'medio como lista' => [['medio' => ['Transferencia']], 'medios', 'Medio 1: seleccione un medio de pago válido.'],
            'sin operación' => [['numero_operacion' => ''], 'medios', 'Medio 1: informe el número de operación *'],
            'operación de 61 (VARCHAR(60))' => [['numero_operacion' => str_repeat('9', 61)], 'medios', 'Medio 1: el número de operación admite hasta 60 letras, números o signos, sin tildes.'],
        ], ['cobros', 'cobros_medios']);
        $this->enviar('admin/creditos/999999999/cancelacion-anticipada', $base);
        $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), 'crédito inexistente');

        $this->assertAceptado($this->enviar("admin/creditos/{$credito['id']}/cancelacion-anticipada", ['medio' => ' Transferencia '] + $base));
        $this->assertSame('Cancelado', $this->db->table('creditos')->where('id', $credito['id'])->get()->getRow()->estado);
    }

    // ── IN-48 Orden de pago electrónico (CU-34) ──────────────────────────────────────────────────────────────────

    public function testOrdenDePagoElectronico(): void
    {
        $this->comoRol('Cajero');
        $credito = $this->fabrica->credito();
        $base = ['cliente_id' => (string) $credito['cliente']['id'], 'cuotas' => [$credito['id'] => ['1']]];

        $this->rechazos('admin/pagos-electronicos/nueva', $base, [
            'sin cuotas' => [['cuotas' => []], 'seleccion', 'Seleccione al menos una cuota.'],
            'cuotas como texto' => [['cuotas' => 'x'], 'seleccion', null],
            'cuota salteada' => [['cuotas' => [$credito['id'] => ['3']]], 'seleccion', 'Crédito *'],
            'cliente como lista (H-21)' => [['cliente_id' => [$base['cliente_id']]], 'seleccion', null],
        ], ['ordenes_pago_electronico']);

        $this->assertAceptado($this->enviar('admin/pagos-electronicos/nueva', $base));
        $orden = $this->ultimoId('ordenes_pago_electronico');
        $this->enviar('admin/pagos-electronicos/nueva', $base);
        $this->assertStringContainsString('ya integra la orden', (string) ($_SESSION['error'] ?? ''), 'A7: una cuota no integra dos órdenes');
        $this->assertSame($orden, $this->ultimoId('ordenes_pago_electronico'));
    }

    // ── IN-49 Revocación (CU-102) ────────────────────────────────────────────────────────────────────────────────

    public function testRevocacion(): void
    {
        $cajero = $this->comoRol('Cajero');
        $credito = $this->fabrica->credito();
        $base = ['fecha_comunicacion' => self::hoy(), 'medio_comunicacion' => 'Nota presentada en la sucursal'];

        $this->rechazos("admin/revocaciones/{$credito['id']}", $base, [
            'fecha vacía' => [['fecha_comunicacion' => ''], 'fecha_comunicacion', null],
            'fecha inválida' => [['fecha_comunicacion' => '30/02/2026'], 'fecha_comunicacion', null],
            'fecha futura' => [['fecha_comunicacion' => self::hoy(1)], 'fecha_comunicacion', null],
            'fecha anterior al desembolso' => [['fecha_comunicacion' => self::hoy(-1)], 'fecha_comunicacion', 'La comunicación no puede ser anterior al desembolso *'],
            'fecha como lista' => [['fecha_comunicacion' => [self::hoy()]], 'fecha_comunicacion', null],
            'medio de 2' => [['medio_comunicacion' => 'No'], 'medio_comunicacion', null],
            'medio de 61' => [['medio_comunicacion' => str_repeat('m', 61)], 'medio_comunicacion', null],
            'medio de 59 y un emoji (H-16)' => [['medio_comunicacion' => str_repeat('m', 59) . '😀'], 'medio_comunicacion', null],
            'medio como lista' => [['medio_comunicacion' => ['Nota']], 'medio_comunicacion', null],
        ], ['revocaciones']);

        $this->assertAceptado($this->enviar("admin/revocaciones/{$credito['id']}", ['medio_comunicacion' => str_repeat('m', 58) . '😀'] + $base));
        $this->enviar("admin/revocaciones/{$credito['id']}", $base);
        $this->assertSame('El crédito ya tiene una revocación registrada.', $_SESSION['error'] ?? null);

        // Restitución: operación e importe
        $this->fabrica->cajaAbierta($cajero['id']);
        $restituir = (string) $this->db->table('revocaciones')->where('credito_id', $credito['id'])->get()->getRow()->importe_restituir;
        $conf = ['numero_operacion' => self::operacion(), 'importe' => str_replace('.', ',', $restituir)];
        $this->rechazos("admin/revocaciones/{$credito['id']}/confirmar", $conf, [
            'operación vacía' => [['numero_operacion' => ''], 'numero_operacion', null],
            'operación de 61' => [['numero_operacion' => str_repeat('9', 61)], 'numero_operacion', null],
            'operación con eñe (VARCHAR)' => [['numero_operacion' => 'OP-Ñ'], 'numero_operacion', null],
            'importe distinto' => [['importe' => '1,00'], 'importe', 'El importe a restituir es $ *'],
            'importe vacío' => [['importe' => ''], 'importe', null],
            'importe como lista' => [['importe' => [$restituir]], 'importe', null],
        ], ['cobros']);
        $this->assertAceptado($this->enviar("admin/revocaciones/{$credito['id']}/confirmar", $conf));
    }

    // ── IN-50 Condonación de punitorios (CU-74) ──────────────────────────────────────────────────────────────────

    public function testCondonacion(): void
    {
        $this->comoRol('Gerente');
        $credito = $this->fabrica->credito();
        $cuota = (string) $credito['cuotas'][0]['id'];
        $base = ['cliente_id' => (string) $credito['cliente']['id'], 'cuotas' => [$cuota], 'importe' => '100,00', 'fundamento' => 'Situación de salud del titular'];

        $this->rechazos('admin/punitorios/condonar', $base, [
            'sin cuotas' => [['cuotas' => []], 'cuotas', 'Seleccione al menos una cuota con punitorios pendientes.'],
            'cuotas como texto' => [['cuotas' => 'x'], 'cuotas', 'Seleccione al menos una cuota con punitorios pendientes.'],
            'cuota como lista anidada' => [['cuotas' => [[$cuota]]], 'cuotas', null],
            'importe vacío' => [['importe' => ''], 'importe', 'Ingrese el importe a condonar, mayor que cero.'],
            'importe cero' => [['importe' => '0'], 'importe', 'Ingrese el importe a condonar, mayor que cero.'],
            'importe como lista' => [['importe' => ['100']], 'importe', 'Ingrese el importe a condonar, mayor que cero.'],
            'fundamento de 4' => [['fundamento' => 'Baja'], 'fundamento', 'Indique el fundamento de la condonación (al menos 5 caracteres, A2).'],
            'fundamento de 1001' => [['fundamento' => str_repeat('f', 1001)], 'fundamento', null],
            'fundamento de 999 y un emoji' => [['fundamento' => str_repeat('f', 999) . '😀'], 'fundamento', null],
            'sin punitorios: supera el máximo' => [[], 'importe', 'El importe supera el máximo condonable: *'],
            'cuota de otro cliente' => [['cuotas' => [(string) $this->fabrica->credito()['cuotas'][0]['id']]], '', 'Alguna cuota seleccionada no es del cliente *'],
            'cuota inexistente' => [['cuotas' => ['999999999']], '', 'Alguna cuota seleccionada no existe.'],
        ], ['condonaciones']);
    }

    // ── IN-51 Refinanciación (CU-39 a CU-44) ─────────────────────────────────────────────────────────────────────

    /**
     * Crédito en mora: la cuota 1 vencida hace 40 días. Los punitorios se calculan día por día con los parámetros
     * vigentes en cada día (CU-88): como en los datos de demostración, la carga inicial de parámetros se lleva dos
     * años atrás para que rija en esos días.
     */
    private function creditoEnMora(): array
    {
        $this->db->query("UPDATE dbo.versiones_parametros SET vigente_desde = DATEADD(year, -2, vigente_desde) WHERE numero = 1 AND vigente_desde > DATEADD(year, -1, SYSDATETIMEOFFSET())");
        $credito = $this->fabrica->credito();
        $this->db->table('cuotas')->where('id', $credito['cuotas'][0]['id'])->update(['vencimiento' => Time::now(config('Sigccr')->zonaNegocio)->subDays(40)->format('Y-m-d'), 'estado' => 'Vencida']);
        $this->db->table('creditos')->where('id', $credito['id'])->update(['estado' => 'EnMora']);

        return $credito;
    }

    public function testRefinanciacion(): void
    {
        $this->comoRol('AnalistaCredito');
        $credito = $this->creditoEnMora();
        $base = ['cliente_id' => (string) $credito['cliente']['id'], 'creditos' => [(string) $credito['id']], 'tasa' => '60', 'cuotas' => '12'];
        $creditos = 'Seleccione uno o más créditos en mora del cliente (RN-70).';

        $this->rechazos('admin/refinanciaciones/simular', $base, [
            'sin créditos' => [['creditos' => []], 'creditos', $creditos],
            // Un único crédito enviado como texto equivale a una casilla marcada (como las categorías de una línea)
            'crédito como lista anidada' => [['creditos' => [[(string) $credito['id']]]], 'creditos', $creditos],
            'crédito al día' => [['creditos' => [(string) $this->fabrica->credito()['id']]], 'creditos', $creditos],
            'cliente como lista (H-21)' => [['cliente_id' => [$base['cliente_id']]], 'creditos', $creditos],
            'tasa vacía' => [['tasa' => ''], 'tasa', null],
            'tasa con letras' => [['tasa' => 'sesenta'], 'tasa', null],
            'tasa como lista' => [['tasa' => ['60']], 'tasa', null],
            'tasa de 1000' => [['tasa' => '1000'], 'tasa', null],
            'cuotas vacías' => [['cuotas' => ''], 'cuotas', null],
            'cuotas cero' => [['cuotas' => '0'], 'cuotas', null],
            'cuotas 121' => [['cuotas' => '121'], 'cuotas', null],
            'cuotas como lista' => [['cuotas' => ['12']], 'cuotas', null],
        ], ['simulaciones_refinanciacion']);

        $this->assertAceptado($this->enviar('admin/refinanciaciones/simular', ['tasa' => ' 60,5 % ', 'cuotas' => ' 12 '] + $base), 'simulación');
        $simulacion = $this->ultimoId('simulaciones_refinanciacion');
        $this->assertAceptado($this->enviar("admin/refinanciaciones/simulaciones/{$simulacion}/propuesta"), 'propuesta');
        $propuesta = $this->ultimoId('propuestas_refinanciacion');
        $version = fn (): string => (string) $this->db->table('propuestas_refinanciacion')->where('id', $propuesta)->get()->getRow()->version_fila;

        // CU-42 Resolver (Gerente)
        $this->comoRol('Gerente');
        $decision = 'Seleccione Aprobar, Rechazar u Observar.';
        $this->rechazos("admin/refinanciaciones/{$propuesta}/resolver", ['version_fila' => $version(), 'decision' => 'Aprobada', 'fundamento' => 'Capacidad de pago verificada'], [
            'decisión vacía' => [['decision' => ''], 'decision', $decision],
            'decisión en minúsculas' => [['decision' => 'aprobada'], 'decision', $decision],
            'decisión como lista' => [['decision' => ['Aprobada']], 'decision', $decision],
            'fundamento de 4' => [['fundamento' => 'Bien'], 'fundamento', 'Indique el fundamento de la aprobación (al menos 5 caracteres).'],
            'fundamento de 1001' => [['fundamento' => str_repeat('f', 1001)], 'fundamento', null],
            'fundamento de 999 y un emoji' => [['fundamento' => str_repeat('f', 999) . '😀'], 'fundamento', null],
            'versión como lista (H-21)' => [['version_fila' => [$version()]], '', null],
        ], []);
        $this->assertAceptado($this->enviar("admin/refinanciaciones/{$propuesta}/resolver", ['version_fila' => $version(), 'decision' => ' Aprobada ', 'fundamento' => str_repeat('f', 1000)]), 'aprobación con espacios y fundamento de 1000');

        // CU-43 Aplicar (aceptación del cliente)
        $this->comoRol('AnalistaCredito');
        $aplicar = ['version_fila' => $version(), 'fecha_constancia' => self::hoy(), 'medio' => 'Firma en la sucursal', 'observaciones' => ''];
        $this->rechazos("admin/refinanciaciones/{$propuesta}/aplicar", $aplicar, [
            'fecha vacía' => [['fecha_constancia' => ''], 'fecha_constancia', null],
            'fecha futura' => [['fecha_constancia' => self::hoy(1)], 'fecha_constancia', null],
            'fecha anterior a la propuesta' => [['fecha_constancia' => self::hoy(-1)], 'fecha_constancia', 'La aceptación no puede ser anterior al registro de la propuesta.'],
            'medio vacío' => [['medio' => ''], 'medio', 'Indique el medio de la aceptación (hasta 100 caracteres).'],
            'medio de 101' => [['medio' => str_repeat('m', 101)], 'medio', 'Indique el medio de la aceptación (hasta 100 caracteres).'],
            'medio de 99 y un emoji (H-16)' => [['medio' => str_repeat('m', 99) . '😀'], 'medio', 'Indique el medio de la aceptación (hasta 100 caracteres).'],
            'observaciones de 501' => [['observaciones' => str_repeat('o', 501)], 'observaciones', 'Las observaciones admiten hasta 500 caracteres.'],
            'observaciones de 499 y un emoji (H-16)' => [['observaciones' => str_repeat('o', 499) . '😀'], 'observaciones', 'Las observaciones admiten hasta 500 caracteres.'],
        ], ['creditos']);
        $this->assertAceptado($this->enviar("admin/refinanciaciones/{$propuesta}/aplicar", ['medio' => str_repeat('m', 98) . '😀', 'observaciones' => str_repeat('o', 498) . '😀'] + $aplicar), 'aplicación');
        $this->assertSame('Refinanciado', $this->db->table('creditos')->where('id', $credito['id'])->get()->getRow()->estado);
    }

    public function testCancelarPropuestaDeRefinanciacion(): void
    {
        $this->comoRol('AnalistaCredito');
        $credito = $this->creditoEnMora();
        $this->assertAceptado($this->enviar('admin/refinanciaciones/simular', ['cliente_id' => (string) $credito['cliente']['id'], 'creditos' => [(string) $credito['id']], 'tasa' => '60', 'cuotas' => '12']));
        $this->assertAceptado($this->enviar('admin/refinanciaciones/simulaciones/' . $this->ultimoId('simulaciones_refinanciacion') . '/propuesta'));
        $propuesta = $this->ultimoId('propuestas_refinanciacion');
        $base = ['version_fila' => (string) $this->db->table('propuestas_refinanciacion')->where('id', $propuesta)->get()->getRow()->version_fila,
            'motivo' => 'El cliente desistió', 'fecha_desistimiento' => self::hoy(), 'medio_desistimiento' => 'Nota en la sucursal'];

        $this->rechazos("admin/refinanciaciones/{$propuesta}/cancelar", $base, [
            'motivo de 4' => [['motivo' => 'Baja'], 'motivo', 'Indique el motivo de la operación (al menos 5 caracteres).'],
            'motivo de 501' => [['motivo' => str_repeat('m', 501)], 'motivo', 'El motivo admite hasta 500 caracteres.'],
            'motivo de 499 y un emoji' => [['motivo' => str_repeat('m', 499) . '😀'], 'motivo', 'El motivo admite hasta 500 caracteres.'],
            'fecha futura' => [['fecha_desistimiento' => self::hoy(1)], 'fecha_desistimiento', 'Ingrese la fecha de la constancia de desistimiento (no posterior a hoy).'],
            'fecha como lista' => [['fecha_desistimiento' => [self::hoy()]], 'fecha_desistimiento', 'Ingrese la fecha de la constancia de desistimiento (no posterior a hoy).'],
            'medio vacío' => [['medio_desistimiento' => ''], 'medio_desistimiento', 'Indique el medio por el que el cliente desistió (hasta 100 caracteres).'],
            'medio de 99 y un emoji (H-16)' => [['medio_desistimiento' => str_repeat('m', 99) . '😀'], 'medio_desistimiento', 'Indique el medio por el que el cliente desistió (hasta 100 caracteres).'],
        ], []);
        $this->assertSame('PendienteResolucion', $this->db->table('propuestas_refinanciacion')->where('id', $propuesta)->get()->getRow()->estado);
        $this->assertAceptado($this->enviar("admin/refinanciaciones/{$propuesta}/cancelar", ['motivo' => str_repeat('m', 498) . '😀'] + $base));
        $this->assertSame('Cancelada', $this->db->table('propuestas_refinanciacion')->where('id', $propuesta)->get()->getRow()->estado);
    }

    // ── IN-52 Saldo a favor y devolución (CU-76 a CU-78) ─────────────────────────────────────────────────────────

    private function saldo(int $clienteId, string $importe = '50000.00'): int
    {
        $this->db->table('saldos_a_favor')->insert(['cliente_id' => $clienteId, 'importe_original' => $importe, 'importe_disponible' => $importe]);

        return (int) $this->db->insertID();
    }

    public function testAplicarSaldoAFavor(): void
    {
        $cajero = $this->comoRol('Cajero');
        $this->fabrica->cajaAbierta($cajero['id']);
        $credito = $this->fabrica->credito();
        $this->saldo($credito['cliente']['id']);
        $base = ['cliente_id' => (string) $credito['cliente']['id'], 'cuotas' => [$credito['id'] => ['1']], 'fecha_conformidad' => self::hoy(), 'medio_conformidad' => 'Nota firmada', 'observaciones' => ''];

        $this->rechazos('admin/saldos-favor/aplicar', $base, [
            'fecha vacía' => [['fecha_conformidad' => ''], 'fecha_conformidad', 'Ingrese la fecha de la solicitud o conformidad del cliente (no posterior a hoy, A3).'],
            'fecha futura' => [['fecha_conformidad' => self::hoy(1)], 'fecha_conformidad', 'Ingrese la fecha de la solicitud o conformidad del cliente (no posterior a hoy, A3).'],
            'medio vacío' => [['medio_conformidad' => ''], 'medio_conformidad', 'Indique el medio de la solicitud o conformidad del cliente (A3).'],
            'medio de 101' => [['medio_conformidad' => str_repeat('m', 101)], 'medio_conformidad', 'Indique el medio de la solicitud o conformidad del cliente (A3).'],
            'medio de 99 y un emoji (H-16)' => [['medio_conformidad' => str_repeat('m', 99) . '😀'], 'medio_conformidad', 'Indique el medio de la solicitud o conformidad del cliente (A3).'],
            'observaciones de 151' => [['observaciones' => str_repeat('o', 151)], 'observaciones', 'Las observaciones admiten hasta 150 caracteres.'],
            'observaciones de 149 y un emoji (H-16)' => [['observaciones' => str_repeat('o', 149) . '😀'], 'observaciones', 'Las observaciones admiten hasta 150 caracteres.'],
            'sin cuotas' => [['cuotas' => []], 'seleccion', 'Seleccione al menos una cuota.'],
            'cuotas como texto' => [['cuotas' => '1'], 'seleccion', null],
        ], ['aplicaciones_saldo']);

        $this->assertAceptado($this->enviar('admin/saldos-favor/aplicar', ['medio_conformidad' => str_repeat('m', 98) . '😀', 'observaciones' => str_repeat('o', 148) . '😀'] + $base));
    }

    public function testDevolucion(): void
    {
        $this->comoRol('Gerente');
        $credito = $this->fabrica->credito();
        $saldo = $this->saldo($credito['cliente']['id']);
        $cuit = (string) $credito['cliente']['datos']['cuit_cuil'];
        $base = [
            'importe' => '10.000,00', 'motivo' => 'El cliente pidió la devolución', 'fecha_solicitud' => self::hoy(), 'medio_solicitud' => 'Nota firmada',
            'destino' => 'Transferencia', 'cbu' => Fabrica::cbu(7), 'cuit_titular' => $cuit, 'entidad' => 'Banco de Prueba', 'evidencia' => 'Constancia de CBU del titular',
        ];

        $this->rechazos("admin/saldos-favor/{$saldo}/devolucion", $base, [
            'importe cero' => [['importe' => '0'], 'importe', 'Ingrese el importe a devolver, mayor que cero.'],
            'importe mayor que el saldo' => [['importe' => '50.000,01'], 'importe', 'El importe supera el saldo disponible: *'],
            'importe como lista' => [['importe' => ['100']], 'importe', 'Ingrese el importe a devolver, mayor que cero.'],
            'motivo de 4' => [['motivo' => 'Pide'], 'motivo', 'Indique el motivo de la operación (al menos 5 caracteres).'],
            'motivo de 501' => [['motivo' => str_repeat('m', 501)], 'motivo', null],
            'motivo de 499 y un emoji (H-16)' => [['motivo' => str_repeat('m', 499) . '😀'], 'motivo', null],
            'fecha futura' => [['fecha_solicitud' => self::hoy(1)], 'fecha_solicitud', 'Ingrese la fecha de la solicitud del cliente (no posterior a hoy).'],
            'medio de 101' => [['medio_solicitud' => str_repeat('m', 101)], 'medio_solicitud', 'Indique el medio de la solicitud del cliente (hasta 100 caracteres).'],
            'destino vacío' => [['destino' => ''], 'destino', 'Seleccione el destino de la devolución. No se admiten devoluciones en efectivo (RN-73, RN-77).'],
            'destino efectivo' => [['destino' => 'Efectivo'], 'destino', 'Seleccione el destino de la devolución. No se admiten devoluciones en efectivo (RN-73, RN-77).'],
            'destino como lista (H-15)' => [['destino' => ['Transferencia']], 'destino', 'Seleccione el destino de la devolución. No se admiten devoluciones en efectivo (RN-73, RN-77).'],
            'medio original sin transacción' => [['destino' => 'MedioOriginal'], 'destino', null],
            'CBU inválido' => [['cbu' => '123'], 'cbu', 'El CBU debe tener 22 dígitos con sus dígitos verificadores correctos.'],
            'CBU como lista (H-15)' => [['cbu' => [Fabrica::cbu(7)]], 'cbu', null],
            'titular de un tercero' => [['cuit_titular' => Fabrica::cuitValido('20')], 'cuit_titular', null],
            'titular como lista (H-15)' => [['cuit_titular' => [$cuit]], 'cuit_titular', null],
            'entidad de 101' => [['entidad' => str_repeat('e', 101)], 'entidad', 'Indique la entidad bancaria (hasta 100 caracteres).'],
            'evidencia de 301' => [['evidencia' => str_repeat('e', 301)], 'evidencia', null],
            'evidencia de 299 y un emoji (H-16)' => [['evidencia' => str_repeat('e', 299) . '😀'], 'evidencia', null],
        ], ['autorizaciones_devolucion']);

        $this->assertAceptado($this->enviar("admin/saldos-favor/{$saldo}/devolucion", ['destino' => ' Transferencia ', 'evidencia' => str_repeat('e', 298) . '😀'] + $base));
        $autorizacion = $this->ultimoId('autorizaciones_devolucion');
        $version = fn (): string => (string) $this->db->table('autorizaciones_devolucion')->where('id', $autorizacion)->get()->getRow()->version_fila;

        // CU-78 Ejecutar (Cajero)
        $this->comoRol('Cajero');
        $ejecutar = ['version_fila' => $version(), 'numero_operacion' => self::operacion(), 'fecha' => self::hoy(), 'importe' => '10.000,00', 'cbu' => Fabrica::cbu(7)];
        $this->rechazos("admin/devoluciones/{$autorizacion}/ejecutar", $ejecutar, [
            'operación vacía' => [['numero_operacion' => ''], 'numero_operacion', 'Ingrese el número de operación de la transferencia (hasta 100 caracteres).'],
            'operación de 101' => [['numero_operacion' => str_repeat('9', 101)], 'numero_operacion', 'Ingrese el número de operación de la transferencia (hasta 100 caracteres).'],
            'fecha futura' => [['fecha' => self::hoy(1)], 'fecha', 'Ingrese la fecha de la transferencia (no posterior a hoy).'],
            'importe distinto' => [['importe' => '9.999,99'], 'importe', 'El importe transferido no coincide con el autorizado *'],
            'otra cuenta' => [['cbu' => Fabrica::cbu(8)], 'cbu', 'La cuenta destino no coincide con la autorizada (A6).'],
            'CBU como lista (H-15)' => [['cbu' => [Fabrica::cbu(7)]], 'cbu', 'La cuenta destino no coincide con la autorizada (A6).'],
        ], []);

        // CU-77 A4 Anular la autorización (Gerente)
        $this->comoRol('Gerente');
        $this->rechazos("admin/saldos-favor/autorizaciones/{$autorizacion}/anular", ['version_fila' => $version(), 'motivo_anulacion' => 'El cliente desistió'], [
            'motivo de 4' => [['motivo_anulacion' => 'Baja'], 'motivo_anulacion', null],
            'motivo como lista' => [['motivo_anulacion' => ['El cliente desistió']], 'motivo_anulacion', null],
            'versión como lista (H-21)' => [['version_fila' => [$version()]], '', null],
        ], []);
        $this->assertAceptado($this->enviar("admin/saldos-favor/autorizaciones/{$autorizacion}/anular", ['version_fila' => $version(), 'motivo_anulacion' => 'El cliente desistió']));
    }
}
