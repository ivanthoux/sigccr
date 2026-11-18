<?php

declare(strict_types=1);

namespace Tests\Feature\Solicitudes;

use App\Libraries\Solicitudes\ModeloContrato;
use App\Services\Solicitudes\ContratoService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-55 · Modelo de contrato configurable (CU-106, D-56; RN-15, RN-78). El Gerente registra una versión de las
 * cláusulas; los contratos que se generan desde entonces la usan y los anteriores conservan la suya.
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
}
