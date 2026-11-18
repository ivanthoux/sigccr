<?php

declare(strict_types=1);

namespace Tests\Feature\Calendario;

use CodeIgniter\I18n\Time;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-29 · Calendario operativo (CU-82, RN-49). Plan de pruebas 7.9.2 y Anexo B.10.
 *
 * Las fechas de días no hábiles son únicas en toda la base: cada prueba usa días libres de un año lejano.
 *
 * @internal
 */
final class CalendarioTest extends IntegracionTestCase
{
    private static int $siguiente = 0;

    /** Fecha futura que ninguna otra prueba usa, en dd/mm/aaaa y aaaa-mm-dd. @return array{0: string, 1: string} */
    private static function fechaLibre(): array
    {
        $t = Time::createFromDate(2090, 1, 1, 'UTC')->addDays(self::$siguiente++);

        return [$t->format('d/m/Y'), $t->format('Y-m-d')];
    }

    /** @return array<string, string|null> */
    private function dia(string $fecha): ?array
    {
        return $this->db->table('dias_no_habiles')->where('fecha', $fecha)->get()->getRowArray();
    }

    /** @param array<string, mixed> $datos */
    private function rechazo(array $datos, string $motivo, string $contexto): void
    {
        $r = $this->enviar('admin/calendario', $datos);
        $this->assertCampoRechazado($r, 'fechas', null, $contexto);
        $this->assertStringContainsString($motivo, (string) $_SESSION['errores']['fechas'], $contexto);
    }

    /** Alta de un día con cada clase de dato en la fecha, la descripción y el tipo. */
    public function testAgregarConCadaClaseDeDato(): void
    {
        $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);

