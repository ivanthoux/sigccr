<?php

declare(strict_types=1);

namespace Tests\Feature\Clientes;

use App\Libraries\Clientes\PoliticaDatos;
use App\Services\Clientes\PreRegistroService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-78 · Depuración de los pre-registros sin completar (D-64; Ley 25.326, art. 4, inc. 7): se eliminan a los 90 días
 * los que no se completaron con el alta y se conservan los completados y los recientes. Plan de pruebas 7.9.
 *
 * @internal
 */
final class DepuracionPreRegistrosTest extends IntegracionTestCase
{
    private const PROCESO = 'vencimientos:controlar';

    private function hace(int $dias): string
    {
        return Time::now('UTC')->subDays($dias)->format('Y-m-d H:i:s.vP');
    }

    /** @param array<string, mixed> $cambios */
    private function preRegistro(string $estado, int $creadoHace, ?int $actualizadoHace = null, array $cambios = []): int
    {
        $p = Fabrica::personaHumana();
        $this->db->table('pre_registros')->insert($cambios + [
            'tipo_documento' => 'DNI', 'nro_documento' => (string) $p['dni'], 'cuit_cuil' => (string) $p['cuil'], 'nombre' => 'Prueba', 'apellido' => 'Depuración',
            'correo' => 'depuracion.' . Fabrica::sufijo() . '@prueba.local', 'vence' => $this->hace($creadoHace), 'fecha_politica' => $this->hace($creadoHace),
            'estado' => $estado, 'creado_en' => $this->hace($creadoHace), 'actualizado_en' => $actualizadoHace === null ? null : $this->hace($actualizadoHace),
        ]);

        return $this->ultimoId('pre_registros');
    }

    private function existe(int $id): bool
    {
        return $this->contar('pre_registros', ['id' => $id]) === 1;
    }

    public function testElPlazoEsElDeLaPolitica(): void
    {
        $this->assertSame(90, PoliticaDatos::DIAS_CONSERVACION_PRE_REGISTRO);
    }

    public function testEliminaLosNoCompletadosVencidosYConservaElResto(): void
    {
        $cliente = $this->fabrica->cliente();
        $sinVerificar = $this->preRegistro('PendienteVerificacion', 91);
        $verificadoViejo = $this->preRegistro('PendienteValidacion', 120, 91, ['fecha_verificacion' => $this->hace(91)]);
        $descartado = $this->preRegistro('Descartado', 100, 100, ['motivo_descarte' => 'No se presentó a verificar su identidad.']);
        $verificadoReciente = $this->preRegistro('PendienteValidacion', 200, 10, ['fecha_verificacion' => $this->hace(10)]);
        $reciente = $this->preRegistro('PendienteVerificacion', 89);
        $completado = $this->preRegistro('Completado', 200, 150, ['cliente_id' => $cliente['id']]);
        $this->db->table('alertas')->insert([
            'tipo' => 'Operativa', 'causa' => 'Pre-registro verificado.', 'rol_destino' => 'AnalistaCredito', 'objeto_tipo' => 'pre_registros', 'objeto_id' => (string) $verificadoViejo,
            'fecha_generacion' => $this->hace(91), 'estado' => 'Pendiente',
        ]);
        $auditoriaAntes = $this->contar('registros_auditoria', ['accion' => 'pre-registros.depurar']);

        $eliminados = (new PreRegistroService($this->db))->depurar(self::PROCESO);

        $this->assertGreaterThanOrEqual(3, $eliminados);
        foreach ([$sinVerificar, $verificadoViejo, $descartado] as $id) {
            $this->assertFalse($this->existe($id), "El pre-registro {$id} debía eliminarse.");
        }
        foreach ([$verificadoReciente, $reciente, $completado] as $id) {
            $this->assertTrue($this->existe($id), "El pre-registro {$id} debía conservarse.");
        }
        $this->assertSame(0, $this->contar('alertas', ['objeto_tipo' => 'pre_registros', 'objeto_id' => (string) $verificadoViejo, 'estado' => 'Pendiente']), 'La alerta del pre-registro eliminado se resuelve.');
        $this->assertSame($auditoriaAntes + 1, $this->contar('registros_auditoria', ['accion' => 'pre-registros.depurar']));
        $detalle = json_decode((string) $this->db->query("SELECT TOP 1 detalle FROM dbo.registros_auditoria WHERE accion = 'pre-registros.depurar' ORDER BY id DESC")->getRowArray()['detalle'], true);
        $this->assertSame($eliminados, $detalle['cantidad']);
        $this->assertSame(90, $detalle['dias']);
    }

    /** Sin nada que eliminar no se escribe en la auditoría (el proceso corre cada 15 minutos). */
    public function testSinNadaQueEliminarNoAudita(): void
    {
        $this->preRegistro('PendienteVerificacion', 91);
        $servicio = new PreRegistroService($this->db);
        $servicio->depurar(self::PROCESO);
        $auditoria = $this->contar('registros_auditoria', ['accion' => 'pre-registros.depurar']);
        $this->assertSame(0, $servicio->depurar(self::PROCESO));
        $this->assertSame($auditoria, $this->contar('registros_auditoria', ['accion' => 'pre-registros.depurar']));
    }

    /** El borde: a los 90 días exactos ya se elimina; un minuto antes, no. */
    public function testElBordeDeLosNoventaDias(): void
    {
        $justo = $this->preRegistro('PendienteVerificacion', 90);
        $falta = $this->preRegistro('PendienteVerificacion', 89);
        (new PreRegistroService($this->db))->depurar(self::PROCESO);
        $this->assertFalse($this->existe($justo));
        $this->assertTrue($this->existe($falta));
    }
}
