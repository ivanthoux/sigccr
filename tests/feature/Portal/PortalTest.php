<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\AutorizacionService;
use App\Services\Clientes\ClienteService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-53 a IN-60 · Portal de autogestión y pre-registros (iteración 4, bloque A): pre-registro (CU-104), verificación
 * de su correo, alta y descarte desde el panel (CU-11), preferencias de comunicación (CU-98), cambio de correo
 * (CU-75), pago electrónico desde el portal (CU-49), comprobantes (CU-48) y consultas propias del cliente (CU-45 a
 * CU-47, CU-43 A5, RN-01). Plan de pruebas 7.9.2 y Anexo B.15.
 *
 * El portal toma el cliente de la sesión, nunca de la petición: un identificador de otro cliente o inexistente vuelve
 * al listado con el mismo mensaje genérico y queda auditado (portal.acceso-denegado).
 *
 * @internal
 */
final class PortalTest extends IntegracionTestCase
{
    private const AJENO = 'No encontramos la información solicitada entre sus operaciones.';

    // ── IN-53 Pre-registro (CU-104) ──────────────────────────────────────────────────────────────────────────────

    /** @return array<string, string> datos válidos de una persona que todavía no es cliente */
    private static function datosPreRegistro(): array
    {
        $p = Fabrica::personaHumana();

        return [
            'nombre' => 'Sofía', 'apellido' => 'Benítez', 'tipo_documento' => 'DNI', 'nro_documento' => (string) $p['dni'],
            'cuit_cuil' => $p['cuil'], 'correo' => 'pre.' . Fabrica::sufijo() . '@prueba.local', 'telefono' => '3764441234', 'acepta_politica' => '1',
        ];
    }

    /** @return array<string, array<string, mixed>> campos del pre-registro (Anexo B.15, IN-53) */
    public static function camposPreRegistro(): array
    {
        $nombre = static fn (string $el): array => [
            'tipo' => 'NOM', 'obligatorio' => true, 'max' => 80,
            'mensajes' => [
                'obligatorio' => "Complete {$el}.", 'formato' => ucfirst($el) . ' solo admite letras, espacios, apóstrofos y guiones.',
                'largo' => ucfirst($el) . ' admite hasta 80 caracteres.',
            ],
        ];
        $pasaporte = ['tipo_documento' => 'Pasaporte', 'nro_documento' => static fn (): string => 'PP' . Fabrica::entre(1000000, 9999999)];

        return [
            'nombre' => $nombre('el nombre'),
            'apellido' => $nombre('el apellido'),
            'tipo_documento' => [
                // El formulario siempre envía un tipo; sin él se toma DNI, el primero de la lista
                'tipo' => 'SEL', 'obligatorio' => false, 'vacio_guardado' => 'DNI', 'valores' => ['DNI', 'LC', 'LE', 'Pasaporte'],
                'mensajes' => ['formato' => 'Seleccione un tipo de documento válido.'],
            ],
            'nro_documento' => [
                'tipo' => 'DOC', 'obligatorio' => true, 'admite_pasaporte' => true,
                'mensajes' => ['obligatorio' => 'Complete el número de documento.', 'formato' => 'El documento debe tener entre 6 y 8 dígitos, sin puntos.'],
                'otros' => ['tipo_documento' => 'LE'],
            ],
            'cuit_cuil' => [
                'tipo' => 'CUI', 'obligatorio' => true, 'persona' => 'PersonaHumana',
                'mensajes' => ['obligatorio' => 'Complete el CUIL.', 'formato' => 'El CUIT o CUIL debe tener 11 dígitos.'],
                'otros' => $pasaporte,
            ],
            'correo' => [
                'tipo' => 'COR', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => 'Complete el correo electrónico.', 'formato' => 'Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.'],
            ],
            'telefono' => ['tipo' => 'TEL', 'obligatorio' => false, 'mensajes' => ['formato' => 'Ingrese un teléfono válido (solo números, espacios, guiones o +).']],
            'acepta_politica' => ['tipo' => 'CHK', 'mensajes' => ['obligatorio' => 'Para continuar, acepte la política de tratamiento de datos personales.']],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosPreRegistro(): array
    {
        return self::casosDelCatalogo('IN-53', self::camposPreRegistro());
    }

    /**
     * IN-53: cada campo del pre-registro con cada clase de dato, sin sesión y por HTTP.
     *
     * @dataProvider casosPreRegistro
     * @param array<string, mixed> $otros
     */
    public function testPreRegistroConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->sinSesion();
        $antes = $this->ultimoId('pre_registros');
        $r = $this->enviar('portal/pre-registro', self::conValor(self::datosPreRegistro(), $campo, $valor, $otros));

        if (!$esperado->acepta) {
            $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
            $this->assertSame($antes, $this->ultimoId('pre_registros'), 'Un rechazo no debe crear el pre-registro');

            return;
        }
        $this->assertSame(200, $r->response()->getStatusCode(), "{$campo}: " . json_encode($_SESSION['errores'] ?? $_SESSION['error'] ?? null, JSON_UNESCAPED_UNICODE));
        $r->assertSee('Verifique su correo');
        $id = $this->ultimoId('pre_registros');
        $this->assertGreaterThan($antes, $id, 'Debe crearse el pre-registro');
        $fila = $this->db->table('pre_registros')->where('id', $id)->get()->getRowArray();
        if (array_key_exists($campo, $fila)) {
            $this->assertValorGuardado($esperado->guardado, $fila[$campo], $campo);
        }
        $this->assertSame('PendienteVerificacion', $fila['estado']);
        $this->assertNotNull($fila['fecha_politica'], 'RN-64: se registra la aceptación de la política');
    }

