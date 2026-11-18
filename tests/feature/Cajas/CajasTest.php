<?php

declare(strict_types=1);

namespace Tests\Feature\Cajas;

use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-25 a IN-28 · Cajas (CU-80, CU-72, CU-81) y apertura y cierre de la jornada (CU-31, CU-36, RN-28, RN-32). Plan de
 * pruebas 7.9.2 y Anexo B.9.
 *
 * @internal
 */
final class CajasTest extends IntegracionTestCase
{
    private const MENSAJE_CODIGO = 'Ingrese un código de hasta 10 letras, números o guiones (por ejemplo, CAJA-01).';

    /** @return array<string, array<string, mixed>> definición de cada campo (Anexo B.9) */
    public static function campos(): array
    {
        return [
            'denominacion' => [
                'tipo' => 'TXT', 'obligatorio' => true, 'max' => 60,
                'mensajes' => ['obligatorio' => 'Ingrese la denominación de la caja (hasta 60 caracteres).', 'largo' => 'Ingrese la denominación de la caja (hasta 60 caracteres).'],
            ],
            'ubicacion' => ['tipo' => 'TXT', 'obligatorio' => false, 'max' => 100, 'mensajes' => ['largo' => 'La ubicación admite hasta 100 caracteres.']],
            'observaciones' => ['tipo' => 'TXT', 'obligatorio' => false, 'max' => 300, 'mensajes' => ['largo' => 'Las observaciones admiten hasta 300 caracteres.']],
        ];
    }

    /** @return array<string, string> */
    public static function datos(): array
    {
        return ['codigo' => self::codigo(), 'denominacion' => 'Caja de prueba', 'ubicacion' => 'Planta baja', 'observaciones' => ''];
    }

