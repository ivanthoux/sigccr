<?php

declare(strict_types=1);

namespace Tests\Feature\Campanas;

use App\Libraries\Campanas\ReglasCampanas;
use App\Services\Campanas\CampanaService;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-61 a IN-63 · Campañas crediticias (iteración 4, bloque B): aceptar (CU-52) y rechazar (CU-52 A1) una oferta desde
 * el portal y consultar las campañas en el panel (CU-50). Los parámetros de campañas (CU-51) son IN-33. Plan de
 * pruebas 7.9.2 y Anexo B.16.
 *
 * Cada prueba parte de una oferta vigente de una campaña activa (Fabrica::oferta).
 *
 * @internal
 */
final class CampanasTest extends IntegracionTestCase
{
    /** @return array{0: array<string, mixed>, 1: string, 2: string} oferta, versión leída y clave de la primera alternativa permitida */
    private function ofertaEnElPortal(): array
    {
        $o = $this->fabrica->oferta();
        $this->comoUsuario($o['acceso'], 'Cliente');
        $detalle = (new CampanaService($this->db))->ofertaParaCliente($o['cliente']['id'], $o['id']);
        $this->assertNotSame([], $detalle['aceptables'], 'La oferta debe tener alternativas permitidas');

        return [$o, (string) $detalle['version_fila'], ReglasCampanas::clave($detalle['aceptables'][0])];
    }

    private function estadoOferta(int $id): string
    {
        return (string) $this->db->table('ofertas')->where('id', $id)->get()->getRow()->estado;
    }

    // ── IN-61 Aceptar la oferta (CU-52) ──────────────────────────────────────────────────────────────────────────