    /** IN-53 A1 a A3: persona que ya es cliente, correo de otro cliente, CUIL de otro DNI y reinicio sin verificar. */
    public function testPreRegistroReglas(): void
    {
        $this->sinSesion();
        $cliente = $this->fabrica->cliente();
        $base = self::datosPreRegistro();

        $this->assertRechazos('portal/pre-registro', $base, [
            'A1 documento de un cliente' => [['nro_documento' => (string) $cliente['datos']['nro_documento'], 'cuit_cuil' => (string) $cliente['datos']['cuit_cuil']], '', 'Usted ya es cliente de la entidad*'],
            'A1 CUIL de un cliente con pasaporte' => [['tipo_documento' => 'Pasaporte', 'nro_documento' => 'PP' . Fabrica::entre(1000000, 9999999), 'cuit_cuil' => (string) $cliente['datos']['cuit_cuil']], '', 'Usted ya es cliente de la entidad*'],
            'A2 correo de otro cliente' => [['correo' => mb_strtoupper((string) $cliente['datos']['correo'])], 'correo', 'Ese correo ya está registrado para otro cliente: ingrese otro.'],
            'CUIL válido de otro DNI' => [['cuit_cuil' => Fabrica::personaHumana()['cuil']], 'cuit_cuil', 'El CUIL no corresponde al número de documento.'],
            'A4 política sin aceptar' => [['acepta_politica' => Esperado::AUSENTE], 'acepta_politica', 'Para continuar, acepte la política de tratamiento de datos personales.'],
        ], ['pre_registros', 'comunicaciones']);

        // A3: el mismo pre-registro sin verificar se reinicia con un enlace nuevo, sin otra fila
        $this->assertSame(200, $this->enviar('portal/pre-registro', $base)->response()->getStatusCode());
        $id = $this->ultimoId('pre_registros');
        $hash = $this->db->table('pre_registros')->where('id', $id)->get()->getRow()->hash_codigo;
        $this->assertSame(200, $this->enviar('portal/pre-registro', ['nombre' => 'Sofía Inés'] + $base)->response()->getStatusCode());
        $this->assertSame($id, $this->ultimoId('pre_registros'), 'A3: se reinicia el mismo pre-registro');
        $fila = $this->db->table('pre_registros')->where('id', $id)->get()->getRow();
        $this->assertNotSame($hash, $fila->hash_codigo, 'A3: el enlace anterior deja de valer');
        $this->assertSame('Sofía Inés', $fila->nombre);

        // Ya verificado: no se reinicia
        $this->db->table('pre_registros')->where('id', $id)->update(['estado' => 'PendienteValidacion', 'hash_codigo' => null]);
        $this->enviar('portal/pre-registro', $base);
        $this->assertStringStartsWith('Ya tenemos un pre-registro verificado', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame($id, $this->ultimoId('pre_registros'));
    }

    // ── IN-54 Verificar el correo del pre-registro ───────────────────────────────────────────────────────────────

    /** Pre-registro pendiente de verificación con un código conocido. @return array{id: int, codigo: string} */
    private function preRegistroConCodigo(): array
    {
        $this->sinSesion();
        $this->assertSame(200, $this->enviar('portal/pre-registro', self::datosPreRegistro())->response()->getStatusCode());
        $id = $this->ultimoId('pre_registros');
        $codigo = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->table('pre_registros')->where('id', $id)->update(['hash_codigo' => hash('sha256', $codigo)]);

        return ['id' => $id, 'codigo' => $codigo];
    }

    /** TOK-01 a TOK-04 con el enlace del pre-registro: alterado, corto, largo, vencido y usado (RN-03). */
    public function testVerificarPreRegistro(): void
    {
        $p = $this->preRegistroConCodigo();
        $invalido = 'El enlace de verificación expiró o ya no es válido.';
        $codigos = [
            'alterado' => substr($p['codigo'], 0, -1) . ($p['codigo'][-1] === 'A' ? 'B' : 'A'),
            'de 1 carácter' => 'x',
            'de 500 caracteres' => str_repeat('a', 500),
        ];
        foreach ($codigos as $caso => $codigo) {
            $this->pedir('portal/pre-registro/verificar/' . rawurlencode($codigo))->assertOK();
            $this->enviar('portal/pre-registro/verificar/' . rawurlencode($codigo))->assertSee($invalido);
            $this->assertSame('PendienteVerificacion', $this->db->table('pre_registros')->where('id', $p['id'])->get()->getRow()->estado, $caso);
        }

        // Vencido
        $this->db->query('UPDATE dbo.pre_registros SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE id = ?', [$p['id']]);
        $this->enviar("portal/pre-registro/verificar/{$p['codigo']}")->assertSee($invalido);
        $this->db->query('UPDATE dbo.pre_registros SET vence = DATEADD(minute, 30, SYSDATETIMEOFFSET()) WHERE id = ?', [$p['id']]);

        // Vigente: queda pendiente de validación con la alerta; el mismo enlace ya no vale (uso único)
        $this->enviar("portal/pre-registro/verificar/{$p['codigo']}")->assertSee('Su correo quedó verificado.');
        $fila = $this->db->table('pre_registros')->where('id', $p['id'])->get()->getRow();
        $this->assertSame('PendienteValidacion', $fila->estado);
        $this->assertNull($fila->hash_codigo);
        $this->assertSame(1, $this->contar('alertas', ['objeto_tipo' => 'pre_registros', 'objeto_id' => (string) $p['id']]));
        $this->enviar("portal/pre-registro/verificar/{$p['codigo']}")->assertSee($invalido);
    }

    // ── IN-55 Completar el alta y descartar el pre-registro (CU-11) ──────────────────────────────────────────────

    /** Pre-registro verificado, pendiente de validación. */
    private function preRegistroVerificado(): int
    {
        $p = $this->preRegistroConCodigo();
        $this->enviar("portal/pre-registro/verificar/{$p['codigo']}");

        return $p['id'];
    }

    /** @return array<string, array{0: mixed, 1: Esperado, 2: array<string, mixed>}> */
    public static function casosDescarte(): array
    {
        $r = [];
        foreach (self::casosDelCatalogo('IN-55', ['motivo' => ['tipo' => 'MOT', 'obligatorio' => true, 'max' => 500]]) as $nombre => [, $valor, $esperado, $otros]) {
            $r[$nombre] = [$valor, $esperado, $otros];
        }

        return $r;
    }

    /**
     * IN-55: motivo del descarte con cada clase de dato (hasta 500 caracteres, el largo de la columna).
     *
     * @dataProvider casosDescarte
     * @param array<string, mixed> $otros
     */
    public function testDescartarConCadaClaseDeMotivo(mixed $valor, Esperado $esperado, array $otros): void
    {
        $id = $this->preRegistroVerificado();
        $this->comoRol('AnalistaCredito');
        $r = $this->enviar("admin/pre-registros/{$id}/descartar", self::conValor(['version_fila' => (string) $this->version($id)], 'motivo', $valor, $otros));

        $fila = $this->db->table('pre_registros')->where('id', $id)->get()->getRowArray();
        if ($esperado->acepta) {
            $this->assertAceptado($r, "motivo: {$esperado}");
            $this->assertSame('Descartado', $fila['estado']);
            $this->assertValorGuardado($esperado->guardado, $fila['motivo_descarte'], 'motivo');

            return;
        }
        $this->assertCampoRechazado($r, 'motivo', $esperado->mensaje, "motivo: {$esperado}");
        $this->assertSame('PendienteValidacion', $fila['estado']);
    }

    private function version(int $preRegistroId): int
    {
        return (int) $this->db->table('pre_registros')->where('id', $preRegistroId)->get()->getRow()->version_fila;
    }

    /** IN-55: versión de fila, pre-registro inexistente o ya descartado, y alta desde el pre-registro. */
    public function testDescartarYCompletarControles(): void
    {
        $id = $this->preRegistroVerificado();
        $this->comoRol('AnalistaCredito');
        $motivo = 'No se presentó a verificar su identidad.';

        $v = (string) $this->version($id);
        foreach (['versión anterior' => (string) ((int) $v - 1), 'versión como lista (H-21)' => [$v], 'versión con letras' => "{$v}x"] as $caso => $version) {
            $this->enviar("admin/pre-registros/{$id}/descartar", ['version_fila' => $version, 'motivo' => $motivo]);
            $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), $caso);
            $this->assertSame('PendienteValidacion', $this->db->table('pre_registros')->where('id', $id)->get()->getRow()->estado, $caso);
        }
        $this->enviar('admin/pre-registros/999999999/descartar', ['version_fila' => '1', 'motivo' => $motivo]);
        $this->assertSame('Solo se descarta un pre-registro pendiente.', (string) ($_SESSION['error'] ?? ''));