    /** Código único de 10 caracteres. */
    private static function codigo(string $prefijo = 'C'): string
    {
        return $prefijo . strtoupper(substr(Fabrica::sufijo(), -(10 - strlen($prefijo))));
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casos(): array
    {
        $casos = self::casosDelCatalogo('IN-25', self::campos());
        // Código de la caja: hasta 10 letras, números o guiones, en mayúsculas y único (CU-80 paso 3)
        $u = static fn (int $n): string => strtoupper(substr(Fabrica::sufijo(), -$n));
        $mal = Esperado::rechaza(self::MENSAJE_CODIGO);
        foreach (
            [
            ['COM-01 campo ausente', Esperado::AUSENTE, $mal],
            ['COM-02 enviado como lista', ['CAJA-01'], $mal],
            ['COM-03 código HTML', '<b>C1</b>', $mal],
            ['COD-01 vacío', '', $mal],
            ['COD-02 minúsculas', 'c-' . strtolower($v = $u(8)), Esperado::acepta("C-{$v}")],
            ['COD-02 espacios en los extremos', ' ' . ($v2 = 'E' . $u(9)) . ' ', Esperado::acepta($v2)],
            ['COD-03 1 carácter', $v3 = $u(1), Esperado::acepta($v3)],
            ['COD-03 10 caracteres', $v4 = 'D' . $u(9), Esperado::acepta($v4)],
            ['COD-03 11 caracteres', 'F' . $u(10), $mal],
            ['COD-04 espacio interno', 'CAJA 01', $mal],
            ['COD-04 guion bajo', 'CAJA_01', $mal],
            ['COD-04 tilde', 'CAJÁ1', $mal],
            ['COD-04 eñe', 'CAÑA1', $mal],
            ['COD-04 punto', 'CAJA.1', $mal],
            ] as [$nombre, $valor, $esperado]
        ) {
            $casos["IN-25 · codigo · {$nombre}"] = ['codigo', $valor, $esperado, []];
        }

        return $casos;
    }

    /**
     * IN-25: alta de caja con cada campo y cada clase de dato.
     *
     * @dataProvider casos
     * @param array<string, mixed> $otros
     */
    public function testRegistrarCampoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Gerente');
        $antes = $this->ultimoId('cajas');

        $r = $this->enviar('admin/cajas', self::conValor(self::datos(), $campo, $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            $caja = $this->caja($this->ultimoId('cajas'));
            $this->assertGreaterThan($antes, (int) $caja['id']);
            $this->assertValorGuardado($esperado->guardado, $caja[$campo], $campo);
            $this->assertSame('Habilitada', $caja['estado']);

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame($antes, $this->ultimoId('cajas'), 'Un rechazo no debe crear la caja');
    }

    /** COD-05: el código es único sin distinguir mayúsculas. */
    public function testCodigoUnico(): void
    {
        $this->comoRol('Gerente');
        $datos = self::datos();
        $this->assertAceptado($this->enviar('admin/cajas', $datos));
        $this->assertCampoRechazado($this->enviar('admin/cajas', ['codigo' => strtolower($datos['codigo'])] + self::datos()), 'codigo', 'Ya existe una caja con ese código.');
    }

    /** IN-26: asignación de la caja a un Cajero (CU-72) y vigencia (A3). */
    public function testAsignarConCadaClaseDeDato(): void
    {
        $gerente = $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $f = static fn (Time $t): string => $t->format('d/m/Y');
        $inicio = 'Ingrese una fecha de inicio válida (dd/mm/aaaa).';
        $fin = 'Ingrese una fecha de fin válida (dd/mm/aaaa) o déjela vacía.';
        $cajero = 'Seleccione un Cajero con la cuenta habilitada.';

        $rechazos = [
            'desde ayer' => [['desde' => $f($hoy->subDays(1))], 'desde', 'La asignación no puede comenzar en una fecha pasada.'],
            'desde inválida' => [['desde' => '31/02/2030'], 'desde', $inicio],
            'desde texto' => [['desde' => 'mañana'], 'desde', $inicio],
            'desde en otro formato' => [['desde' => '2030/01/01'], 'desde', $inicio],
            'hasta inválida' => [['hasta' => '32/01/2030'], 'hasta', $fin],
            'hasta antes de desde' => [['desde' => $f($hoy->addDays(5)), 'hasta' => $f($hoy->addDays(4))], 'hasta', 'La fecha de fin no puede ser anterior a la de inicio.'],
            'sin cajero' => [['cajero_id' => ''], 'cajero_id', $cajero],
            'cajero ausente' => [['cajero_id' => Esperado::AUSENTE], 'cajero_id', $cajero],
            'cajero no numérico' => [['cajero_id' => 'abc'], 'cajero_id', $cajero],
            'cajero como lista' => [['cajero_id' => ['1']], 'cajero_id', $cajero],
            'cajero inexistente' => [['cajero_id' => '999999999'], 'cajero_id', $cajero],
            'un Gerente como cajero' => [['cajero_id' => (string) $gerente['id']], 'cajero_id', $cajero],
            'cajero inhabilitado' => [['cajero_id' => (string) $this->fabrica->usuario('Cajero', 'Desactivada')['id']], 'cajero_id', $cajero],
        ];
        $caja = $this->nuevaCaja();
        $base = ['cajero_id' => (string) $this->fabrica->usuario('Cajero')['id'], 'desde' => '', 'hasta' => ''];
        foreach ($rechazos as $caso => [$cambios, $campo, $mensaje]) {
            $datos = array_filter($cambios + $base, static fn ($v) => $v !== Esperado::AUSENTE);
            $this->assertCampoRechazado($this->enviar("admin/cajas/{$caja}/asignar", $datos), $campo, $mensaje, $caso);
            $this->assertSame(0, $this->contar('asignaciones_caja', ['caja_id' => $caja]), "{$caso}: no debe asignarse");
        }

        $aceptados = [
            'desde vacío: hoy, sin fin' => [['desde' => '', 'hasta' => ''], $hoy->format('Y-m-d'), null],
            'desde hoy y hasta hoy' => [['desde' => $f($hoy), 'hasta' => $f($hoy)], $hoy->format('Y-m-d'), $hoy->format('Y-m-d')],
            'desde futuro en aaaa-mm-dd' => [['desde' => $hoy->addDays(3)->format('Y-m-d'), 'hasta' => ''], $hoy->addDays(3)->format('Y-m-d'), null],
            'desde con espacios' => [['desde' => ' ' . $f($hoy) . ' ', 'hasta' => ''], $hoy->format('Y-m-d'), null],
        ];
        foreach ($aceptados as $caso => [$cambios, $desde, $hasta]) {
            $caja = $this->nuevaCaja();
            $r = $this->enviar("admin/cajas/{$caja}/asignar", ['cajero_id' => (string) $this->fabrica->usuario('Cajero')['id']] + $cambios);
            $this->assertAceptado($r, $caso);
            $a = $this->db->table('asignaciones_caja')->where('caja_id', $caja)->get()->getRowArray();
            $this->assertValorGuardado($desde, $a['desde'], "{$caso}: desde");
            $this->assertSame($hasta, $a['hasta'] === null ? null : substr((string) $a['hasta'], 0, 10), "{$caso}: hasta");
        }
    }

    /** CU-72 A1, A2 y A5; CU-81: una caja y un Cajero con una sola asignación vigente; inhabilitar y rehabilitar. */
    public function testReglasDeAsignacionYEstado(): void
    {
        $this->comoRol('Gerente');
        $caja = $this->nuevaCaja();
        $otraCaja = $this->nuevaCaja();
        $cajero = (string) $this->fabrica->usuario('Cajero')['id'];
        $otroCajero = (string) $this->fabrica->usuario('Cajero')['id'];

        $this->assertAceptado($this->enviar("admin/cajas/{$caja}/asignar", ['cajero_id' => $cajero]));
        $this->enviar("admin/cajas/{$caja}/asignar", ['cajero_id' => $otroCajero]);
        $this->assertSame('La caja ya está asignada: finalice la asignación vigente antes de asignarla a otro Cajero.', $_SESSION['error'] ?? null);
        $this->enviar("admin/cajas/{$otraCaja}/asignar", ['cajero_id' => $cajero]);
        $this->assertStringStartsWith('El Cajero ya tiene asignada la caja ', (string) ($_SESSION['error'] ?? ''));

        $this->assertCampoRechazado($this->enviar("admin/cajas/{$caja}/finalizar-asignacion", ['motivo_fin' => 'Fin']), 'motivo_fin', 'Indique el motivo de la operación (al menos 5 caracteres).');
        $this->assertAceptado($this->enviar("admin/cajas/{$caja}/finalizar-asignacion", ['motivo_fin' => 'Cambio de turno']));
        $this->enviar("admin/cajas/{$caja}/finalizar-asignacion", ['motivo_fin' => 'Cambio de turno']);
        $this->assertSame('La caja no tiene una asignación vigente.', $_SESSION['error'] ?? null);

        $this->assertCampoRechazado($this->enviar("admin/cajas/{$caja}/estado", ['accion' => 'inhabilitar', 'motivo' => '']), 'motivo', 'Indique el motivo de la operación (al menos 5 caracteres).');
        $this->assertAceptado($this->enviar("admin/cajas/{$caja}/estado", ['accion' => 'inhabilitar', 'motivo' => 'Equipo en reparación']));
        $this->assertSame('Inhabilitada', $this->caja($caja)['estado']);
        $this->enviar("admin/cajas/{$caja}/asignar", ['cajero_id' => $otroCajero]);
        $this->assertSame('La caja no existe o está inhabilitada (CU-81).', $_SESSION['error'] ?? null);
        $this->enviar("admin/cajas/{$caja}/estado", ['accion' => 'inhabilitar', 'motivo' => 'Otra vez inhabilitar']);
        $this->assertSame('La caja ya está Inhabilitada.', $_SESSION['error'] ?? null);
        $this->assertAceptado($this->enviar("admin/cajas/{$caja}/estado", ['accion' => 'rehabilitar', 'motivo' => 'Equipo reparado']));
        $this->assertSame('Habilitada', $this->caja($caja)['estado']);

        // SEL-03 (D-52): una acción fuera de la lista no se toma como «rehabilitar»
        $this->assertAceptado($this->enviar("admin/cajas/{$caja}/estado", ['accion' => 'inhabilitar', 'motivo' => 'Equipo en reparación']));
        foreach (['', 'Inhabilitar', 'borrar', ['inhabilitar']] as $accion) {
            $this->assertCampoRechazado($this->enviar("admin/cajas/{$caja}/estado", ['accion' => $accion, 'motivo' => 'Acción fuera de la lista']), 'accion', 'Seleccione la acción.', 'acción ' . json_encode($accion));
            $this->assertSame('Inhabilitada', $this->caja($caja)['estado']);
        }

        $this->enviar('admin/cajas/999999999/estado', ['accion' => 'inhabilitar', 'motivo' => 'Caja inexistente']);
        $this->assertSame('La caja no existe.', $_SESSION['error'] ?? null);
    }

    /** IN-27: apertura de la jornada (CU-31, RN-28). */
    public function testApertura(): void
    {
        $cajero = $this->comoRol('Cajero');
        $this->enviar('admin/caja');
        $this->assertSame('No tiene una caja asignada y habilitada: solicite la asignación al Gerente (CU-72).', $_SESSION['error'] ?? null);

        $this->asignar($cajero['id']);
        $this->assertAceptado($this->enviar('admin/caja'));
        $this->assertSame(1, $this->contar('jornadas_caja', ['usuario_id' => $cajero['id'], 'estado' => 'Abierta']));
        $this->enviar('admin/caja');
        $this->assertStringStartsWith('Ya tiene una caja abierta (', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame(1, $this->contar('jornadas_caja', ['usuario_id' => $cajero['id']]));
    }

    /** @return array<string, array{0: string, 1: mixed, 2: string|null, 3: string|null}> */
    public static function casosCierre(): array
    {
        $acreditado = 'Ingrese el total acreditado por Transferencia o depósito según el extracto o el resumen del procesador (un importe no negativo).';
        $explicar = 'Hay una diferencia de 100,00: explique su causa (al menos 10 caracteres).';

        // [acreditado, explicación, campo del error o null si se acepta, mensaje]
        return [
            'IMP-01 vacío sin cobros' => ['', '', null, null],
            'IMP-05 cero' => ['0', '', null, null],
            'IMP-05 cero con decimales' => ['0,00', '', null, null],
            'IMP-02 importe con diferencia y explicación' => ['100,00', 'Acreditación duplicada del banco', null, null],
            'IMP-06 negativo' => ['-1', '', 'acreditado_Transferencia', $acreditado],
            'IMP-09 letras' => ['abc', '', 'acreditado_Transferencia', $acreditado],
            'IMP-07 tres decimales' => ['1,234', '', 'acreditado_Transferencia', $acreditado],
            'IMP-08 18 dígitos' => ['999999999999999999', '', 'acreditado_Transferencia', $acreditado],
            'COM-02 importe como lista' => [['100'], '', 'acreditado_Transferencia', $acreditado],
            'TXT-01 diferencia sin explicación' => ['100', '', 'explicacion_Transferencia', $explicar],
            'TXT-02 explicación de 9 caracteres' => ['100', str_repeat('e', 9), 'explicacion_Transferencia', $explicar],
            'TXT-02 explicación de 10 caracteres' => ['100', str_repeat('e', 10), null, null],
            'TXT-02 explicación de 9 con espacios' => ['100', '  ' . str_repeat('e', 9) . '   ', 'explicacion_Transferencia', $explicar],
            'TXT-02 500 caracteres' => ['100', str_repeat('e', 500), null, null],
            'TXT-03 501 caracteres' => ['100', str_repeat('e', 501), 'explicacion_Transferencia', 'La explicación admite hasta 500 caracteres.'],
            'TXT-05 499 caracteres y un emoji' => ['100', str_repeat('e', 499) . '😀', 'explicacion_Transferencia', 'La explicación admite hasta 500 caracteres.'],
            'TXT-05 498 caracteres y un emoji' => ['100', str_repeat('e', 498) . '😀', null, null],
            'COM-02 explicación como lista' => ['100', ['Acreditación duplicada'], 'explicacion_Transferencia', $explicar],
            'COM-03 explicación con HTML' => ['100', '<b>Acreditación</b><script>alert(1)</script>', null, null],
        ];
    }

    /**
     * IN-28: cierre con la conciliación de cada medio (CU-36, RN-32). La jornada no tiene cobros: lo acreditado es la
     * diferencia.
     *
     * @dataProvider casosCierre
     */
    public function testCierreConCadaClaseDeDato(mixed $acreditado, mixed $explicacion, ?string $campo, ?string $mensaje): void
    {
        $cajero = $this->comoRol('Cajero');
        $this->asignar($cajero['id']);
        $this->assertAceptado($this->enviar('admin/caja'));
        $jornada = $this->db->table('jornadas_caja')->where('usuario_id', $cajero['id'])->get()->getRowArray();

        $r = $this->enviar('admin/caja/cierre', [
            'version_fila' => (string) $jornada['version_fila'],
            'acreditado' => ['Transferencia' => $acreditado], 'explicacion' => ['Transferencia' => $explicacion],
        ]);

        $estado = $this->db->table('jornadas_caja')->where('id', $jornada['id'])->get()->getRowArray()['estado'];
        if ($campo === null) {
            $this->assertAceptado($r);
            $this->assertSame('Cerrada', $estado);
            $c = $this->db->table('conciliaciones_jornada')->where('jornada_caja_id', $jornada['id'])->get()->getRowArray();
            if (is_string($acreditado) && $acreditado !== '' && $acreditado !== '0' && $acreditado !== '0,00') {
                $this->assertValorGuardado('100.00', $c['diferencia']);
                $this->assertSame(trim((string) preg_replace('/\s+/', ' ', (string) $explicacion)), $c['explicacion']);
            } else {
                $this->assertNull($c, 'Sin cobros ni acreditaciones no hay conciliación');
            }

            return;
        }
        $this->assertCampoRechazado($r, $campo, $mensaje);
        $this->assertSame('Abierta', $estado, 'Un rechazo no debe cerrar la jornada');
    }

    /** CU-36: versión desactualizada y cierre sin jornada abierta. */
    public function testCierreConcurrenteYSinJornada(): void
    {
        $cajero = $this->comoRol('Cajero');
        $this->enviar('admin/caja/cierre', ['version_fila' => '1']);
        $this->assertSame('No tiene una caja abierta.', $_SESSION['error'] ?? null);

        $this->asignar($cajero['id']);
        $this->assertAceptado($this->enviar('admin/caja'));
        $jornada = $this->db->table('jornadas_caja')->where('usuario_id', $cajero['id'])->get()->getRowArray();
        foreach (['', 'abc', (string) ((int) $jornada['version_fila'] + 1)] as $version) {
            $this->enviar('admin/caja/cierre', ['version_fila' => $version]);
            $this->assertSame('El registro fue modificado por otra operación. Actualice la pantalla y vuelva a intentarlo.', $_SESSION['error'] ?? null, "versión «{$version}»");
        }
        $this->assertAceptado($this->enviar('admin/caja/cierre', ['version_fila' => (string) $jornada['version_fila']]));
    }

    private function nuevaCaja(): int
    {
        $this->db->table('cajas')->insert(['codigo' => self::codigo('T'), 'denominacion' => 'Caja de prueba', 'estado' => 'Habilitada']);

        return (int) $this->db->insertID();
    }

    /** Asigna una caja nueva al Cajero desde hoy (como lo haría el Gerente con CU-72). */
    private function asignar(int $cajeroId): int
    {
        $caja = $this->nuevaCaja();
        $this->db->table('asignaciones_caja')->insert(['caja_id' => $caja, 'usuario_id' => $cajeroId, 'desde' => Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d')]);

        return $caja;
    }

    /** @return array<string, mixed> */
    private function caja(int $id): array
    {
        return $this->db->table('cajas')->where('id', $id)->get()->getRowArray() ?? [];
    }
}
