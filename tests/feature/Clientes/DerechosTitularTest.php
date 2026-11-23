<?php

declare(strict_types=1);

namespace Tests\Feature\Clientes;

use App\Libraries\Clientes\DerechosTitular;
use App\Services\Clientes\DerechosTitularService;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-77 · Solicitudes de derechos del titular de los datos personales (CU-108, D-64; Ley 25.326, arts. 14, 16 y 20;
 * RN-85, RN-86), por el portal y en la sucursal. Plan de pruebas 7.9.
 *
 * @internal
 */
final class DerechosTitularTest extends IntegracionTestCase
{
    private const DETALLE = 'Mi domicilio cambió y necesito que lo actualicen.';

    /** @return array{id: int, cuenta_id: int, correo: string, clave: string} cliente con la sesión del portal iniciada */
    private function conSesionDelPortal(): array
    {
        $c = $this->fabrica->clienteConAcceso();
        $this->comoUsuario($c, 'Cliente');

        return $c;
    }

    /** @return array<string, mixed> */
    private function fila(int $id): array
    {
        return $this->db->table('solicitudes_derechos')->where('id', $id)->get()->getRowArray();
    }

    public function testElPortalMuestraElFormularioYSoloLasSolicitudesPropias(): void
    {
        $ajena = $this->fabrica->clienteConAcceso();
        (new DerechosTitularService($this->db))->registrar($ajena['id'], ['tipo' => 'Acceso', 'detalle' => 'Detalle de otra persona.'], 'Portal');
        $this->conSesionDelPortal();
        $r = $this->pedir('/portal/derechos');
        $r->assertOK();
        $r->assertSee('Mis derechos sobre mis datos');
        $r->assertDontSee('Detalle de otra persona.');
        $r->assertSee('Todavía no hizo ninguna solicitud');
    }

    public function testElClienteRegistraUnAccesoConPlazoDeDiezDiasCorridos(): void
    {
        $c = $this->conSesionDelPortal();
        $r = $this->enviar('/portal/derechos', ['tipo' => 'Acceso', 'detalle' => 'Quiero una copia de mis datos.']);
        $this->assertAceptado($r);

        $f = $this->fila($this->ultimoId('solicitudes_derechos'));
        $this->assertSame($c['id'], (int) $f['cliente_id']);
        $this->assertSame('Pendiente', $f['estado']);
        $this->assertSame('Portal', $f['canal']);
        $this->assertSame(Time::now(config('Sigccr')->zonaNegocio)->addDays(10)->format('Y-m-d'), substr((string) $f['vence'], 0, 10));
        $this->assertSame(1, $this->contar('registros_auditoria', ['accion' => 'derechos.registrar', 'objeto_id' => (string) $f['id']]));
        $this->assertSame(1, $this->contar('alertas', ['objeto_tipo' => 'solicitudes_derechos', 'objeto_id' => (string) $f['id'], 'rol_destino' => 'AnalistaCredito', 'estado' => 'Pendiente']));
        $this->pedir('/portal/derechos')->assertSee('Quiero una copia de mis datos.');
    }