        // Alta desde el pre-registro (CU-11 paso 2 y CU-104 paso 7): el pre-registro queda completado con su cliente
        $this->pedir('admin/clientes/nuevo', ['pre' => (string) $id])->assertOK();
        foreach (['abc', '1.5', '99999999999'] as $pre) {
            $this->pedir('admin/clientes/nuevo', ['pre' => $pre]);
        }
        $servicio = new ClienteService($this->db);
        $pre = $this->db->table('pre_registros')->where('id', $id)->get()->getRowArray();
        $datos = ['pre_registro_id' => (string) $id, 'nro_documento' => $pre['nro_documento'], 'cuit_cuil' => $pre['cuit_cuil'], 'correo' => $pre['correo']] + $this->fabrica->datosCliente();
        $clienteId = $servicio->registrar($datos, Fabrica::archivosIdentificacion(), $this->fabrica->responsable());
        $pre = $this->db->table('pre_registros')->where('id', $id)->get()->getRowArray();
        $this->assertSame('Completado', $pre['estado']);
        $this->assertSame($clienteId, (int) $pre['cliente_id']);
        $this->assertSame(0, $this->contar('alertas', ['objeto_tipo' => 'pre_registros', 'objeto_id' => (string) $id, 'estado' => 'Pendiente']));

