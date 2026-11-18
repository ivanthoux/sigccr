<?php

declare(strict_types=1);

namespace Tests\Feature\Solicitudes;

use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-34 · Simular crédito (CU-21, RN-13, RN-14, RN-76) e IN-35 · Simular todas las líneas (CU-99, D-10). Plan de
 * pruebas 7.9.2 y Anexo B.12. La línea de la fábrica presta de $ 100.000,00 a $ 1.500.000,00 en 3 a 24 cuotas.
 *
 * @internal
 */
final class SimulacionTest extends IntegracionTestCase
{
    private const RANGO = 'La línea presta entre $ 100.000,00 y $ 1.500.000,00 (RN-76).';
    private const IMPORTE_INVALIDO = 'Ingrese un importe válido, por ejemplo 850.000,00.';
    private const CUOTAS_RANGO = 'La línea admite entre 3 y 24 cuotas (RN-14).';
    private const CUOTAS_ENTERO = 'La cantidad de cuotas debe ser un número entero.';

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado}> */
    public static function casos(): array
    {
        $casos = [];
        foreach (
            [
            // Importe (IMP) dentro del rango de la línea
            ['importe', 'COM-01 campo ausente', Esperado::AUSENTE, Esperado::rechaza('Ingrese el importe a financiar.')],
            ['importe', 'COM-02 enviado como lista', ['500000'], Esperado::rechaza('Ingrese el importe a financiar.')],
            ['importe', 'COM-03 código HTML', '<b>500000</b>', Esperado::rechaza(self::IMPORTE_INVALIDO)],
            ['importe', 'IMP-01 vacío', '', Esperado::rechaza('Ingrese el importe a financiar.')],
            ['importe', 'IMP-01 solo espacios', '   ', Esperado::rechaza('Ingrese el importe a financiar.')],
            ['importe', 'IMP-02 puntos y coma', '1.234.567,89', Esperado::acepta('1234567.89')],
            ['importe', 'IMP-02 solo coma', '500000,50', Esperado::acepta('500000.50')],
            ['importe', 'IMP-02 solo punto', '500000.50', Esperado::acepta('500000.50')],
            ['importe', 'IMP-02 con signo de pesos', '$ 750.000', Esperado::acepta('750000.00')],
            ['importe', 'IMP-03 un decimal', '500000,5', Esperado::acepta('500000.50')],
            ['importe', 'IMP-05 cero', '0', Esperado::rechaza(self::RANGO)],
            ['importe', 'IMP-06 negativo', '-500000', Esperado::rechaza(self::IMPORTE_INVALIDO)],
            ['importe', 'IMP-07 tres decimales', '500000,123', Esperado::rechaza(self::IMPORTE_INVALIDO)],
            ['importe', 'IMP-09 letras', 'quinientos mil', Esperado::rechaza(self::IMPORTE_INVALIDO)],
            ['importe', 'IMP-09 notación científica', '5e5', Esperado::rechaza(self::IMPORTE_INVALIDO)],
            ['importe', 'IMP-10 mínimo de la línea', '100.000,00', Esperado::acepta('100000.00')],
            ['importe', 'IMP-10 mínimo − 0,01', '99.999,99', Esperado::rechaza(self::RANGO)],
            ['importe', 'IMP-10 máximo de la línea', '1.500.000,00', Esperado::acepta('1500000.00')],
            ['importe', 'IMP-10 máximo + 0,01', '1.500.000,01', Esperado::rechaza(self::RANGO)],
            ['importe', 'IMP-08 17 dígitos', '99999999999999999', Esperado::rechaza(self::RANGO)],
            // Cuotas (ENT) dentro del rango de la línea
            ['cantidad_cuotas', 'COM-01 campo ausente', Esperado::AUSENTE, Esperado::rechaza('Ingrese la cantidad de cuotas.')],
            ['cantidad_cuotas', 'COM-02 enviado como lista', ['12'], Esperado::rechaza('Ingrese la cantidad de cuotas.')],
            ['cantidad_cuotas', 'ENT-01 vacío', '', Esperado::rechaza('Ingrese la cantidad de cuotas.')],
            ['cantidad_cuotas', 'ENT-02 mínimo 3', '3', Esperado::acepta('3')],
            ['cantidad_cuotas', 'ENT-02 mínimo − 1', '2', Esperado::rechaza(self::CUOTAS_RANGO)],
            ['cantidad_cuotas', 'ENT-02 máximo 24', '24', Esperado::acepta('24')],
            ['cantidad_cuotas', 'ENT-02 máximo + 1', '25', Esperado::rechaza(self::CUOTAS_RANGO)],
            ['cantidad_cuotas', 'ENT-02 cero', '0', Esperado::rechaza(self::CUOTAS_RANGO)],
            ['cantidad_cuotas', 'ENT-03 decimal', '12,5', Esperado::rechaza(self::CUOTAS_ENTERO)],
            ['cantidad_cuotas', 'ENT-03 negativo', '-12', Esperado::rechaza(self::CUOTAS_ENTERO)],
            ['cantidad_cuotas', 'ENT-03 letras', 'doce', Esperado::rechaza(self::CUOTAS_ENTERO)],
            ['cantidad_cuotas', 'ENT-03 cuatro dígitos', '1200', Esperado::rechaza(self::CUOTAS_ENTERO)],
            ['cantidad_cuotas', 'ENT-04 con espacios', ' 12 ', Esperado::acepta('12')],
            ['cantidad_cuotas', 'ENT-04 con ceros a la izquierda', '012', Esperado::acepta('12')],
            ] as [$campo, $nombre, $valor, $esperado]
        ) {
            $casos["IN-34 · {$campo} · {$nombre}"] = [$campo, $valor, $esperado];
        }

        return $casos;
    }

    /**
     * IN-34: importe y cuotas con cada clase de dato.
     *
     * @dataProvider casos
     */
    public function testSimularConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado): void
    {
        $this->comoRol('AnalistaCredito');
        $cliente = $this->fabrica->clienteParaCredito();
        $linea = $this->fabrica->linea()['id'];
        $antes = $this->ultimoId('simulaciones');

        $base = ['cliente_id' => (string) $cliente['id'], 'linea_id' => (string) $linea, 'importe' => '500.000,00', 'cantidad_cuotas' => '12'];
        $r = $this->enviar('admin/simulaciones', self::conValor($base, $campo, $valor));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            $s = $this->db->table('simulaciones')->where('id', $this->ultimoId('simulaciones'))->get()->getRowArray();
            $this->assertSame($cliente['id'], (int) $s['cliente_id']);
            $this->assertValorGuardado($esperado->guardado, $s[$campo], $campo);
            $this->assertSame('Vigente', $s['estado']);

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame($antes, $this->ultimoId('simulaciones'), 'Un rechazo no debe registrar la simulación');
    }

    /** IN-34: cliente y línea (REF) y elegibilidad (RN-13, RN-16). H-21: un identificador como lista no elige otro registro. */
    public function testClienteYLinea(): void
    {
        $this->comoRol('AnalistaCredito');
        $cliente = $this->fabrica->clienteParaCredito();
        $linea = $this->fabrica->linea()['id'];
        $base = ['cliente_id' => (string) $cliente['id'], 'linea_id' => (string) $linea, 'importe' => '500.000,00', 'cantidad_cuotas' => '12'];

        $suspendida = $this->fabrica->linea()['id'];
        $this->db->table('lineas_credito')->where('id', $suspendida)->update(['estado' => 'Suspendida']);
        $soloEmpresas = $this->fabrica->linea(['admite_persona_humana' => ''])['id'];
        $desactivado = $this->fabrica->clienteParaCredito()['id'];
        $this->db->table('clientes')->where('id', $desactivado)->update(['estado' => 'Desactivado']);

        foreach (
            [
            'cliente vacío' => ['cliente_id' => ''],
            'cliente ausente' => ['cliente_id' => Esperado::AUSENTE],
            'cliente no numérico' => ['cliente_id' => 'abc'],
            'cliente con letras al final (H-21)' => ['cliente_id' => $cliente['id'] . 'abc'],
            'cliente como lista (H-21)' => ['cliente_id' => [(string) $cliente['id']]],
            'cliente decimal (H-21)' => ['cliente_id' => $cliente['id'] . '.9'],
            'cliente inexistente' => ['cliente_id' => '999999999'],
            'cliente desactivado' => ['cliente_id' => (string) $desactivado],
            'línea vacía' => ['linea_id' => ''],
            'línea como lista (H-21)' => ['linea_id' => [(string) $linea]],
            'línea inexistente' => ['linea_id' => '999999999'],
            'línea suspendida' => ['linea_id' => (string) $suspendida],
            'línea que no admite personas humanas' => ['linea_id' => (string) $soloEmpresas],
            ] as $caso => $cambios
        ) {
            $antes = $this->ultimoId('simulaciones');
            $r = $this->enviar('admin/simulaciones', self::conValor($base, (string) array_key_first($cambios), reset($cambios)));
            $this->assertTrue($r->isRedirect(), $caso);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), "{$caso}: falta el mensaje de error");
            $this->assertSame($antes, $this->ultimoId('simulaciones'), "{$caso}: no debe registrarse la simulación");
        }
        $this->assertAceptado($this->enviar('admin/simulaciones', ['cliente_id' => " {$cliente['id']} "] + $base), 'identificador con espacios');
    }

    /** @return array<string, array{0: string, 1: mixed, 2: ?string}> */
    public static function casosComparativa(): array
    {
        $importe = 'Ingrese un importe válido, por ejemplo 500.000,00, o déjelo vacío.';
        $cuotas = 'Ingrese una cantidad de cuotas entre 1 y 360, o déjela vacía.';
        $criterio = 'Seleccione el criterio de comparación.';
        $c = [];
        foreach (
            [
            ['importe', 'IMP-01 vacío: el máximo de cada línea', '', null],
            ['importe', 'COM-02 como lista: vacío', ['500000'], null],
            ['importe', 'IMP-02 con puntos y coma', '500.000,00', null],
            ['importe', 'IMP-05 cero', '0', $importe],
            ['importe', 'IMP-06 negativo', '-1', $importe],
            ['importe', 'IMP-07 tres decimales', '1,123', $importe],
            ['importe', 'IMP-09 letras', 'abc', $importe],
            ['cantidad_cuotas', 'ENT-01 vacío', '', null],
            ['cantidad_cuotas', 'COM-02 como lista: vacío', ['12'], null],
            ['cantidad_cuotas', 'ENT-02 1', '1', null],
            ['cantidad_cuotas', 'ENT-02 360', '360', null],
            ['cantidad_cuotas', 'ENT-02 0', '0', $cuotas],
            ['cantidad_cuotas', 'ENT-02 361', '361', $cuotas],
            ['cantidad_cuotas', 'ENT-03 decimal', '12,5', $cuotas],
            ['cantidad_cuotas', 'ENT-03 negativo', '-1', $cuotas],
            ['cantidad_cuotas', 'ENT-04 con espacios', ' 12 ', null],
            ['criterio', 'SEL-01 Monto', 'Monto', null],
            ['criterio', 'SEL-01 Costo', 'Costo', null],
            ['criterio', 'SEL-02 vacío', '', $criterio],
            ['criterio', 'COM-01 ausente', Esperado::AUSENTE, $criterio],
            ['criterio', 'COM-02 como lista', ['Monto'], $criterio],
            ['criterio', 'SEL-03 inexistente', 'Plazo', $criterio],
            ['criterio', 'SEL-03 otras mayúsculas', 'monto', $criterio],
            ['criterio', 'SEL-04 espacios en los extremos', ' Monto ', null],
            ] as [$campo, $nombre, $valor, $mensaje]
        ) {
            $c["IN-35 · {$campo} · {$nombre}"] = [$campo, $valor, $mensaje];
        }

        return $c;
    }

    /**
     * IN-35: comparación de todas las líneas (CU-99) con cada clase de dato.
     *
     * @dataProvider casosComparativa
     */
    public function testCompararConCadaClaseDeDato(string $campo, mixed $valor, ?string $mensaje): void
    {
        $this->comoRol('AnalistaCredito');
        $cliente = $this->fabrica->clienteParaCredito();
        $this->fabrica->linea();
        $antes = $this->ultimoId('simulaciones_comparativas');

        $base = ['cliente_id' => (string) $cliente['id'], 'importe' => '500.000,00', 'cantidad_cuotas' => '12', 'criterio' => 'Monto'];
        $r = $this->enviar('admin/simulaciones/comparar', self::conValor($base, $campo, $valor));

        if ($mensaje === null) {
            $this->assertAceptado($r, $campo);
            $this->assertGreaterThan($antes, $this->ultimoId('simulaciones_comparativas'));

            return;
        }
        $this->assertCampoRechazado($r, $campo, $mensaje, $campo);
        $this->assertSame($antes, $this->ultimoId('simulaciones_comparativas'), 'Un rechazo no debe registrar la comparación');
    }

    /** IN-35: cliente (REF) de la comparación; H-21. */
    public function testCompararCliente(): void
    {
        $this->comoRol('AnalistaCredito');
        $cliente = $this->fabrica->clienteParaCredito();
        $base = ['importe' => '', 'cantidad_cuotas' => '', 'criterio' => 'Monto'];
        foreach (['', 'abc', '999999999', [(string) $cliente['id']], $cliente['id'] . 'x'] as $valor) {
            $antes = $this->ultimoId('simulaciones_comparativas');
            $this->enviar('admin/simulaciones/comparar', ['cliente_id' => $valor] + $base);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), 'cliente ' . json_encode($valor));
            $this->assertSame($antes, $this->ultimoId('simulaciones_comparativas'), 'cliente ' . json_encode($valor));
        }
    }

    /**
     * H-24 y H-25: una línea válida según RN-14 con un gasto de otorgamiento alto produce un CFTEA que no cabe en
     * DECIMAL(18,12) o un neto negativo. La simulación se rechaza con un mensaje y la comparación la informa como no viable.
     */
    public function testLineaConCostoFueraDeLimite(): void
    {
        $this->comoRol('AnalistaCredito');
        $cliente = $this->fabrica->clienteParaCredito();
        $antes = $this->ultimoId('simulaciones');
        // H-24: 80 % de gasto más su IVA deja un neto del 3,2 %: el CFTEA no cabe en DECIMAL(18,12)
        // H-25: 99,9999 % más su IVA supera el capital: no hay neto para desembolsar
        foreach (['80' => \App\Libraries\Finanzas\Tasas::MENSAJE_FUERA_DE_LIMITE, '99,9999' => \App\Libraries\Finanzas\Tasas::MENSAJE_SIN_NETO] as $gasto => $mensaje) {
            // D-54 limita el gasto a 50 %: estas líneas son anteriores a la decisión y se crean con el gasto en la base
            $extrema = $this->fabrica->linea(['tna' => '500'])['id'];
            $this->db->table('versiones_condiciones')->where('linea_credito_id', $extrema)->update(['gasto_otorgamiento_pct' => bcdiv(str_replace(',', '.', (string) $gasto), '100', 6)]);
            $this->enviar('admin/simulaciones', ['cliente_id' => (string) $cliente['id'], 'linea_id' => (string) $extrema, 'importe' => '500.000,00', 'cantidad_cuotas' => '12']);
            $this->assertSame($mensaje, $_SESSION['error'] ?? null, "gasto {$gasto} %");
            $this->assertSame($antes, $this->ultimoId('simulaciones'));
        }

        $this->assertAceptado($this->enviar('admin/simulaciones/comparar', ['cliente_id' => (string) $cliente['id'], 'importe' => '500.000,00', 'cantidad_cuotas' => '12', 'criterio' => 'Costo']));
        $c = $this->db->table('simulaciones_comparativas')->where('id', $this->ultimoId('simulaciones_comparativas'))->get()->getRowArray();
        $this->assertStringContainsString('costo financiero total que el sistema no puede registrar', (string) $c['lineas_no_viables']);
        $this->assertStringContainsString('no queda nada para desembolsar', (string) $c['lineas_no_viables']);
    }
}