    /** @dataProvider tiposEnDiasHabiles */
    public function testRectificacionActualizacionYSupresionVencenAlosCincoDiasHabiles(string $tipo): void
    {
        $this->conSesionDelPortal();
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => $tipo, 'detalle' => self::DETALLE]));
        $f = $this->fila($this->ultimoId('solicitudes_derechos'));

        $hoy = Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d');
        $noHabiles = array_map(static fn (array $d) => substr((string) $d['fecha'], 0, 10), $this->db->query('SELECT fecha FROM dbo.dias_no_habiles WHERE fecha >= CAST(? AS date)', [$hoy])->getResultArray());
        $esperada = DerechosTitular::vence(new DateTimeImmutable($hoy), $tipo, $noHabiles)->format('Y-m-d');
        $this->assertSame($esperada, substr((string) $f['vence'], 0, 10));
        $this->assertGreaterThanOrEqual(5, (int) (new DateTimeImmutable($hoy))->diff(new DateTimeImmutable($esperada))->format('%a'), 'Cinco días hábiles son al menos cinco días corridos.');
    }

    /** @return array<string, array{0: string}> */
    public static function tiposEnDiasHabiles(): array
    {
        return ['rectificación' => ['Rectificacion'], 'actualización' => ['Actualizacion'], 'supresión' => ['Supresion']];
    }

    public function testValidacionesDelPedido(): void
    {
        $this->conSesionDelPortal();
        $antes = $this->contar('solicitudes_derechos');
        $this->assertCampoRechazado($this->enviar('/portal/derechos', ['tipo' => '', 'detalle' => self::DETALLE]), 'tipo', null);
        $this->assertCampoRechazado($this->enviar('/portal/derechos', ['tipo' => 'Borrar', 'detalle' => self::DETALLE]), 'tipo', null);
        $this->assertCampoRechazado($this->enviar('/portal/derechos', ['tipo' => 'Acceso', 'detalle' => 'corto']), 'detalle', null);
        $this->assertCampoRechazado($this->enviar('/portal/derechos', ['tipo' => 'RevisionAutomatizada', 'detalle' => self::DETALLE]), 'solicitud_id', null);
        $this->assertSame($antes, $this->contar('solicitudes_derechos'));
    }

    /** RN-86: un pedido pendiente del mismo tipo no se repite. */
    public function testNoSeRepiteUnPedidoPendiente(): void
    {
        $this->conSesionDelPortal();
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'Acceso', 'detalle' => self::DETALLE]));
        $antes = $this->contar('solicitudes_derechos');
        $r = $this->enviar('/portal/derechos', ['tipo' => 'Acceso', 'detalle' => self::DETALLE]);
        $this->assertTrue($r->isRedirect());
        $this->assertStringContainsString('Ya hay un pedido pendiente', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame($antes, $this->contar('solicitudes_derechos'));
        // otro tipo sí se acepta
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'Supresion', 'detalle' => self::DETALLE]));
    }

    /** El cliente es el de la sesión: un cliente_id enviado en la petición se ignora. */
    public function testElClienteEsElDeLaSesion(): void
    {
        $otro = $this->fabrica->cliente();
        $c = $this->conSesionDelPortal();
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'Acceso', 'detalle' => self::DETALLE, 'cliente_id' => $otro['id']]));
        $this->assertSame($c['id'], (int) $this->fila($this->ultimoId('solicitudes_derechos'))['cliente_id']);
    }

    /** Art. 20: la revisión se pide sobre una solicitud propia rechazada por motivos crediticios y la resuelve el Gerente. */
    public function testRevisionDeUnaDecisionAutomatizada(): void
    {
        $s = $this->fabrica->solicitud();
        $this->db->table('solicitudes_credito')->where('id', $s['id'])->update(['estado' => 'Rechazada', 'tipo_rechazo' => 'Crediticio']);
        $this->fabrica->habilitarPortal($s['cliente']['id']);
        $cuenta = $this->db->query('SELECT ca.correo FROM dbo.clientes c JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id WHERE c.id = ?', [$s['cliente']['id']])->getRowArray();
        $this->comoUsuario(['correo' => $cuenta['correo'], 'clave' => \Tests\Support\Feature\Fabrica::CLAVE], 'Cliente');

        // una solicitud ajena no se puede revisar
        $ajena = $this->fabrica->solicitud();
        $this->db->table('solicitudes_credito')->where('id', $ajena['id'])->update(['estado' => 'Rechazada', 'tipo_rechazo' => 'Crediticio']);
        $this->assertCampoRechazado($this->enviar('/portal/derechos', ['tipo' => 'RevisionAutomatizada', 'detalle' => self::DETALLE, 'solicitud_id' => $ajena['id']]), 'solicitud_id', null);

        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'RevisionAutomatizada', 'detalle' => 'No estoy de acuerdo con el resultado.', 'solicitud_id' => $s['id']]));
        $id = $this->ultimoId('solicitudes_derechos');
        $this->assertSame($s['id'], (int) $this->fila($id)['solicitud_id']);
        $this->assertSame(1, $this->contar('alertas', ['objeto_tipo' => 'solicitudes_derechos', 'objeto_id' => (string) $id, 'rol_destino' => 'Gerente']));

        // el Analista la ve pero no la resuelve; el Gerente sí
        $this->comoRol('AnalistaCredito');
        $this->pedir("/admin/derechos/{$id}")->assertSee('la resuelve el Gerente');
        $r = $this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Atendida', 'respuesta' => 'Se revisó la solicitud con sus antecedentes.', 'version_fila' => 1]);
        $this->assertTrue($r->isRedirect());
        $this->assertSame('Pendiente', $this->fila($id)['estado']);

        $gerente = $this->comoRol('Gerente');
        $this->assertAceptado($this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Atendida', 'respuesta' => 'Se revisó la solicitud con sus antecedentes.', 'version_fila' => 1]));
        $f = $this->fila($id);
        $this->assertSame('Atendida', $f['estado']);
        $this->assertSame($gerente['id'], (int) $f['usuario_id']);
    }

    public function testSoloSeRevisanLasSolicitudesRechazadasPorMotivosCrediticios(): void
    {
        $s = $this->fabrica->solicitud();
        $this->fabrica->habilitarPortal($s['cliente']['id']);
        $c = $this->db->query('SELECT ca.correo FROM dbo.clientes c JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id WHERE c.id = ?', [$s['cliente']['id']])->getRowArray();
        $this->comoUsuario(['correo' => $c['correo'], 'clave' => \Tests\Support\Feature\Fabrica::CLAVE], 'Cliente');
        $r = $this->enviar('/portal/derechos', ['tipo' => 'RevisionAutomatizada', 'detalle' => self::DETALLE, 'solicitud_id' => $s['id']]);
        $this->assertCampoRechazado($r, 'solicitud_id', 'Solo se puede pedir la revisión de una solicitud suya rechazada por motivos crediticios.');
    }

    public function testElAnalistaAtiendeYLaRespuestaLlegaAlCliente(): void
    {
        $c = $this->conSesionDelPortal();
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'Rectificacion', 'detalle' => self::DETALLE]));
        $id = $this->ultimoId('solicitudes_derechos');

        $analista = $this->comoRol('AnalistaCredito');
        $this->pedir('/admin/derechos')->assertSee('Rectificación de datos inexactos');
        $this->pedir("/admin/derechos/{$id}")->assertSee(self::DETALLE);

        // la respuesta es obligatoria y el resultado, uno de los dos
        $this->assertCampoRechazado($this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Atendida', 'respuesta' => 'ok', 'version_fila' => 1]), 'respuesta', null);
        $this->assertCampoRechazado($this->enviar("/admin/derechos/{$id}/resolver", ['estado' => '', 'respuesta' => 'Se actualizó el domicilio.', 'version_fila' => 1]), 'estado', null);
        $this->assertSame('Pendiente', $this->fila($id)['estado']);

        $this->assertAceptado($this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Atendida', 'respuesta' => 'Se actualizó el domicilio.', 'version_fila' => 1]));
        $f = $this->fila($id);
        $this->assertSame('Atendida', $f['estado']);
        $this->assertSame('Se actualizó el domicilio.', $f['respuesta']);
        $this->assertSame($analista['id'], (int) $f['usuario_id']);
        $this->assertSame(0, $this->contar('alertas', ['objeto_tipo' => 'solicitudes_derechos', 'objeto_id' => (string) $id, 'estado' => 'Pendiente']), 'La alerta se resuelve.');
        $this->assertSame(1, $this->contar('registros_auditoria', ['accion' => 'derechos.resolver', 'objeto_id' => (string) $id]));

        // ya respondida: no se responde dos veces
        $this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Denegada', 'respuesta' => 'Otra respuesta distinta.', 'version_fila' => 2]);
        $this->assertSame('Atendida', $this->fila($id)['estado']);

        // el cliente ve la respuesta
        $this->comoUsuario($c, 'Cliente');
        $this->pedir('/portal/derechos')->assertSee('Se actualizó el domicilio.');
    }

    public function testElAnalistaDeniegaConSuMotivo(): void
    {
        $this->conSesionDelPortal();
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'Supresion', 'detalle' => 'Quiero que borren todos mis datos.']));
        $id = $this->ultimoId('solicitudes_derechos');
        $this->comoRol('AnalistaCredito');
        $this->assertAceptado($this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Denegada', 'respuesta' => 'Tiene un crédito vigente: los datos deben conservarse.', 'version_fila' => 1]));
        $this->assertSame('Denegada', $this->fila($id)['estado']);
    }

    /** Con una versión vieja (otro usuario respondió antes) la respuesta no se pisa. */
    public function testControlDeVersion(): void
    {
        $this->conSesionDelPortal();
        $this->assertAceptado($this->enviar('/portal/derechos', ['tipo' => 'Acceso', 'detalle' => self::DETALLE]));
        $id = $this->ultimoId('solicitudes_derechos');
        $this->comoRol('AnalistaCredito');
        $this->db->table('solicitudes_derechos')->where('id', $id)->update(['version_fila' => 5]);
        $this->enviar("/admin/derechos/{$id}/resolver", ['estado' => 'Atendida', 'respuesta' => 'Se entregó la copia.', 'version_fila' => 1]);
        $this->assertSame('Pendiente', $this->fila($id)['estado']);
    }

    public function testElAnalistaRegistraUnaSolicitudDeLaSucursal(): void
    {
        $c = $this->fabrica->cliente();
        $this->comoRol('AnalistaCredito');
        $this->pedir('/admin/derechos/nueva', ['cliente' => $c['id']])->assertSee('Registrar solicitud de derechos');
        $this->assertAceptado($this->enviar('/admin/derechos', ['cliente_id' => $c['id'], 'tipo' => 'Acceso', 'detalle' => 'Se presentó en la sucursal y pidió sus datos.']));
        $f = $this->fila($this->ultimoId('solicitudes_derechos'));
        $this->assertSame('Sucursal', $f['canal']);
        $this->assertSame($c['id'], (int) $f['cliente_id']);
    }

    public function testSinPermisoNoSeAccedeAlPanel(): void
    {
        $this->comoRol('Cajero');
        $this->pedir('/admin/derechos')->assertStatus(403);
        $this->comoRol('Administrador');
        $this->pedir('/admin/derechos')->assertStatus(403);
        $this->sinSesion();
        $this->assertTrue($this->pedir('/admin/derechos')->isRedirect());
        $this->assertTrue($this->pedir('/portal/derechos')->isRedirect());
    }

    public function testElListadoFiltraPorEstadoYTipo(): void
    {
        $c = $this->fabrica->cliente();
        $s = new DerechosTitularService($this->db);
        $a = $s->registrar($c['id'], ['tipo' => 'Acceso', 'detalle' => 'Quiero ver mis datos.'], 'Sucursal');
        $s->registrar($c['id'], ['tipo' => 'Supresion', 'detalle' => 'Quiero que los eliminen.'], 'Sucursal');
        $this->comoRol('AnalistaCredito');
        $r = $this->pedir('/admin/derechos', ['tipo' => 'Acceso']);
        $r->assertSee('Acceso a mis datos');
        $accesos = $s->buscar('Pendiente', 'Acceso');
        $this->assertContains($a, array_map(static fn (array $f) => (int) $f['id'], $accesos));
        $this->assertSame(['Acceso'], array_values(array_unique(array_column($accesos, 'tipo'))));
    }
}