        // Completado: no se descarta ni vuelve a completarse
        $this->enviar("admin/pre-registros/{$id}/descartar", ['version_fila' => (string) $this->version($id), 'motivo' => $motivo]);
        $this->assertSame('Solo se descarta un pre-registro pendiente.', (string) ($_SESSION['error'] ?? ''));
        try {
            $servicio->registrar(['pre_registro_id' => (string) $id] + $this->fabrica->datosCliente(), Fabrica::archivosIdentificacion(), $this->fabrica->responsable());
            $this->fail('Un pre-registro completado no vuelve a completarse');
        } catch (ReglaNegocioException $e) {
            $this->assertStringStartsWith('El pre-registro ya fue completado o descartado', $e->getMessage());
        }
    }

    // ── Sesión del portal ────────────────────────────────────────────────────────────────────────────────────────

    /** Cliente con un crédito liquidado y su portal habilitado, con la sesión del portal iniciada. @return array<string, mixed> */
    private function clienteConCredito(): array
    {
        $credito = $this->fabrica->credito();
        $acceso = $this->fabrica->habilitarPortal($credito['cliente']['id']);
        $this->comoUsuario($acceso, 'Cliente');

        return $credito + ['acceso' => $acceso];
    }

    // ── IN-56 Preferencias de comunicación (CU-98) ───────────────────────────────────────────────────────────────

    public function testPreferenciasDeComunicacion(): void
    {
        $c = $this->fabrica->clienteConAcceso();
        $this->comoUsuario($c, 'Cliente');
        $consentimiento = fn (): mixed => $this->db->query("SELECT TOP 1 otorgado FROM dbo.consentimientos WHERE cliente_id = ? AND tipo = 'ComunicacionesComerciales' ORDER BY id DESC", [$c['id']])->getRow()?->otorgado;
        $antes = $this->ultimoId('consentimientos');

        // Un valor fuera de la lista no cambia el consentimiento (H-27)
        foreach (['vacío' => '', 'ausente' => Esperado::AUSENTE, 'inexistente' => 'Inexistente', 'otras mayúsculas' => 'otorgar', 'como lista' => ['Otorgar']] as $caso => $valor) {
            $r = $this->enviar('portal/perfil/preferencias', self::conValor([], 'comunicaciones', $valor));
            $this->assertTrue($r->isRedirect(), $caso);
            $this->assertSame('Seleccione otorgar o revocar el consentimiento.', (string) ($_SESSION['error'] ?? ''), $caso);
            $this->assertSame($antes, $this->ultimoId('consentimientos'), "{$caso}: no debe registrarse un consentimiento");
        }

        // Sin consentimiento previo, revocar no registra nada (A1)
        $this->enviar('portal/perfil/preferencias', ['comunicaciones' => 'Revocar']);
        $this->assertSame('El consentimiento ya está revocado: no hay cambios por registrar.', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame($antes, $this->ultimoId('consentimientos'));

        $this->assertAceptado($this->enviar('portal/perfil/preferencias', ['comunicaciones' => ' Otorgar ']));
        $this->assertSame('1', (string) $consentimiento());
        $this->assertAceptado($this->enviar('portal/perfil/preferencias', ['comunicaciones' => 'Revocar']));
        $this->assertSame('0', (string) $consentimiento());
        $this->assertSame('Portal', $this->db->query("SELECT TOP 1 medio FROM dbo.consentimientos WHERE cliente_id = ? AND tipo = 'ComunicacionesComerciales' ORDER BY id DESC", [$c['id']])->getRow()->medio);
    }

    // ── IN-57 Cambio de correo por autogestión (CU-75) ───────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosCorreoNuevo(): array
    {
        return self::casosDelCatalogo('IN-57', ['correo_nuevo' => [
            'tipo' => 'COR', 'obligatorio' => true,
            'mensajes' => ['obligatorio' => 'Ingrese el nuevo correo electrónico.', 'formato' => 'Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.'],
        ]]);
    }

    /**
     * IN-57: el correo nuevo con cada clase de dato. Aceptado, queda una autorización para el correo nuevo y el correo
     * de la cuenta no cambia hasta la confirmación.
     *
     * @dataProvider casosCorreoNuevo
     * @param array<string, mixed> $otros
     */
    public function testCambioDeCorreoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $c = $this->fabrica->clienteConAcceso();
        $this->comoUsuario($c, 'Cliente');
        $antes = $this->ultimoId('autorizaciones_temporales');
        $r = $this->enviar('portal/perfil/correo', self::conValor([], $campo, $valor, $otros));

        if (!$esperado->acepta) {
            $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
            $this->assertSame($antes, $this->ultimoId('autorizaciones_temporales'));

            return;
        }
        $this->assertAceptado($r, "{$campo}: {$esperado}");
        $a = $this->db->table('autorizaciones_temporales')->where('id', $this->ultimoId('autorizaciones_temporales'))->get()->getRowArray();
        $this->assertSame('CambioCorreo', $a['proposito']);
        $this->assertValorGuardado($esperado->guardado, $a['correo_destino'], 'correo_destino');
        $this->assertSame($c['correo'], $this->db->table('cuentas_acceso')->where('id', $c['cuenta_id'])->get()->getRow()->correo, 'El correo no cambia hasta confirmarlo');
    }

    /** IN-57 A1 y pasos 6 a 9: igual al actual, de otro cliente, enlaces inválidos y confirmación completa. */
    public function testCambioDeCorreoReglasYConfirmacion(): void
    {
        $otro = $this->fabrica->clienteConAcceso();
        $c = $this->fabrica->clienteConAcceso();
        $this->comoUsuario($c, 'Cliente');
        $this->assertRechazos('portal/perfil/correo', [], [
            'igual al actual' => [['correo_nuevo' => mb_strtoupper($c['correo'])], 'correo_nuevo', 'El nuevo correo es igual al actual.'],
            'de otro cliente (A1)' => [['correo_nuevo' => $otro['correo']], 'correo_nuevo', 'Ese correo ya está registrado para otro cliente: ingrese otro.'],
        ], ['autorizaciones_temporales']);

        $nuevo = 'nuevo.' . Fabrica::sufijo() . '@prueba.local';
        $autorizaciones = new AutorizacionService($this->db);
        $codigo = $autorizaciones->emitirSinComunicacion($c['cuenta_id'], 'CambioCorreo', $nuevo);
        $this->sinSesion();
        $invalido = 'El enlace para confirmar el cambio de correo expiró o ya no es válido';
        foreach (['alterado' => substr($codigo, 0, -1) . ($codigo[-1] === 'A' ? 'B' : 'A'), 'de 1 carácter' => 'x', 'de 500 caracteres' => str_repeat('a', 500)] as $caso => $malo) {
            $this->pedir("portal/cambio-correo/{$malo}")->assertOK();
            $this->enviar("portal/cambio-correo/{$malo}")->assertSee($invalido);
        }
        // Un enlace de otro propósito (recuperación) no confirma el cambio
        $recuperacion = $autorizaciones->emitirSinComunicacion($c['cuenta_id'], 'Recuperacion', $c['correo']);
        $this->enviar("portal/cambio-correo/{$recuperacion}")->assertSee($invalido);

        $this->enviar("portal/cambio-correo/{$codigo}")->assertSee('Cambio confirmado.');
        $this->enviar("portal/cambio-correo/{$codigo}")->assertSee($invalido);
        $this->assertSame($c['correo'], $this->db->table('cuentas_acceso')->where('id', $c['cuenta_id'])->get()->getRow()->correo, 'Hasta verificar el nuevo, el correo no cambia');
        $this->assertNotNull($autorizaciones->buscarVigente($recuperacion, ['Recuperacion'], false), 'Hasta verificar el nuevo, la recuperación sigue vigente');

        // Pasos 8 y 9: la verificación del correo nuevo completa el cambio y deja sin efecto la recuperación del anterior (RN-58)
        $verificacion = $autorizaciones->emitirSinComunicacion($c['cuenta_id'], 'VerificacionCorreo', $nuevo);
        $this->enviar("portal/verificar-correo/{$verificacion}")->assertRedirectTo(site_url('portal/login'));
        $this->assertSame($nuevo, $this->db->table('cuentas_acceso')->where('id', $c['cuenta_id'])->get()->getRow()->correo);
        $this->assertNull($autorizaciones->buscarVigente($recuperacion, ['Recuperacion'], false), 'RN-58: las autorizaciones del correo anterior quedan sin efecto');
    }

    // ── IN-58 Pago electrónico desde el portal (CU-49) ───────────────────────────────────────────────────────────

    public function testPagoElectronicoDesdeElPortal(): void
    {
        $ajeno = $this->fabrica->credito();
        $c = $this->clienteConCredito();
        $base = ['cuotas' => [$c['id'] => ['1']]];

        $this->assertRechazos('portal/pagar', $base, [
            'sin cuotas' => [['cuotas' => []], 'seleccion', 'Seleccione al menos una cuota.'],
            'cuotas ausentes' => [['cuotas' => Esperado::AUSENTE], 'seleccion', 'Seleccione al menos una cuota.'],
            'cuotas como texto' => [['cuotas' => 'x'], 'seleccion', null],
            'cuota salteada (RN-29)' => [['cuotas' => [$c['id'] => ['2']]], 'seleccion', 'Crédito *'],
            'número de cuota con letras' => [['cuotas' => [$c['id'] => ['1x']]], 'seleccion', null],
            'número de cuota como lista' => [['cuotas' => [$c['id'] => [['1']]]], 'seleccion', null],
            'crédito de otro cliente' => [['cuotas' => [$ajeno['id'] => ['1']]], 'seleccion', null],
            'crédito inexistente' => [['cuotas' => [999999999 => ['1']]], 'seleccion', null],
            'crédito mayor que INT' => [['cuotas' => ['99999999999' => ['1']]], 'seleccion', null],
            'crédito con letras' => [['cuotas' => [$c['id'] . 'x' => ['1']]], 'seleccion', null],
        ], ['ordenes_pago_electronico']);

        $r = $this->enviar('portal/pagar', $base);
        $orden = $this->ultimoId('ordenes_pago_electronico');
        $r->assertRedirectTo(site_url("portal/pagar/ordenes/{$orden}"));
        $o = $this->db->table('ordenes_pago_electronico')->where('id', $orden)->get()->getRowArray();
        $this->assertSame($c['cliente']['id'], (int) $o['cliente_id']);
        $this->assertSame('Pendiente', substr((string) $o['estado'], 0, 9), 'La cuota sigue pendiente hasta la confirmación');

        // A8: la misma cuota vuelve a la orden vigente, sin otra
        $this->enviar('portal/pagar', $base)->assertRedirectTo(site_url("portal/pagar/ordenes/{$orden}"));
        $this->assertSame($orden, $this->ultimoId('ordenes_pago_electronico'));
    }

    // ── IN-59 y IN-60 Consultas propias del cliente (RN-01) y comprobantes (CU-48) ───────────────────────────────

    /** @return array<string, array{0: string, 1: string}> ruta del portal con el identificador => tabla auditada */
    public static function consultasConIdentificador(): array
    {
        return [
            'solicitud' => ['portal/solicitudes/%d', 'solicitudes'],
            'crédito' => ['portal/estado-cuenta/creditos/%d', 'creditos'],
            'cuota de un crédito' => ['portal/estado-cuenta/creditos/%d/cuotas/1', 'creditos'],
            'comprobante' => ['portal/comprobantes/%d', 'cobros'],
            'orden de pago' => ['portal/pagar/ordenes/%d', 'ordenes_pago_electronico'],
            'propuesta de refinanciación' => ['portal/propuestas/refinanciaciones/%d', 'propuestas_refinanciacion'],
            'oferta' => ['portal/ofertas/%d', 'ofertas'],
        ];
    }

    /**
     * IN-60: con un identificador inexistente o de otro cliente, la consulta vuelve con el mensaje
     * genérico, sin revelar si existe, y deja el registro portal.acceso-denegado.
     *
     * @dataProvider consultasConIdentificador
     */
    public function testConsultaDeObjetoAjeno(string $ruta, string $tabla): void
    {
        $ajeno = $this->fabrica->credito();
        $this->clienteConCredito();
        $idsAjenos = ['solicitudes' => $ajeno['solicitud_id'], 'creditos' => $ajeno['id']];
        $casos = ['inexistente' => 999999999];
        if (isset($idsAjenos[$tabla])) {
            $casos['de otro cliente'] = $idsAjenos[$tabla];
        }
        foreach ($casos as $caso => $id) {
            $antes = $this->contar('registros_auditoria', ['accion' => 'portal.acceso-denegado']);
            $r = $this->pedir(str_replace('%d', (string) $id, $ruta));
            $this->assertTrue($r->isRedirect(), "{$caso}: vuelve al listado");
            $this->assertSame(self::AJENO, (string) ($_SESSION['error'] ?? ''), $caso);
            $this->assertSame($antes + 1, $this->contar('registros_auditoria', ['accion' => 'portal.acceso-denegado']), "{$caso}: se audita");
        }
        // Más de 9 dígitos: la ruta no existe (H-28)
        foreach (['2147483648', '99999999999999999999'] as $id) {
            try {
                $this->assertSame(404, $this->pedir(str_replace('%d', $id, $ruta))->response()->getStatusCode(), $id);
            } catch (PageNotFoundException $e) {
                $this->assertSame(404, $e->getCode(), $id);
            }
        }
    }

    /** IN-59: la descarga del comprobante propio cuenta la reemisión; la de uno ajeno o inexistente, el mensaje genérico. */
    public function testDescargarComprobante(): void
    {
        $cajero = $this->comoRol('Cajero');
        $this->fabrica->cajaAbierta($cajero['id']);
        $ajeno = $this->fabrica->credito();
        $c = $this->fabrica->credito();
        foreach ([$ajeno, $c] as $credito) {
            $total = \App\Libraries\Caja\CobroCuotas::total($credito['cuotas'][0])->toFixed(2);
            $this->assertAceptado($this->enviar('admin/cobros', [
                'cliente_id' => (string) $credito['cliente']['id'], 'cuotas' => [$credito['id'] => ['1']], 'importe' => '',
                'medios' => [['medio' => 'Transferencia', 'importe' => str_replace('.', ',', $total), 'numero_operacion' => 'OP-' . strtoupper(Fabrica::sufijo())]],
            ]));
        }
        $cobroPropio = $this->ultimoId('cobros');
        $cobroAjeno = $cobroPropio - 1;
        $this->comoUsuario($this->fabrica->habilitarPortal($c['cliente']['id']), 'Cliente');

        foreach (['ajeno' => $cobroAjeno, 'inexistente' => 999999999] as $caso => $id) {
            $r = $this->enviar("portal/comprobantes/{$id}/descargar");
            $this->assertTrue($r->isRedirect(), $caso);
            $this->assertSame(self::AJENO, (string) ($_SESSION['error'] ?? ''), $caso);
        }
        $reemisiones = fn (int $cobro = 0): int => (int) $this->db->table('comprobantes_pago')->where('cobro_id', $cobro ?: $cobroPropio)->get()->getRow()->cantidad_reemisiones;
        $this->assertSame(0, $reemisiones());
        $r = $this->enviar("portal/comprobantes/{$cobroPropio}/descargar");
        $r->assertOK();
        $this->assertSame(1, $reemisiones());
        $this->assertSame(0, $reemisiones($cobroAjeno));
    }

    // ── IN-C01 e IN-C03 del portal ───────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string}> «VERBO ruta» => [verbo, ruta con ids] de las rutas con sesión del portal */
    public static function rutasConSesion(): array
    {
        $coleccion = service('routes')->loadRoutes();
        $r = [];
        foreach (['GET', 'POST'] as $verbo) {
            foreach (array_keys($coleccion->getRoutes($verbo, false)) as $ruta) {
                if (!str_starts_with($ruta, 'portal') || !in_array('auth:portal', $coleccion->getFiltersForRoute($ruta, $verbo), true)) {
                    continue;
                }
                $r["{$verbo} {$ruta}"] = [$verbo, str_replace(['([0-9]{1,9})', '([0-9]+)', '(:num)', '([^/]+)', '(:segment)'], ['999999999', '999999999', '999999999', 'x', 'x'], $ruta)];
            }
        }

        return $r;
    }

    /**
     * IN-C01 e IN-C03 (RN-08): sin sesión o con una sesión del panel, cada ruta del portal lleva al inicio de sesión
     * del portal y no ejecuta la operación.
     *
     * @dataProvider rutasConSesion
     */
    public function testRutaDelPortalSinSesionDeCliente(string $verbo, string $ruta): void
    {
        $this->assertGreaterThan(15, count(self::rutasConSesion()), 'Deben encontrarse las rutas del portal');
        foreach (['sin sesión' => null, 'con sesión del panel' => 'Gerente'] as $caso => $rol) {
            $rol === null ? $this->sinSesion() : $this->comoRol($rol);
            $antes = $this->ultimoId('registros_auditoria');
            $r = $verbo === 'GET' ? $this->pedir($ruta) : $this->enviar($ruta);
            $r->assertRedirectTo(site_url('portal/login'));
            $this->assertSame(0, $this->db->query("SELECT COUNT(*) AS n FROM dbo.registros_auditoria WHERE id > ? AND accion LIKE 'portal.consultar%'", [$antes])->getRow()->n, $caso);
        }
    }
}
