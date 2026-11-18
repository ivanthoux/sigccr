<?php

declare(strict_types=1);

namespace Tests\Feature\Fondos;

use App\Services\Fondos\FondosService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-23 y IN-24 · Movimientos de fondos (CU-85, RN-44, RN-71). Plan de pruebas 7.9.2 y Anexo B.8.
 *
 * @internal
 */
final class FondosTest extends IntegracionTestCase
{
    private const MENSAJE_IMPORTE = 'Ingrese un importe mayor que cero.';
    private const MENSAJE_FECHA = 'Ingrese la fecha del movimiento (dd/mm/aaaa).';

    /** @return array<string, array<string, mixed>> definición de cada campo (Anexo B.8) */
    public static function campos(): array
    {
        return [
            'tipo' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['AporteCapital', 'Financiamiento', 'GastoOperativo', 'Otro'],
                'mensajes' => ['obligatorio' => 'Seleccione el tipo de movimiento.', 'formato' => 'Seleccione el tipo de movimiento.'],
            ],
            'caracter' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['Ingreso', 'Egreso'],
                'mensajes' => ['obligatorio' => 'Indique si es un ingreso o un egreso.', 'formato' => 'Indique si es un ingreso o un egreso.'],
            ],
            'importe' => [
                'tipo' => 'IMP', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => self::MENSAJE_IMPORTE, 'formato' => self::MENSAJE_IMPORTE, 'cero' => self::MENSAJE_IMPORTE],
            ],
            'fecha' => [
                'tipo' => 'FEC', 'obligatorio' => true, 'anio_valido' => 2020,
                'mensajes' => ['obligatorio' => self::MENSAJE_FECHA, 'formato' => self::MENSAJE_FECHA],
            ],
            'concepto' => [
                'tipo' => 'TXT', 'obligatorio' => true, 'max' => 200,
                'mensajes' => ['obligatorio' => 'Ingrese el concepto (hasta 200 caracteres).', 'largo' => 'Ingrese el concepto (hasta 200 caracteres).'],
            ],
            'referencia' => [
                'tipo' => 'TXT', 'obligatorio' => false, 'max' => 100,
                'mensajes' => ['largo' => 'La referencia admite hasta 100 caracteres.'],
            ],
        ];
    }

    /** @return array<string, string> datos válidos: un ingreso realizado hoy */
    public static function datos(): array
    {
        return [
            'tipo' => 'AporteCapital', 'caracter' => 'Ingreso', 'importe' => '1.000,00',
            'fecha' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'), 'concepto' => 'Aporte de prueba', 'referencia' => '',
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casos(): array
    {
        return self::casosDelCatalogo('IN-23', self::campos());
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Un egreso realizado necesita disponible libre (RN-71): cada prueba parte con fondos de sobra
        $this->db->table('movimientos_fondos')->insert([
            'tipo' => 'AporteCapital', 'caracter' => 'Ingreso', 'importe' => '1000000000.00', 'fecha' => '2020-01-01',
            'concepto' => 'Fondos iniciales de la prueba', 'estado' => 'Realizado', 'usuario_id' => $this->fabrica->responsable(),
        ]);
    }

    /**
     * IN-23: cada campo con cada clase de dato.
     *
     * @dataProvider casos
     * @param array<string, mixed> $otros
     */
    public function testRegistrarCampoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Gerente');
        $antes = $this->ultimoId('movimientos_fondos');

        $r = $this->enviar('admin/fondos', self::conValor(self::datos(), $campo, $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            $m = $this->movimiento($this->ultimoId('movimientos_fondos'));
            $this->assertGreaterThan($antes, (int) $m['id'], 'Debe registrarse el movimiento');
            $this->assertValorGuardado($esperado->guardado, $m[$campo], $campo);
            $this->assertSame('Realizado', $m['estado']);

            return;
        }
        if ($esperado->mensaje === Esperado::PETICION) {
            $this->assertCampoRechazado($r, $campo, $esperado->mensaje);

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame($antes, $this->ultimoId('movimientos_fondos'), 'Un rechazo no debe registrar el movimiento');
    }

    /** Programado y realizado según la fecha (CU-85 paso 4). */
    public function testProgramadoYRealizadoSegunLaFecha(): void
    {
        $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $manana = $hoy->addDays(1)->format('d/m/Y');

        $this->assertCampoRechazado($this->enviar('admin/fondos', ['fecha' => $manana] + self::datos()), 'fecha', 'Un movimiento realizado no puede tener fecha futura: márquelo como programado.');
        $this->assertCampoRechazado($this->enviar('admin/fondos', ['programado' => '1'] + self::datos()), 'fecha', 'Un movimiento programado debe tener una fecha futura.');
        $this->assertCampoRechazado($this->enviar('admin/fondos', ['programado' => '1', 'fecha' => $hoy->subDays(1)->format('d/m/Y')] + self::datos()), 'fecha', 'Un movimiento programado debe tener una fecha futura.');

        foreach (['1', 'si', ['1']] as $marca) {
            $this->assertAceptado($this->enviar('admin/fondos', ['programado' => $marca, 'fecha' => $manana] + self::datos()), 'programado ' . json_encode($marca));
            $this->assertSame('Programado', $this->movimiento($this->ultimoId('movimientos_fondos'))['estado']);
        }
        foreach (['', '0'] as $sinMarca) {
            $this->assertAceptado($this->enviar('admin/fondos', ['programado' => $sinMarca] + self::datos()), "programado «{$sinMarca}»");
            $this->assertSame('Realizado', $this->movimiento($this->ultimoId('movimientos_fondos'))['estado']);
        }
    }

    /** RN-71: un egreso realizado no puede superar el disponible libre; un egreso programado sí. */
    public function testEgresoYDisponibleLibre(): void
    {
        $this->comoRol('Gerente');
        $libre = (new FondosService($this->db))->disponibilidad()['libre'];
        $mas = bcadd($libre, '0.01', 2);
        $manana = Time::now(config('Sigccr')->zonaNegocio)->addDays(1)->format('d/m/Y');

        $r = $this->enviar('admin/fondos', ['caracter' => 'Egreso', 'importe' => str_replace('.', ',', $mas)] + self::datos());
        $this->assertCampoRechazado($r, 'importe', 'El egreso supera el disponible libre (' . str_replace('.', ',', $libre) . '): no pueden usarse reservas, compromisos ni la reserva mínima (RN-71).');

        $this->assertAceptado($this->enviar('admin/fondos', ['caracter' => 'Egreso', 'importe' => str_replace('.', ',', $mas), 'programado' => '1', 'fecha' => $manana] + self::datos()), 'egreso programado');
        $this->assertAceptado($this->enviar('admin/fondos', ['caracter' => 'Egreso', 'importe' => str_replace('.', ',', $libre)] + self::datos()), 'egreso por todo el disponible libre');
        $this->assertSame('0.00', (new FondosService($this->db))->disponibilidad()['libre']);
    }

    /** IMP-08 con fondos: dos ingresos del máximo admitido no deben impedir consultar la disponibilidad. */
    public function testIngresosDelMaximoAdmitido(): void
    {
        $this->comoRol('Gerente');
        foreach ([1, 2] as $vez) {
            $r = $this->enviar('admin/fondos', ['importe' => '99999999999999999'] + self::datos());
            if ($vez === 1) {
                $this->assertAceptado($r, 'primer ingreso del máximo');
            }
        }
        $this->pedir('admin/fondos')->assertStatus(200);
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosMotivo(): array
    {
        return self::casosDelCatalogo('IN-24', ['motivo' => ['tipo' => 'MOT', 'obligatorio' => true]]);
    }

    /**
     * IN-24: motivo de la corrección de un movimiento realizado (CU-85 A2).
     *
     * @dataProvider casosMotivo
     * @param array<string, mixed> $otros
     */
    public function testCorregirConCadaClaseDeMotivo(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Gerente');
        $id = $this->registrar(self::datos());
        $antes = $this->ultimoId('movimientos_fondos');

        $r = $this->enviar("admin/fondos/{$id}/corregir", self::conValor([], $campo, $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "motivo: {$esperado}");
            $inverso = $this->movimiento($this->ultimoId('movimientos_fondos'));
            $this->assertSame($id, (int) $inverso['movimiento_corregido_id']);
            $this->assertSame('Egreso', $inverso['caracter'], 'El inverso de un ingreso es un egreso');
            $this->assertValorGuardado($esperado->guardado, $inverso['motivo'], 'motivo');

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "motivo: {$esperado}");
        $this->assertSame($antes, $this->ultimoId('movimientos_fondos'), 'Un rechazo no debe registrar el inverso');
    }

    /** CU-85 A2 y A3: corrección única, confirmación y anulación de programados. */
    public function testCorreccionConfirmacionYAnulacion(): void
    {
        $this->comoRol('Gerente');
        $realizado = $this->registrar(self::datos());
        $this->assertAceptado($this->enviar("admin/fondos/{$realizado}/corregir", ['motivo' => 'Importe mal cargado']));
        $inverso = $this->ultimoId('movimientos_fondos');
        foreach ([$realizado, $inverso] as $id) {
            $this->enviar("admin/fondos/{$id}/corregir", ['motivo' => 'Segunda corrección']);
            $this->assertSame('El movimiento ya es una corrección o ya fue corregido.', $_SESSION['error'] ?? null, "corregir {$id}");
        }

        $manana = Time::now(config('Sigccr')->zonaNegocio)->addDays(1)->format('d/m/Y');
        $programado = $this->registrar(['programado' => '1', 'fecha' => $manana] + self::datos());
        $this->enviar("admin/fondos/{$programado}/confirmar");
        $this->assertSame('Todavía no llegó la fecha del movimiento: solo se considera en la proyección.', $_SESSION['error'] ?? null);
        $this->enviar("admin/fondos/{$programado}/corregir", ['motivo' => 'Corregir un programado']);
        $this->assertSame('Solo se corrigen movimientos realizados; un movimiento programado se anula.', $_SESSION['error'] ?? null);
        $this->assertCampoRechazado($this->enviar("admin/fondos/{$programado}/anular", ['motivo' => 'Baja']), 'motivo', 'Indique el motivo de la operación (al menos 5 caracteres).');
        $this->assertAceptado($this->enviar("admin/fondos/{$programado}/anular", ['motivo' => 'Ya no se recibirá']));
        $this->assertSame('Anulado', $this->movimiento($programado)['estado']);
        $this->enviar("admin/fondos/{$programado}/anular", ['motivo' => 'Anular otra vez']);
        $this->assertSame('Solo se anulan movimientos programados; uno realizado se corrige.', $_SESSION['error'] ?? null);
        $this->enviar("admin/fondos/{$realizado}/confirmar");
        $this->assertSame('El movimiento no está programado.', $_SESSION['error'] ?? null);

        foreach (['corregir', 'confirmar', 'anular'] as $accion) {
            $this->enviar("admin/fondos/999999999/{$accion}", ['motivo' => 'Movimiento inexistente']);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? $_SESSION['errores']['motivo'] ?? ''), "{$accion} de un movimiento inexistente");
        }
    }

    /** @param array<string, mixed> $datos */
    private function registrar(array $datos): int
    {
        $this->assertAceptado($this->enviar('admin/fondos', $datos), 'alta del movimiento');

        return $this->ultimoId('movimientos_fondos');
    }

    /** @return array<string, mixed> */
    private function movimiento(int $id): array
    {
        return $this->db->table('movimientos_fondos')->where('id', $id)->get()->getRowArray() ?? [];
    }
}
