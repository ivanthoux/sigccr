<?php

declare(strict_types=1);

namespace Tests\Feature\Copias;

use App\Libraries\Copias\EstadoRestauracion;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-69 y IN-70 · Copias de seguridad (iteración 4, bloque D): solicitar una copia (CU-61), descargarla (CU-62) y
 * confirmar su restauración (CU-63). Plan de pruebas 7.9.2 y Anexo B.18.
 *
 * El destino de las copias es una carpeta temporal de la prueba. La generación, la verificación y la restauración las
 * ejecuta el contenedor de procesos (copias:procesar), que no corre en las pruebas: aquí se prueban la solicitud, la
 * descarga y la confirmación. Nunca se envía una confirmación válida, porque pondría el sistema en mantenimiento.
 *
 * @internal
 */
final class CopiasTest extends IntegracionTestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = WRITEPATH . 'copias_prueba_' . Fabrica::sufijo();
        mkdir($this->directorio, 0o775, true);
        config('Sigccr')->directorioCopias = $this->directorio;
        // Las copias solicitadas por otras pruebas quedan como fallidas: no hay contenedor de procesos (A1)
        $this->db->query("UPDATE dbo.copias_seguridad SET estado = 'Fallida', fecha_fin = SYSDATETIMEOFFSET() WHERE estado IN ('Solicitada', 'EnCurso')");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directorio . '/*') ?: []);
        @rmdir($this->directorio);
        config('Sigccr')->directorioCopias = '/var/opt/sigccr/backups';
        parent::tearDown();
    }

    // ── IN-69 Solicitar una copia (CU-61) ────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosDescripcion(): array
    {
        return self::casosDelCatalogo('IN-69', ['descripcion' => [
            'tipo' => 'TXT', 'obligatorio' => false, 'max' => 300,
            'mensajes' => ['largo' => 'La descripción admite hasta 300 caracteres.'],
        ]]);
    }

    /**
     * IN-69: la descripción con cada clase de dato. Aceptada, la copia queda Solicitada con la descripción.
     *
     * @dataProvider casosDescripcion
     * @param array<string, mixed> $otros
     */
    public function testSolicitarConCadaClaseDeDescripcion(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Administrador');
        $antes = $this->ultimoId('copias_seguridad');
        $r = $this->enviar('admin/copias', self::conValor([], $campo, $valor, $otros));
        if (!$esperado->acepta) {
            $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
            $this->assertSame($antes, $this->ultimoId('copias_seguridad'));

            return;
        }
        $this->assertAceptado($r, "{$campo}: {$esperado}");
        $c = $this->db->table('copias_seguridad')->where('id', $this->ultimoId('copias_seguridad'))->get()->getRowArray();
        $this->assertSame('Solicitada', $c['estado']);
        $this->assertSame('Manual', $c['origen']);
        $this->assertValorGuardado($esperado->guardado, $c['descripcion'], $campo);
    }

    /** IN-69 A1 y A2: copia en curso y destino no disponible. */
    public function testSolicitarConCopiaEnCursoODestinoNoDisponible(): void
    {
        $this->comoRol('Administrador');
        $this->assertAceptado($this->enviar('admin/copias', ['descripcion' => 'Primera']));
        $antes = $this->ultimoId('copias_seguridad');
        $this->enviar('admin/copias', ['descripcion' => 'Segunda']);
        $this->assertSame('Ya hay una copia de seguridad en curso: espere a que finalice para iniciar otra (A1).', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame($antes, $this->ultimoId('copias_seguridad'));

        $this->db->query("UPDATE dbo.copias_seguridad SET estado = 'Fallida' WHERE id = ?", [$antes]);
        config('Sigccr')->directorioCopias = $this->directorio . '/no-existe';
        $this->enviar('admin/copias', ['descripcion' => 'Tercera']);
        $this->assertStringStartsWith('El destino de las copias', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame($antes, $this->ultimoId('copias_seguridad'));
    }

    // ── IN-70 Descargar (CU-62) y confirmar la restauración (CU-63) ──────────────────────────────────────────────

    /** Copia verificada con su archivo en el destino. */
    private function copiaVerificada(string $contenido = 'contenido cifrado de prueba'): int
    {
        $archivo = 'sigccr_prueba_' . Fabrica::sufijo() . '.sccr';
        file_put_contents($this->directorio . '/' . $archivo, $contenido);
        $this->db->table('copias_seguridad')->insert([
            'archivo' => $archivo, 'tamano_bytes' => strlen($contenido), 'origen' => 'Manual', 'hash_verificacion' => hash('sha256', $contenido),
            'estado' => 'Verificada', 'responsable' => 'Prueba de integración', 'incluye_adjuntos' => 0,
        ]);

        return $this->ultimoId('copias_seguridad');
    }

    public function testDescargarCopia(): void
    {
        $this->comoRol('Administrador');
        $id = $this->copiaVerificada();
        $r = $this->enviar("admin/copias/{$id}/descargar");
        $r->assertOK();
        $archivo = (string) $this->db->table('copias_seguridad')->where('id', $id)->get()->getRow()->archivo;
        $this->assertStringContainsString($archivo, $r->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame(1, $this->contar('registros_auditoria', ['accion' => 'copias.descargar', 'objeto_id' => (string) $id, 'resultado' => 'Exitoso']));

        // Inexistente, no verificada y corrupta (A1)
        $this->pedir('admin/copias/999999999');
        $this->assertSame('La copia de seguridad no existe.', (string) ($_SESSION['error'] ?? ''));
        $this->db->query("UPDATE dbo.copias_seguridad SET estado = 'Fallida' WHERE id = ?", [$id]);
        $this->enviar("admin/copias/{$id}/descargar");
        $this->assertStringStartsWith('La copia no está disponible para descargar', (string) ($_SESSION['error'] ?? ''));
        $corrupta = $this->copiaVerificada();
        file_put_contents($this->directorio . '/' . $this->db->table('copias_seguridad')->where('id', $corrupta)->get()->getRow()->archivo, 'x', FILE_APPEND);
        $this->enviar("admin/copias/{$corrupta}/descargar");
        $this->assertStringStartsWith('La copia está corrupta', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame('Corrupta', $this->db->table('copias_seguridad')->where('id', $corrupta)->get()->getRow()->estado);
    }

    /** IN-70 A2: sin la palabra RESTAURAR la restauración no se pide y el sistema sigue operativo. */
    public function testConfirmacionDeLaRestauracion(): void
    {
        $this->comoRol('Administrador');
        $id = $this->copiaVerificada();
        $this->pedir("admin/copias/{$id}/restaurar")->assertOK();
        $mensaje = 'Para confirmar, escriba RESTAURAR (A2).';
        $casos = [
            'vacía' => '', 'ausente' => Esperado::AUSENTE, 'si' => 'si', 'como lista' => ['RESTAURAR'], 'otra palabra' => 'RESTAURA',
            'con texto adicional' => 'RESTAURAR YA', 'código HTML' => '<b>RESTAURAR</b>', 'con una letra con tilde' => 'RESTAURÁR',
        ];
        foreach ($casos as $caso => $valor) {
            $antes = $this->contar('registros_auditoria', ['accion' => 'copias.solicitar-restauracion']);
            $r = $this->enviar("admin/copias/{$id}/restaurar", self::conValor(['sin_respaldo' => '1'], 'confirmacion', $valor));
            $this->assertCampoRechazado($r, 'confirmacion', $mensaje, $caso);
            $this->assertSame($antes, $this->contar('registros_auditoria', ['accion' => 'copias.solicitar-restauracion']), $caso);
            $this->assertFalse(EstadoRestauracion::predeterminado()->activa(), "{$caso}: el sistema no entra en mantenimiento");
        }

        // Una copia no verificada no se ofrece ni se restaura (A1)
        $this->db->query("UPDATE dbo.copias_seguridad SET estado = 'Corrupta' WHERE id = ?", [$id]);
        $this->pedir("admin/copias/{$id}/restaurar");
        $this->assertStringStartsWith('La copia está corrupta, incompleta o no disponible', (string) ($_SESSION['error'] ?? ''));
        $this->enviar("admin/copias/{$id}/restaurar", ['confirmacion' => 'si']);
        $this->assertStringStartsWith('La copia está corrupta, incompleta o no disponible', (string) ($_SESSION['error'] ?? ''));
    }
}