    public function testAceptarOferta(): void
    {
        [$o, $version, $clave] = $this->ofertaEnElPortal();
        $ruta = "portal/ofertas/{$o['id']}/aceptar";
        $base = ['version_fila' => $version, 'alternativa' => $clave];
        [$importe] = explode('|', $clave);
        $mayor = '501000.00';

        $this->assertRechazos($ruta, $base, [
            'alternativa vacía' => [['alternativa' => ''], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'alternativa ausente' => [['alternativa' => Esperado::AUSENTE], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'alternativa como lista' => [['alternativa' => [$clave]], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'alternativa inexistente' => [['alternativa' => "{$importe}|999"], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'importe mayor que el ofrecido' => [['alternativa' => "{$mayor}|12"], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'alternativa con formato local' => [['alternativa' => str_replace('.', ',', $clave)], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'texto SQL' => [['alternativa' => "{$clave}'; DROP TABLE ofertas;--"], 'alternativa', 'Elija una de las alternativas permitidas: *'],
            'versión anterior' => [['version_fila' => (string) ((int) $version - 1)], '', 'La oferta cambió desde que la abrió: revísela nuevamente.'],
            'versión como lista (H-21)' => [['version_fila' => [$version]], '', 'La oferta cambió desde que la abrió: revísela nuevamente.'],
            'versión con letras' => [['version_fila' => "{$version}x"], '', 'La oferta cambió desde que la abrió: revísela nuevamente.'],
        ], ['solicitudes_credito']);
        $this->assertSame('Vigente', $this->estadoOferta($o['id']), 'Los rechazos no cambian la oferta');

        // Oferta de otro cliente o inexistente: mensaje genérico (RN-01)
        $ajena = $this->fabrica->oferta();
        $this->comoUsuario($o['acceso'], 'Cliente');
        foreach ([$ajena['id'], 999999999] as $id) {
            $this->enviar("portal/ofertas/{$id}/aceptar", $base);
            $this->assertSame('No encontramos la información solicitada entre sus operaciones.', (string) ($_SESSION['error'] ?? ''), (string) $id);
        }
        // La oferta de $o quedó de una campaña finalizada al crear otra (RN-66): A2, la oferta vence sin solicitud
        $antes = $this->ultimoId('solicitudes_credito');
        $this->enviar($ruta, $base);
        $this->assertStringStartsWith('La oferta perdió vigencia porque la campaña finalizó', (string) ($_SESSION['error'] ?? $_SESSION['aviso'] ?? ''));
        $this->assertSame('Vencida', $this->estadoOferta($o['id']));
        $this->assertSame($antes, $this->ultimoId('solicitudes_credito'));

        // Aceptación válida, con espacios en los extremos de la clave
        [$o, $version, $clave] = $this->ofertaEnElPortal();
        $r = $this->enviar("portal/ofertas/{$o['id']}/aceptar", ['version_fila' => " {$version} ", 'alternativa' => " {$clave} "]);
        $solicitud = $this->ultimoId('solicitudes_credito');
        $r->assertRedirectTo(site_url("portal/solicitudes/{$solicitud}"));
        $s = $this->db->table('solicitudes_credito')->where('id', $solicitud)->get()->getRowArray();
        $this->assertSame($o['cliente']['id'], (int) $s['cliente_id']);
        $this->assertSame('Aceptada', $this->estadoOferta($o['id']));
        [$importe] = explode('|', $clave);
        $this->assertValorGuardado($importe, $this->db->table('campanas')->where('id', $o['campana_id'])->get()->getRow()->capital_comprometido, 'capital comprometido');

        // A3: una segunda aceptación no genera otra solicitud
        $this->enviar("portal/ofertas/{$o['id']}/aceptar", ['version_fila' => $version, 'alternativa' => $clave]);
        $this->assertStringStartsWith('Ya aceptó esta oferta', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame($solicitud, $this->ultimoId('solicitudes_credito'));
    }

    /** IN-61 A2: con el cupo de la campaña agotado, la oferta vence sin generar la solicitud. */
    public function testAceptarSinCupo(): void
    {
        $o = $this->fabrica->oferta('500000.00', 12, '1.00');
        $this->comoUsuario($o['acceso'], 'Cliente');
        $detalle = (new CampanaService($this->db))->ofertaParaCliente($o['cliente']['id'], $o['id']);
        $antes = $this->ultimoId('solicitudes_credito');
        $this->enviar("portal/ofertas/{$o['id']}/aceptar", ['version_fila' => (string) $detalle['version_fila'], 'alternativa' => ReglasCampanas::clave($detalle['aceptables'][0])]);
        $this->assertStringStartsWith('La campaña ya no tiene cupo para esta oferta', (string) ($_SESSION['error'] ?? $_SESSION['aviso'] ?? ''));
        $this->assertSame('Vencida', $this->estadoOferta($o['id']));
        $this->assertSame($antes, $this->ultimoId('solicitudes_credito'));
    }

    // ── IN-62 Rechazar la oferta (CU-52 A1) ──────────────────────────────────────────────────────────────────────

    public function testRechazarOferta(): void
    {
        [$o, $version] = $this->ofertaEnElPortal();
        $ruta = "portal/ofertas/{$o['id']}/rechazar";
        foreach (['versión anterior' => (string) ((int) $version - 1), 'versión como lista (H-21)' => [$version], 'versión ausente' => Esperado::AUSENTE] as $caso => $v) {
            $this->enviar($ruta, self::conValor([], 'version_fila', $v));
            $this->assertSame('La oferta cambió desde que la abrió: revísela nuevamente.', (string) ($_SESSION['error'] ?? ''), $caso);
            $this->assertSame('Vigente', $this->estadoOferta($o['id']), $caso);
        }
        $this->assertAceptado($this->enviar($ruta, ['version_fila' => $version]));
        $this->assertSame('Rechazada', $this->estadoOferta($o['id']));
        $this->enviar($ruta, ['version_fila' => (string) ((int) $version + 1)]);
        $this->assertSame('La oferta ya fue respondida o venció: no puede rechazarse.', (string) ($_SESSION['error'] ?? ''));
    }

    // ── IN-63 Consultar campañas (CU-50) ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed}> filtro => valor de cada clase */
    public static function filtrosDeCampanas(): array
    {
        $r = [];
        $clases = [
            'vacío' => '', 'como lista (COM-02)' => ['1'], 'código HTML (COM-03)' => '<b>1</b><script>alert(1)</script>',
            'texto SQL (COM-04)' => "1'); DROP TABLE campanas;--", 'negativo' => '-1', 'decimal' => '1.5',
            'mayor que INT' => '2147483648', 'muy grande' => '99999999999999999999', 'emoji' => '😀',
        ];
        foreach (['numero', 'linea', 'estado', 'desde', 'hasta', 'canal'] as $filtro) {
            foreach ($clases as $clase => $valor) {
                $r["{$filtro} · {$clase}"] = [$filtro, $valor];
            }
        }
        $r['desde · fecha inválida'] = ['desde', '31/02/2026'];
        $r['hasta · fecha anterior a desde'] = ['hasta', '01/01/2000'];

        return $r;
    }

    /**
     * IN-63: cada filtro de la consulta de campañas con cada clase de dato responde la pantalla, sin error 500.
     *
     * @dataProvider filtrosDeCampanas
     */
    public function testConsultarCampanasConCadaFiltro(string $filtro, mixed $valor): void
    {
        $this->comoRol('Gerente');
        $r = $this->pedir('admin/campanas', [$filtro => $valor]);
        $r->assertOK();
        $r->assertSee('Campañas');
    }

    /** IN-63: los filtros válidos acotan el listado; el número de otra campaña no la muestra. */
    public function testConsultarCampanasFiltra(): void
    {
        $o = $this->fabrica->oferta();
        $numero = (string) $this->db->table('campanas')->where('id', $o['campana_id'])->get()->getRow()->numero;
        $this->comoRol('AnalistaCredito');
        $enlace = site_url("admin/campanas/{$o['campana_id']}") . '"';
        foreach ([['numero' => $numero], ['estado' => 'Activa'], ['linea' => (string) $o['linea_id']], ['numero' => " {$numero} ", 'estado' => 'Activa']] as $f) {
            $this->assertStringContainsString($enlace, (string) $this->pedir('admin/campanas', $f)->response()->getBody(), json_encode($f));
        }
        foreach ([['estado' => 'Finalizada'], ['numero' => '999999999'], ['canal' => 'SMS']] as $f) {
            $this->assertStringNotContainsString($enlace, (string) $this->pedir('admin/campanas', $f)->response()->getBody(), json_encode($f));
        }
        $this->pedir("admin/campanas/{$o['campana_id']}")->assertOK();
        $this->pedir("admin/campanas/ofertas/{$o['id']}")->assertOK();
        foreach (['admin/campanas/999999999', 'admin/campanas/ofertas/999999999'] as $ruta) {
            $r = $this->pedir($ruta);
            $this->assertTrue($r->isRedirect(), $ruta);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), $ruta);
        }
    }
}
