<?php

declare(strict_types=1);

namespace Tests\Feature\Clientes;

use App\Exceptions\ConflictoConcurrenciaException;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Clientes\ClienteService;
use CodeIgniter\I18n\Time;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-09 a IN-14 · Clientes (CU-11 a CU-15, CU-54). Plan de pruebas 7.9.2 y Anexo B.4.
 *
 * El registro (IN-09, IN-10) exige adjuntar el documento de identidad (RN-11), y PHPUnit no puede simular la subida
 * de un archivo por HTTP (is_uploaded_file() es siempre falso). Por eso las clases de datos de sus campos se ejecutan
 * contra ClienteService::registrar, que es la operación que llama el controlador, con la misma normalización,
 * validación y transacción; la petición HTTP se controla con IN-C01 a IN-C05 (ControlesComunesTest). La modificación,
 * la desactivación, la búsqueda y la descarga se prueban por HTTP.
 *
 * @internal
 */
final class ClientesTest extends IntegracionTestCase
{
    /** @return array<string, array<string, mixed>> campos de la persona humana (Anexo B.4, IN-09) */
    public static function camposPersonaHumana(): array
    {
        $nombre = static fn (string $el): array => [
            'tipo' => 'NOM', 'obligatorio' => true, 'max' => 80,
            'mensajes' => [
                'obligatorio' => "Complete {$el}.", 'formato' => ucfirst($el) . ' solo admite letras, espacios, apóstrofos y guiones.',
                'largo' => ucfirst($el) . ' admite hasta 80 caracteres.',
            ],
        ];
        $texto = static fn (string $el, int $max, bool $obligatorio, ?string $obligatorioMensaje = null): array => [
            'tipo' => 'TXT', 'obligatorio' => $obligatorio, 'max' => $max,
            'mensajes' => ['obligatorio' => $obligatorioMensaje ?? "Complete {$el}.", 'largo' => ucfirst($el) . " admite hasta {$max} caracteres."],
        ];
        // Un pasaporte distinto en cada prueba: el documento es único entre los clientes
        $pasaporte = ['tipo_documento' => 'Pasaporte', 'nro_documento' => static fn (): string => 'PP' . Fabrica::entre(1000000, 9999999)];

        return [
            'nombre' => $nombre('el nombre'),
            'apellido' => $nombre('el apellido'),
            'tipo_documento' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['DNI', 'LC', 'LE', 'Pasaporte'],
                'mensajes' => ['obligatorio' => 'Complete el tipo de documento.', 'formato' => 'Seleccione un tipo de documento válido.'],
                // Sin CUIL, para que el tipo no choque con la correspondencia entre CUIL y DNI
                'otros' => ['cuit_cuil' => ''],
            ],
            'nro_documento' => [
                'tipo' => 'DOC', 'obligatorio' => true, 'admite_pasaporte' => true,
                'mensajes' => ['obligatorio' => 'Complete el número de documento.', 'formato' => 'El documento debe tener entre 6 y 9 dígitos, sin puntos.'],
                'otros' => ['cuit_cuil' => ''],
            ],
            'cuit_cuil' => [
                'tipo' => 'CUI', 'obligatorio' => false, 'persona' => 'PersonaHumana',
                'mensajes' => ['formato' => 'El CUIT o CUIL debe tener 11 dígitos.'],
                // Con pasaporte, el CUIL no tiene que coincidir con el documento (CUI-06 se prueba aparte)
                'otros' => $pasaporte,
            ],
            'fecha_nacimiento' => [
                'tipo' => 'FEC', 'obligatorio' => true, 'anio_valido' => 1985,
                'mensajes' => ['obligatorio' => 'Complete la fecha de nacimiento.', 'formato' => 'Ingrese una fecha válida (dd/mm/aaaa).'],
            ],
            'estado_civil' => [
                'tipo' => 'SEL', 'obligatorio' => false, 'valores' => ['Soltero', 'Casado', 'UnionConvivencial', 'Divorciado', 'Viudo'],
                'mensajes' => ['formato' => 'Seleccione un estado civil válido.'],
            ],
            'nacionalidad' => $texto('la nacionalidad', 60, true),
            'ocupacion' => $texto('la actividad laboral o profesional', 100, true, 'Complete la actividad laboral o profesional principal.'),
            'empleador' => $texto('el empleador o actividad', 150, false),
            'correo' => [
                'tipo' => 'COR', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => 'Complete el correo electrónico.', 'formato' => 'Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.'],
            ],
            'telefono' => ['tipo' => 'TEL', 'obligatorio' => false, 'mensajes' => ['formato' => 'Ingrese un teléfono válido (solo números, espacios, guiones o +).']],
            'domicilio' => $texto('el domicilio', 200, true, 'Complete la calle y el número del domicilio.'),
            'codigo_postal' => ['tipo' => 'CPO', 'obligatorio' => false, 'mensajes' => ['formato' => 'Ingrese un código postal de 4 dígitos o el CPA de 8 caracteres (por ejemplo, N3350ABC).']],
            'ingresos_declarados' => [
                'tipo' => 'IMP', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => 'Complete los ingresos declarados.', 'formato' => 'Ingrese un importe válido, por ejemplo 850.000,00.'],
            ],
            'categoria_ingresos' => [
                // Monotributista exige además la categoría del monotributo (D-26): se prueba aparte
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['RelacionDependencia', 'JubiladoPensionado', 'ResponsableInscripto'],
                'mensajes' => ['obligatorio' => 'Seleccione la categoría de ingresos.', 'formato' => 'Seleccione una categoría de ingresos válida.'],
            ],
            'persona_expuesta' => [
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['Si', 'No'], 'guardado' => ['Si' => '1', 'No' => '0'],
                'mensajes' => [
                    'obligatorio' => 'Registre la declaración jurada sobre la condición de persona expuesta políticamente (RN-80).',
                    'formato' => 'Registre la declaración jurada sobre la condición de persona expuesta políticamente (RN-80).',
                ],
            ],
            'acepta_politica' => ['tipo' => 'CHK', 'mensajes' => ['obligatorio' => 'El cliente debe aceptar la política de tratamiento de datos personales.']],
        ];
    }

    /** @return array<string, array<string, mixed>> campos propios de la persona jurídica (Anexo B.4, IN-10) */
    public static function camposPersonaJuridica(): array
    {
        $texto = static fn (string $el, int $max, ?string $obligatorio = null, ?string $largo = null): array => [
            'tipo' => 'TXT', 'obligatorio' => true, 'max' => $max,
            'mensajes' => ['obligatorio' => $obligatorio ?? "Complete {$el}.", 'largo' => $largo ?? ucfirst($el) . " admite hasta {$max} caracteres."],
        ];

        return [
            'razon_social' => $texto('la razón social', 200),
            'cuit_cuil' => [
                'tipo' => 'CUI', 'obligatorio' => true, 'persona' => 'PersonaJuridica',
                'mensajes' => ['obligatorio' => 'Complete el CUIT.', 'formato' => 'El CUIT o CUIL debe tener 11 dígitos.'],
            ],
            'fecha_constitucion' => [
                'tipo' => 'FEC', 'obligatorio' => true, 'anio_valido' => 2010,
                'mensajes' => ['obligatorio' => 'Complete la fecha de constitución.', 'formato' => 'Ingrese una fecha válida (dd/mm/aaaa).'],
            ],
            'actividad_principal' => $texto('la actividad principal', 150),
            'representante_nombre' => [
                'tipo' => 'NOM', 'obligatorio' => true, 'max' => 80,
                'mensajes' => [
                    'obligatorio' => 'Complete el nombre del representante.',
                    'formato' => 'El nombre del representante solo admite letras, espacios, apóstrofos y guiones.',
                    'largo' => 'El nombre del representante admite hasta 80 caracteres.',
                ],
            ],
            'representante_documento' => [
                'tipo' => 'DOC', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => 'Complete el documento del representante.', 'formato' => 'El documento debe tener entre 6 y 9 dígitos, sin puntos.'],
            ],
            'representante_cargo' => $texto('el cargo del representante', 80, null, 'El cargo admite hasta 80 caracteres.'),
            'domicilio' => $texto('el domicilio', 200, 'Complete la calle y el número del domicilio legal.'),
            'ingresos_declarados' => [
                'tipo' => 'IMP', 'obligatorio' => true,
                'mensajes' => ['obligatorio' => 'Complete los ingresos netos mensuales promedio.', 'formato' => 'Ingrese un importe válido, por ejemplo 850.000,00.'],
            ],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosPersonaHumana(): array
    {
        return self::casosDelCatalogo('IN-09', self::camposPersonaHumana(), false);
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosPersonaJuridica(): array
    {
        return self::casosDelCatalogo('IN-10', self::camposPersonaJuridica(), false);
    }

    /**
     * @dataProvider casosPersonaHumana
     * @param array<string, mixed> $otros
     */
    public function testRegistrarPersonaHumanaConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->registrarConValor('PersonaHumana', $campo, $valor, $esperado, $otros);
    }

    /**
     * @dataProvider casosPersonaJuridica
     * @param array<string, mixed> $otros
     */
    public function testRegistrarPersonaJuridicaConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->registrarConValor('PersonaJuridica', $campo, $valor, $esperado, $otros);
    }

    /** @param array<string, mixed> $otros */
    private function registrarConValor(string $tipo, string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $servicio = new ClienteService($this->db);
        $datos = self::conValor($this->fabrica->datosCliente($tipo), $campo, $valor, $otros);
        $responsable = $this->fabrica->responsable();
        $antes = $this->ultimoId('clientes');

        try {
            $id = $servicio->registrar($datos, Fabrica::archivosIdentificacion($tipo), $responsable);
        } catch (ValidacionException $e) {
            $this->assertFalse($esperado->acepta, "{$campo}: se esperaba que {$esperado}, pero se rechazó: " . json_encode($e->errores, JSON_UNESCAPED_UNICODE));
            $this->assertArrayHasKey($campo, $e->errores, "{$campo}: el error debe marcar el campo. Errores: " . json_encode($e->errores, JSON_UNESCAPED_UNICODE));
            if ($esperado->mensaje !== null) {
                $this->assertSame($esperado->mensaje, $e->errores[$campo], "{$campo}: mensaje");
            }
            $this->assertSame($antes, $this->ultimoId('clientes'), 'Un rechazo no debe crear el cliente');

            return;
        }
        $this->assertTrue($esperado->acepta, "{$campo}: se esperaba que {$esperado}, pero se registró el cliente {$id}");
        $cliente = $servicio->obtener($id);
        if (array_key_exists($campo, $cliente)) {
            $this->assertValorGuardado($esperado->guardado, $cliente[$campo], $campo);
        }
        $this->assertSame('Activo', $cliente['estado']);
        $this->assertSame(1, $this->contar('registros_auditoria', ['accion' => 'clientes.registrar', 'objeto_id' => (string) $id]));
    }

    // ── Reglas que cruzan campos y unicidad ─────────────────────────────────────────────────────────────────────

    /** CUI-06: un CUIL válido de otro DNI no corresponde al documento ingresado. */
    public function testCuilQueNoCorrespondeAlDni(): void
    {
        $datos = $this->fabrica->datosCliente();
        $datos['cuit_cuil'] = Fabrica::personaHumana()['cuil'];

        $this->assertErrores(['cuit_cuil' => 'El CUIT/CUIL no corresponde al número de documento.'], $datos);
    }

    /** FEC-07: 18 años cumplidos hoy se aceptan; si los cumple mañana, no; una fecha futura tampoco. */
    public function testMayoriaDeEdad(): void
    {
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $cumpleHoy = $hoy->subYears(18);
        $cumpleManana = $hoy->subYears(18)->addDays(1);

        $this->fabrica->cliente('PersonaHumana', ['fecha_nacimiento' => $cumpleHoy->format('d/m/Y')]);
        $this->assertErrores(['fecha_nacimiento' => 'El cliente debe ser mayor de edad (18 años).'], ['fecha_nacimiento' => $cumpleManana->format('d/m/Y')] + $this->fabrica->datosCliente());
        $this->assertErrores(['fecha_nacimiento' => 'La fecha de nacimiento no puede ser futura.'], ['fecha_nacimiento' => $hoy->addDays(1)->format('d/m/Y')] + $this->fabrica->datosCliente());
        $this->assertErrores(['fecha_constitucion' => 'La fecha de constitución no puede ser futura.'], ['fecha_constitucion' => $hoy->addDays(1)->format('d/m/Y')] + $this->fabrica->datosCliente('PersonaJuridica'));
    }

    /** REF-01 a REF-05: provincia y localidad del domicilio (D-18). */
    public function testProvinciaYLocalidad(): void
    {
        $base = $this->fabrica->datosCliente();
        $otra = $this->fabrica->localidadDeOtraProvincia((int) $base['provincia_id']);
        foreach (['', '0', 'abc', '-1', '1.5', ['1']] as $valor) {
            $this->assertErrores(['provincia_id' => 'Seleccione la provincia.'], ['provincia_id' => $valor] + $base, 'provincia ' . var_export($valor, true));
        }
        foreach (['', '0', 'abc'] as $valor) {
            $this->assertErrores(['localidad_id' => 'Seleccione la localidad.'], ['localidad_id' => $valor] + $base, 'localidad ' . var_export($valor, true));
        }
        $this->assertErrores(['localidad_id' => 'Seleccione una localidad de la provincia elegida.'], ['localidad_id' => '999999999'] + $base, 'localidad inexistente');
        $this->assertErrores(['localidad_id' => 'Seleccione una localidad de la provincia elegida.'], ['localidad_id' => (string) $otra] + $base, 'localidad de otra provincia');

        $inactiva = $this->fabrica->localidad(1);
        $this->db->table('localidades')->where('id', $inactiva['localidad_id'])->update(['activa' => 0]);
        try {
            $this->assertErrores(['localidad_id' => 'La localidad ya no está disponible: seleccione otra.'], array_map('strval', $inactiva) + $base, 'localidad inactiva');
        } finally {
            $this->db->table('localidades')->where('id', $inactiva['localidad_id'])->update(['activa' => 1]);
        }
    }

    /** COR-09, DOC-11 y CUI-07: unicidad entre los clientes (RN-07, RN-08). */
    public function testUnicidad(): void
    {
        $primero = $this->fabrica->cliente();
        $d = $primero['datos'];

        $this->assertErrores(['correo' => 'El correo electrónico ya está registrado para otro cliente.'], ['correo' => mb_strtoupper($d['correo'])] + $this->fabrica->datosCliente());
        $this->assertErrores(['nro_documento' => 'El documento ya está registrado para otro cliente.'], ['nro_documento' => $d['nro_documento'], 'cuit_cuil' => ''] + $this->fabrica->datosCliente());
        $this->assertErrores(['cuit_cuil' => 'El CUIT/CUIL ya está registrado para otro cliente.'], ['cuit_cuil' => $d['cuit_cuil'], 'tipo_documento' => 'Pasaporte', 'nro_documento' => 'PP1234567'] + $this->fabrica->datosCliente());
        // El mismo número con otro tipo de documento se admite
        $this->fabrica->cliente('PersonaHumana', ['tipo_documento' => 'LE', 'nro_documento' => $d['nro_documento'], 'cuit_cuil' => '']);
    }

    /** RN-11 y ARC-01 a ARC-06: el documento de identidad es obligatorio y cada archivo se valida por su contenido. */
    public function testDocumentosDelAlta(): void
    {
        $servicio = new ClienteService($this->db);
        $responsable = $this->fabrica->responsable();
        $casos = [
            'sin documento' => [[], 'Adjunte este documento: es obligatorio para identificar al cliente (RN-11).'],
            'vacío' => [[Fabrica::archivo('DOC_IDENTIDAD', 'vacio', null, 'dni.pdf')], 'El archivo dni.pdf está vacío.'],
            '5 MB + 1 byte' => [[Fabrica::archivo('DOC_IDENTIDAD', 'pdf', 5 * 1024 * 1024 + 1, 'dni.pdf')], 'El archivo dni.pdf supera los 5 MB.'],
            'GIF' => [[Fabrica::archivo('DOC_IDENTIDAD', 'gif', null, 'dni.gif')], 'El archivo dni.gif no es PDF, JPG ni PNG.'],
            'ejecutable con extensión pdf' => [[Fabrica::archivo('DOC_IDENTIDAD', 'exe', null, 'dni.pdf')], 'El archivo dni.pdf no es PDF, JPG ni PNG.'],
        ];
        foreach ($casos as $caso => [$archivos, $mensaje]) {
            try {
                $servicio->registrar($this->fabrica->datosCliente(), $archivos, $responsable);
                $this->fail("{$caso}: se esperaba el rechazo");
            } catch (ValidacionException $e) {
                $this->assertSame($mensaje, $e->errores['doc_DOC_IDENTIDAD'] ?? null, $caso);
            }
        }
        foreach (['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'] as $tipo => $mime) {
            $id = $servicio->registrar($this->fabrica->datosCliente(), [Fabrica::archivo('DOC_IDENTIDAD', $tipo, null, "dni.{$tipo}")], $responsable);
            $doc = $this->db->table('documentos_legajo')->where('cliente_id', $id)->get()->getRowArray();
            $this->assertSame($mime, $doc['tipo_mime'], $tipo);
            $this->assertStringEndsWith(".{$tipo}", $doc['ruta_archivo']);
            $this->assertStringNotContainsString('dni', basename($doc['ruta_archivo']), 'El archivo se guarda con un nombre aleatorio');
        }
        $id = $servicio->registrar($this->fabrica->datosCliente(), [Fabrica::archivo('DOC_IDENTIDAD', 'pdf', 5 * 1024 * 1024, 'dni.pdf')], $responsable);
        $this->assertSame(1, $this->contar('documentos_legajo', ['cliente_id' => $id]), '5 MB exactos se aceptan');
        $id = $servicio->registrar($this->fabrica->datosCliente(), [Fabrica::archivo('DOC_IDENTIDAD', 'pdf', null, "../../etc/pass\x07wd.pdf")], $responsable);
        $this->assertSame('passwd.pdf', $this->db->table('documentos_legajo')->where('cliente_id', $id)->get()->getRowArray()['nombre_original']);
    }

    // ── IN-11 Modificar (HTTP) ───────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> los datos actuales del cliente como los presenta el formulario de modificación */
    private function formularioDe(int $id): array
    {
        $c = (new ClienteService($this->db))->obtener($id);
        $f = static fn (?string $fecha): string => $fecha === null ? '' : date('d/m/Y', strtotime(substr($fecha, 0, 10)));
        $datos = [
            'tipo_cliente' => $c['tipo_cliente'], 'correo' => (string) $c['correo'], 'telefono' => (string) $c['telefono'],
            'domicilio' => (string) $c['domicilio'], 'provincia_id' => (string) $c['provincia_id'], 'localidad_id' => (string) $c['localidad_id'],
            'codigo_postal' => (string) $c['codigo_postal'], 'ingresos_declarados' => (string) $c['ingresos_declarados'],
            'categoria_ingresos' => (string) $c['categoria_ingresos'], 'cuit_cuil' => (string) $c['cuit_cuil'],
            'persona_expuesta' => (int) $c['persona_expuesta'] === 1 ? 'Si' : 'No', 'version_fila' => (string) $c['version_fila'],
        ];
        if ($c['tipo_cliente'] === 'PersonaJuridica') {
            return $datos + [
                'razon_social' => $c['razon_social'], 'fecha_constitucion' => $f($c['fecha_constitucion']), 'actividad_principal' => $c['actividad_principal'],
                'representante_nombre' => $c['representante_nombre'], 'representante_documento' => $c['representante_documento'], 'representante_cargo' => $c['representante_cargo'],
            ];
        }

        return $datos + [
            'nombre' => $c['nombre'], 'apellido' => $c['apellido'], 'tipo_documento' => $c['tipo_documento'], 'nro_documento' => $c['nro_documento'],
            'fecha_nacimiento' => $f($c['fecha_nacimiento']), 'estado_civil' => (string) $c['estado_civil'], 'nacionalidad' => (string) $c['nacionalidad'],
            'ocupacion' => (string) $c['ocupacion'], 'empleador' => (string) $c['empleador'],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casosModificar(): array
    {
        $campos = array_intersect_key(self::camposPersonaHumana(), array_flip(['nombre', 'apellido', 'nacionalidad', 'ocupacion', 'empleador', 'telefono', 'domicilio', 'codigo_postal', 'ingresos_declarados', 'estado_civil']));

        return self::casosDelCatalogo('IN-11', $campos);
    }

    /**
     * Las clases de los campos que no cambian la identidad del cliente, por HTTP: la modificación aplica la misma
     * validación que el alta y registra el valor anterior y el nuevo (D-48).
     *
     * @dataProvider casosModificar
     * @param array<string, mixed> $otros
     */
    public function testModificarCampoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();
        $servicio = new ClienteService($this->db);
        $antes = $servicio->obtener($c['id']);
        $otroCampo = $campo === 'domicilio' ? 'empleador' : 'domicilio';
        $datos = self::conValor([$otroCampo => 'Cambio de prueba 123'] + $this->formularioDe($c['id']), $campo, $valor, $otros);

        $r = $this->enviar("admin/clientes/{$c['id']}/editar", $datos);
        $despues = $servicio->obtener($c['id']);

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            $this->assertValorGuardado($esperado->guardado, $despues[$campo], $campo);
            $this->assertSame((int) $antes['version_fila'] + 1, (int) $despues['version_fila']);

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame((int) $antes['version_fila'], (int) $despues['version_fila']);
    }

    /** RN-58: cambiar el correo exige describir la verificación de identidad; queda pendiente hasta confirmarlo. */
    public function testCambioDeCorreoExigeEvidencia(): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();
        $nuevo = 'nuevo.' . Fabrica::sufijo() . '@prueba.local';
        $datos = ['correo' => $nuevo] + $this->formularioDe($c['id']);

        foreach (['', '   ', 'corto', ['Presentó el DNI en la sucursal']] as $evidencia) {
            $r = $this->enviar("admin/clientes/{$c['id']}/editar", $datos + ['evidencia_identidad' => $evidencia]);
            $this->assertCampoRechazado($r, 'evidencia_identidad', 'Para cambiar el correo, describa cómo comprobó presencialmente el documento y la identidad del cliente.', var_export($evidencia, true));
        }
        $this->assertAceptado($this->enviar("admin/clientes/{$c['id']}/editar", $datos + ['evidencia_identidad' => 'Presentó el DNI original en la sucursal.']));
        $despues = (new ClienteService($this->db))->obtener($c['id']);
        $this->assertSame($c['datos']['correo'], $despues['correo'], 'Hasta confirmarlo rige el correo anterior');
        $this->assertSame($nuevo, $despues['correo_pendiente']);
    }

    /** IN-C06, IN-C07, IN-C10 e IN-C11 en la modificación. */
    public function testModificarControles(): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();
        $datos = $this->formularioDe($c['id']);
        $leida = $datos['version_fila'];

        $this->assertAceptado($this->enviar("admin/clientes/{$c['id']}/editar", ['domicilio' => 'Primera calle 1'] + $datos));
        foreach (['vieja' => $leida, 'vacía' => '', 'texto' => 'abc', 'lista' => [$leida], 'ausente' => Esperado::AUSENTE] as $caso => $version) {
            $this->enviar("admin/clientes/{$c['id']}/editar", self::conValor(['domicilio' => 'Segunda calle 2'] + $datos, 'version_fila', $version));
            $this->assertSame(ConflictoConcurrenciaException::MENSAJE, $_SESSION['error'] ?? null, "IN-C06/C07 {$caso}");
        }
        $this->assertSame('Primera calle 1', (new ClienteService($this->db))->obtener($c['id'])['domicilio']);

        // IN-C10: el estado, el tipo y la cuenta no se cambian desde el formulario
        $actual = $this->formularioDe($c['id']);
        $this->enviar("admin/clientes/{$c['id']}/editar", ['domicilio' => 'Tercera calle 3', 'estado' => 'Desactivado', 'cuenta_cliente_id' => '1', 'fecha_alta' => '2000-01-01'] + $actual);
        $despues = (new ClienteService($this->db))->obtener($c['id']);
        $this->assertSame('Activo', $despues['estado']);
        $this->assertNotSame('2000-01-01', substr((string) $despues['fecha_alta'], 0, 10));

        // Sin cambios y cliente desactivado (IN-C11)
        $this->enviar("admin/clientes/{$c['id']}/editar", $this->formularioDe($c['id']));
        $this->assertSame('No hay cambios para guardar.', $_SESSION['error'] ?? null);
        $this->db->table('clientes')->where('id', $c['id'])->update(['estado' => 'Desactivado']);
        $this->enviar("admin/clientes/{$c['id']}/editar", ['domicilio' => 'Cuarta calle 4'] + $this->formularioDe($c['id']));
        $this->assertSame('Solo se pueden modificar los datos de un cliente activo.', $_SESSION['error'] ?? null);
    }

    // ── IN-12 Documentos del legajo ──────────────────────────────────────────────────────────────────────────────

    /** RN-01: la descarga solo entrega documentos del cliente de la dirección. */
    public function testDescargaDeDocumentoDeOtroCliente(): void
    {
        $this->comoRol('AnalistaCredito');
        $a = $this->fabrica->cliente();
        $b = $this->fabrica->cliente();
        $docB = (int) $this->db->table('documentos_legajo')->select('id')->where('cliente_id', $b['id'])->get()->getRowArray()['id'];

        $r = $this->pedir("admin/clientes/{$a['id']}/documentos/{$docB}");
        $r->assertRedirectTo(site_url("admin/clientes/{$a['id']}"));
        $this->assertSame('El documento no existe.', $_SESSION['error'] ?? null);

        $propio = $this->pedir("admin/clientes/{$b['id']}/documentos/{$docB}");
        // La descarga se envía como archivo: el cuerpo de la respuesta de prueba queda vacío
        $this->assertSame(200, $propio->response()->getStatusCode());
        $this->assertStringContainsString('attachment', $propio->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame(1, $this->contar('registros_auditoria', ['accion' => 'clientes.descargar_documento', 'objeto_id' => (string) $docB]));
    }

    public function testAgregarDocumentosSinArchivos(): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();

        $this->enviar("admin/clientes/{$c['id']}/documentos", []);
        $this->assertStringContainsString('Seleccione al menos un archivo.', (string) ($_SESSION['error'] ?? json_encode($_SESSION['errores'] ?? [], JSON_UNESCAPED_UNICODE)));
        $this->assertSame(1, $this->contar('documentos_legajo', ['cliente_id' => $c['id']]));
    }

    // ── IN-13 Desactivar y reactivar ─────────────────────────────────────────────────────────────────────────────

    public function testDesactivarYReactivar(): void
    {
        $this->comoRol('Gerente');
        $c = $this->fabrica->clienteConAcceso();
        $version = fn (): string => (string) (new ClienteService($this->db))->obtener($c['id'])['version_fila'];

        foreach (['', 'Baja', 'a    b', str_repeat('m', 1001), ['Motivo como lista']] as $motivo) {
            $this->enviar("admin/clientes/{$c['id']}/desactivar", ['motivo' => $motivo, 'version_fila' => $version()]);
            $this->assertNotEmpty($_SESSION['errores']['motivo'] ?? null, 'motivo ' . var_export($motivo, true));
        }
        $this->enviar("admin/clientes/{$c['id']}/reactivar", ['motivo' => 'No corresponde', 'version_fila' => $version()]);
        $this->assertSame('El cliente no está desactivado.', $_SESSION['error'] ?? null);

        $this->assertAceptado($this->enviar("admin/clientes/{$c['id']}/desactivar", ['motivo' => 'Solicitud del cliente', 'version_fila' => $version()]));
        $this->assertSame(1, $this->contar('clientes', ['id' => $c['id'], 'estado' => 'Desactivado']), 'Baja lógica: la fila se conserva');
        $this->assertSame(1, $this->contar('documentos_legajo', ['cliente_id' => $c['id']]), 'El legajo se conserva');

        // No aparece entre los activos y no puede ingresar al portal (RN-10)
        $enlace = "admin/clientes/{$c['id']}\"";
        $this->assertStringNotContainsString($enlace, (string) $this->pedir('admin/clientes', ['texto' => 'Prueba', 'estado' => 'Activo'])->response()->getBody());
        $this->assertStringContainsString($enlace, (string) $this->pedir('admin/clientes', ['texto' => 'Prueba', 'estado' => 'Desactivado'])->response()->getBody());
        $this->sinSesion();
        $this->enviar('portal/login', ['correo' => $c['correo'], 'contrasena' => $c['clave']]);
        $this->assertSame('Usuario o contraseña incorrectos.', $_SESSION['error'] ?? null);

        $this->comoRol('Gerente');
        $this->assertAceptado($this->enviar("admin/clientes/{$c['id']}/reactivar", ['motivo' => 'Regularizó su situación', 'version_fila' => $version()]));
        $this->assertSame(1, $this->contar('clientes', ['id' => $c['id'], 'estado' => 'Activo']));
    }

    public function testClienteInexistente(): void
    {
        $this->comoRol('AnalistaCredito');
        foreach (['', '/editar', '/historial'] as $sufijo) {
            $r = $this->pedir("admin/clientes/999999999{$sufijo}");
            $this->assertTrue($r->isRedirect(), $sufijo);
            $this->assertSame('El cliente no existe.', $_SESSION['error'] ?? null, $sufijo);
        }
    }

    // ── IN-14 Búsqueda y datos auxiliares ────────────────────────────────────────────────────────────────────────

    public function testBusquedaConCadaClaseDeFiltro(): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();
        $casos = [
            'sin filtros' => [], 'texto como lista' => ['texto' => ['x']],
            'todos como lista' => ['texto' => ['x'], 'documento' => ['1'], 'cuit' => ['2'], 'tipo' => ['PersonaHumana'], 'estado' => ['Activo']],
            'comodín %' => ['texto' => '%'], 'comodín _' => ['texto' => '_'], 'corchetes' => ['texto' => '[a]'],
            'tipo inexistente' => ['tipo' => 'Inexistente'], 'estado inexistente' => ['estado' => 'Inexistente'],
            'HTML' => ['texto' => '<script>alert(1)</script>'], 'SQL' => ['texto' => "' OR 1=1 --"],
        ];
        foreach ($casos as $caso => $filtros) {
            $r = $this->pedir('admin/clientes', $filtros);
            $r->assertOK();
            $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $r->response()->getBody(), $caso);
        }
        // La búsqueda no muestra el correo: el cliente se identifica por el enlace a su ficha
        $enlace = "admin/clientes/{$c['id']}\"";
        $this->assertStringNotContainsString($enlace, (string) $this->pedir('admin/clientes', ['texto' => '%'])->response()->getBody(), 'FIL-03: % no es comodín');
        $documento = number_format((float) $c['datos']['nro_documento'], 0, '', '.');
        $this->assertStringContainsString($enlace, (string) $this->pedir('admin/clientes', ['documento' => $documento])->response()->getBody(), 'El documento se busca con o sin puntos');
    }

    public function testLocalidadesPorProvincia(): void
    {
        $this->comoRol('AnalistaCredito');
        $l = $this->fabrica->localidad();

        $r = $this->pedir("admin/clientes/localidades/{$l['provincia_id']}", [], true);
        $r->assertOK();
        $lista = json_decode((string) $r->response()->getBody(), true);
        $this->assertIsArray($lista);
        $this->assertNotEmpty($lista);

        $vacia = json_decode((string) $this->pedir('admin/clientes/localidades/999999999', [], true)->response()->getBody(), true);
        $this->assertSame([], $vacia['localidades'] ?? $vacia);
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * El registro se rechaza con exactamente esos errores en esos campos, y no crea el cliente.
     *
     * @param array<string, string> $esperados
     * @param array<string, mixed> $datos
     */
    private function assertErrores(array $esperados, array $datos, string $contexto = ''): void
    {
        $antes = $this->ultimoId('clientes');
        try {
            (new ClienteService($this->db))->registrar($datos, Fabrica::archivosIdentificacion((string) $datos['tipo_cliente']), $this->fabrica->responsable());
            $this->fail("{$contexto}: se esperaba el rechazo");
        } catch (ValidacionException $e) {
            foreach ($esperados as $campo => $mensaje) {
                $this->assertSame($mensaje, $e->errores[$campo] ?? null, "{$contexto}: {$campo}. Errores: " . json_encode($e->errores, JSON_UNESCAPED_UNICODE));
            }
        } catch (ReglaNegocioException $e) {
            $this->fail("{$contexto}: se esperaba un error por campo y llegó: {$e->getMessage()}");
        }
        $this->assertSame($antes, $this->ultimoId('clientes'));
    }
}