        // Fecha (FEC)
        [$texto, $iso] = self::fechaLibre();
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => $texto, 'descripcion' => 'Feriado de prueba', 'tipo' => 'Feriado']));
        $this->assertSame(['fecha' => $iso, 'descripcion' => 'Feriado de prueba', 'tipo' => 'Feriado'], ['fecha' => substr((string) $this->dia($iso)['fecha'], 0, 10)] + $this->dia($iso));
        [, $iso] = self::fechaLibre();
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => $iso, 'descripcion' => 'En formato ISO', 'tipo' => 'NoLaborable']), 'aaaa-mm-dd');
        $this->assertNotNull($this->dia($iso));
        [$texto, $iso] = self::fechaLibre();
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => " {$texto} ", 'descripcion' => 'Con espacios', 'tipo' => 'CierreEntidad']), 'fecha con espacios');
        $this->assertNotNull($this->dia($iso));
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => $hoy->format('d/m/Y'), 'descripcion' => 'Hoy', 'tipo' => 'CierreEntidad']), 'hoy se admite');

        $this->rechazo(['fecha' => $hoy->subDays(1)->format('d/m/Y'), 'descripcion' => 'Ayer', 'tipo' => 'Feriado'], 'es una fecha pasada (A1)', 'fecha pasada');
        $this->rechazo(['fecha' => $hoy->format('d/m/Y'), 'descripcion' => 'Hoy otra vez', 'tipo' => 'Feriado'], 'ya está registrada (A2)', 'fecha repetida');
        foreach (['30/02/2091', '2091/01/01', '01-01-2091', 'mañana', '01/01/91'] as $mal) {
            $this->rechazo(['fecha' => $mal, 'descripcion' => 'Fecha inválida', 'tipo' => 'Feriado'], 'la fecha no es válida (dd/mm/aaaa)', $mal);
        }
        $this->rechazo(['fecha' => ['01/01/2091'], 'descripcion' => 'Fecha como lista', 'tipo' => 'Feriado'], 'la fecha no es válida (dd/mm/aaaa)', 'fecha como lista');
        $r = $this->enviar('admin/calendario', ['fecha' => '', 'descripcion' => '', 'tipo' => 'Feriado', 'carga' => '']);
        $this->assertCampoRechazado($r, 'fechas', 'Ingrese al menos una fecha con su descripción.', 'formulario vacío');

        // Descripción (TXT, N = 150)
        foreach (
            [
            '150 caracteres' => [str_repeat('d', 150), true],
            '150 con tildes y eñe' => [str_repeat('ñá', 75), true],
            '148 y un emoji' => [str_repeat('d', 148) . '😀', true],
            '149 y un emoji' => [str_repeat('d', 149) . '😀', false],
            '151 caracteres' => [str_repeat('d', 151), false],
            'vacía' => ['', false],
            'solo espacios' => ['    ', false],
            'como lista' => [['Feriado'], false],
            ] as $caso => [$descripcion, $acepta]
        ) {
            [$texto, $iso] = self::fechaLibre();
            $datos = ['fecha' => $texto, 'descripcion' => $descripcion, 'tipo' => 'Feriado'];
            if ($acepta) {
                $this->assertAceptado($this->enviar('admin/calendario', $datos), "descripción {$caso}");
                $this->assertSame($descripcion, $this->dia($iso)['descripcion'], "descripción {$caso}");
            } else {
                $this->rechazo($datos, 'falta la descripción (hasta 150 caracteres)', "descripción {$caso}");
                $this->assertNull($this->dia($iso));
            }
        }
        [$texto, $iso] = self::fechaLibre();
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => $texto, 'descripcion' => "  Día \t del  <b>empleado</b> ", 'tipo' => 'Feriado']));
        $this->assertSame('Día del <b>empleado</b>', $this->dia($iso)['descripcion'], 'se recorta y se guarda el texto tal cual');

        // Tipo (SEL)
        foreach (['', 'feriado', 'Inexistente', ['Feriado']] as $tipo) {
            [$texto, $iso] = self::fechaLibre();
            $this->rechazo(['fecha' => $texto, 'descripcion' => 'Tipo inválido', 'tipo' => $tipo], 'el tipo no es válido', 'tipo ' . json_encode($tipo));
        }
        [$texto, $iso] = self::fechaLibre();
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => $texto, 'descripcion' => 'Tipo con espacios', 'tipo' => ' Feriado ']), 'SEL-04');
        $this->assertSame('Feriado', $this->dia($iso)['tipo']);
    }

    /** Carga de varias fechas en texto (CU-82 paso 3): «fecha; descripción; tipo» por línea. */
    public function testCargaDeVariasFechas(): void
    {
        $this->comoRol('Gerente');
        [$a, $isoA] = self::fechaLibre();
        [$b, $isoB] = self::fechaLibre();
        [$c, $isoC] = self::fechaLibre();
        [$d, $isoD] = self::fechaLibre();
        $carga = implode("\r\n", [
            "{$a}; Feriado A; Feriado",
            "  {$b} ;  Día B ; Día no laborable  ",
            '',
            "{$c}; Día C",
            "{$d}; Día D; Inexistente",
            '31/02/2091; Fecha imposible; Feriado',
            "{$a}; Repetida en la carga; Feriado",
        ]);

        $r = $this->enviar('admin/calendario', ['fecha' => '', 'descripcion' => '', 'tipo' => 'CierreEntidad', 'carga' => $carga]);

        $this->assertAceptado($r);
        $this->assertSame('Feriado', $this->dia($isoA)['tipo']);
        $this->assertSame(['Día B', 'NoLaborable'], [$this->dia($isoB)['descripcion'], $this->dia($isoB)['tipo']], 'por el nombre del tipo');
        $this->assertSame('CierreEntidad', $this->dia($isoC)['tipo'], 'sin tipo: el elegido en el formulario');
        $this->assertNull($this->dia($isoD));
        $aviso = (string) ($_SESSION['aviso'] ?? '');
        foreach (["{$d}: el tipo no es válido", '31/02/2091: la fecha no es válida (dd/mm/aaaa)', "{$a}: ya está registrada (A2)"] as $rechazo) {
            $this->assertStringContainsString($rechazo, $aviso);
        }
        $this->assertStringContainsString('Se registraron 3 día(s)', (string) $_SESSION['exito']);

        $this->rechazo(['carga' => ['una lista']], 'Ingrese al menos una fecha con su descripción.', 'carga como lista');
    }

    /** Baja de un día (CU-82 paso 3, A1). */
    public function testBaja(): void
    {
        $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        [$texto, $iso] = self::fechaLibre();
        $this->assertAceptado($this->enviar('admin/calendario', ['fecha' => $texto, 'descripcion' => 'Para dar de baja', 'tipo' => 'Feriado']));

        $pasada = 'Solo se pueden dar de baja días de hoy en adelante: modificar fechas pasadas alteraría cómputos ya realizados.';
        foreach (['' => $pasada, 'abc' => $pasada, $hoy->subDays(1)->format('d/m/Y') => $pasada, '01/01/2095' => 'La fecha no está registrada como día no hábil.'] as $fecha => $mensaje) {
            $this->enviar('admin/calendario/baja', ['fecha' => $fecha]);
            $this->assertSame($mensaje, $_SESSION['error'] ?? null, "baja «{$fecha}»");
        }
        $this->enviar('admin/calendario/baja', ['fecha' => [$texto]]);
        $this->assertSame($pasada, $_SESSION['error'] ?? null, 'fecha como lista');
        $this->assertNotNull($this->dia($iso));

        $this->assertAceptado($this->enviar('admin/calendario/baja', ['fecha' => $iso]));
        $this->assertNull($this->dia($iso));
    }
}
