<?php

declare(strict_types=1);

namespace Tests\Support\Feature;

use App\Libraries\Validacion\Campos;
use App\Services\Clientes\ClienteService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use App\Services\Caja\CajaService;
use App\Services\Solicitudes\ContratoService;
use App\Services\Solicitudes\LiquidacionService;
use App\Services\Solicitudes\EstructuracionService;
use App\Services\Solicitudes\EvaluacionService;
use App\Services\Solicitudes\SimulacionService;
use App\Services\Solicitudes\SolicitudService;
use App\Services\Lineas\LineaService;
use App\Services\Usuarios\UsuarioService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Datos propios de cada prueba de integración (D-51): usuarios, clientes y líneas con identificadores únicos, dígitos
 * verificadores válidos y el dominio ficticio prueba.local. Se crean con los servicios del sistema, de modo que pasan
 * por las mismas validaciones y transacciones que una operación real; solo la contraseña inicial de un usuario se
 * define directamente en la base, para no recorrer el enlace de activación en cada prueba.
 */
final class Fabrica
{
    public const CLAVE = 'Prueba.Integracion.2026!';

    /** @var array<string, true> números ya entregados en esta ejecución */
    private static array $usados = [];

    private static ?string $hashClave = null;

    private static ?int $responsableId = null;

    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    // ── Valores únicos ───────────────────────────────────────────────────────────────────────────────────────────

    public static function sufijo(): string
    {
        return bin2hex(random_bytes(5));
    }

    public static function entre(int $desde, int $hasta): int
    {
        return random_int($desde, $hasta);
    }

    /** DNI de 8 dígitos entre 40.000.000 y 49.999.999, distinto de los ya entregados. */
    public static function dni(): int
    {
        do {
            $n = random_int(40_000_000, 49_999_999);
        } while (isset(self::$usados["dni{$n}"]));
        self::$usados["dni{$n}"] = true;

        return $n;
    }

    /** CUIT o CUIL válido con ese prefijo; con $dni, los 8 dígitos centrales son el documento. */
    public static function cuitValido(string $prefijo, ?int $dni = null): string
    {
        for ($i = 0; $i < 100; $i++) {
            $cuerpo = $prefijo . str_pad((string) ($dni ?? random_int(10_000_000, 99_999_999)), 8, '0', STR_PAD_LEFT);
            $dv = Campos::digitoVerificadorCuit($cuerpo);
            if ($dv !== null && !isset(self::$usados["cuit{$cuerpo}{$dv}"])) {
                self::$usados["cuit{$cuerpo}{$dv}"] = true;

                return $cuerpo . $dv;
            }
            if ($dni !== null) {
                throw new \RuntimeException("El DNI {$dni} no tiene CUIL con el prefijo {$prefijo}.");
            }
        }
        throw new \RuntimeException('No se encontró un CUIT disponible.');
    }

    /** Diez dígitos con ese prefijo cuyo verificador calculado es 10: no existe un CUIT válido con ellos (CUI-05). */
    public static function numeroSinVerificador(string $prefijo): string
    {
        for ($i = 0; $i < 10_000; $i++) {
            $cuerpo = $prefijo . random_int(10_000_000, 99_999_999);
            if (Campos::digitoVerificadorCuit($cuerpo) === null) {
                return $cuerpo;
            }
        }
        throw new \RuntimeException('No se encontró un número sin verificador.');
    }

    /** Persona humana con DNI y CUIL coherentes. @return array{dni: int, cuil: string} */
    public static function personaHumana(?int $verificador = null): array
    {
        // El simulador de la Central de Deudores responde según el último dígito del CUIL: 0, sin deudas; 9, sin
        // respuesta (evaluación de contingencia); 1, 2, 3 o 5, esa situación
        do {
            $dni = self::dni();
            $dv = Campos::digitoVerificadorCuit('20' . $dni);
        } while ($dv === null || ($verificador !== null && $dv !== $verificador));

        return ['dni' => $dni, 'cuil' => self::cuitValido('20', $dni)];
    }

    // ── Usuarios internos ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Datos válidos del formulario de usuario interno (CU-05).
     *
     * @return array<string, string>
     */
    public static function datosUsuario(string $rol = 'AnalistaCredito'): array
    {
        $u = self::sufijo();

        return [
            'nombre' => 'Prueba', 'apellido' => 'Integración', 'correo' => "usuario.{$u}@prueba.local",
            'tipo_documento' => 'DNI', 'nro_documento' => (string) self::dni(), 'rol' => $rol,
        ];
    }

    /**
     * Usuario interno habilitado con la contraseña Fabrica::CLAVE.
     *
     * @param 'Administrador'|'Gerente'|'AnalistaCredito'|'Cajero' $rol
     * @return array{id: int, correo: string, clave: string, rol: string}
     */
    public function usuario(string $rol = 'AnalistaCredito', string $estado = 'Habilitada'): array
    {
        $datos = self::datosUsuario($rol);
        $id = (new UsuarioService($this->db))->registrar($datos);
        self::$hashClave ??= password_hash(self::CLAVE, PASSWORD_ARGON2ID);
        $this->db->table('cuentas_acceso')->where('id', $id)->update([
            'hash_contrasena' => self::$hashClave, 'estado' => $estado, 'correo_verificado' => 1,
        ]);

        return ['id' => $id, 'correo' => $datos['correo'], 'clave' => self::CLAVE, 'rol' => $rol];
    }

    /** Analista que figura como responsable de los clientes que crea la fábrica. */
    public function responsable(): int
    {
        return self::$responsableId ??= $this->usuario('AnalistaCredito')['id'];
    }

    // ── Clientes ─────────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array{provincia_id: int, localidad_id: int} una localidad activa del catálogo */
    public function localidad(int $desplazamiento = 0): array
    {
        $l = $this->db->query(
            'SELECT id, provincia_id FROM dbo.localidades WHERE activa = 1 ORDER BY id OFFSET ? ROWS FETCH NEXT 1 ROWS ONLY',
            [$desplazamiento],
        )->getRowArray();
        if ($l === null) {
            throw new \RuntimeException('El catálogo geográfico está vacío: falta GeografiaSeeder.');
        }

        return ['provincia_id' => (int) $l['provincia_id'], 'localidad_id' => (int) $l['id']];
    }

    /** Una localidad activa de otra provincia que la indicada (REF-04). */
    public function localidadDeOtraProvincia(int $provinciaId): int
    {
        return (int) $this->db->query(
            'SELECT TOP 1 id FROM dbo.localidades WHERE activa = 1 AND provincia_id <> ? ORDER BY id',
            [$provinciaId],
        )->getRowArray()['id'];
    }

    /**
     * Datos válidos del formulario de cliente (CU-11).
     *
     * @param 'PersonaHumana'|'PersonaJuridica' $tipo
     * @return array<string, string>
     */
    public function datosCliente(string $tipo = 'PersonaHumana'): array
    {
        $u = self::sufijo();
        $comunes = $this->localidad() + [
            'tipo_cliente' => $tipo, 'correo' => "cliente.{$u}@prueba.local", 'telefono' => '3764441234',
            'domicilio' => 'Av. Siempre Viva 742', 'codigo_postal' => '3300', 'ingresos_declarados' => '850.000,00',
            'persona_expuesta' => 'No', 'acepta_politica' => '1',
        ];
        $comunes = array_map('strval', $comunes);
        if ($tipo === 'PersonaJuridica') {
            return $comunes + [
                'razon_social' => "Empresa de Prueba {$u} S.A.", 'cuit_cuil' => self::cuitValido('30'),
                'fecha_constitucion' => '15/03/2010', 'actividad_principal' => 'Comercio minorista',
                'representante_nombre' => 'Laura Gómez', 'representante_documento' => (string) self::dni(),
                'representante_cargo' => 'Presidenta', 'categoria_ingresos' => 'Empresa',
            ];
        }
        $p = self::personaHumana();

        return $comunes + [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'tipo_documento' => 'DNI', 'nro_documento' => (string) $p['dni'],
            'cuit_cuil' => $p['cuil'], 'fecha_nacimiento' => '10/05/1985', 'estado_civil' => 'Soltero',
            'nacionalidad' => 'Argentina', 'ocupacion' => 'Empleada administrativa', 'empleador' => 'Comercio Prueba S.R.L.',
            'categoria_ingresos' => 'RelacionDependencia',
        ];
    }

    /**
     * Archivos del legajo exigidos para identificar a un cliente del tipo indicado (RN-11), como los arma el
     * controlador a partir de la subida.
     *
     * @return list<array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza: ?int}>
     */
    public static function archivosIdentificacion(string $tipo = 'PersonaHumana'): array
    {
        $codigos = $tipo === 'PersonaJuridica' ? ['CONST_CUIT', 'DOC_IDENTIDAD', 'PODER_REPRESENTACION'] : ['DOC_IDENTIDAD'];

        return array_map(static fn (string $c): array => self::archivo($c, 'pdf'), $codigos);
    }

    /**
     * Un archivo temporal del tipo pedido, como lo entrega el controlador al servicio.
     *
     * @param 'pdf'|'jpg'|'png'|'gif'|'exe'|'vacio' $tipo
     * @return array{codigo: string, nombre: string, temporal: string, tamano: int, reemplaza: ?int}
     */
    public static function archivo(string $codigo, string $tipo, ?int $tamano = null, ?string $nombre = null): array
    {
        $contenido = match ($tipo) {
            'pdf' => "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true),
            'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=', true),
            'gif' => 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x00\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;",
            'exe' => "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00" . str_repeat("\x00", 64),
            'vacio' => '',
        };
        if ($tamano !== null && $tipo === 'pdf') {
            $contenido = str_pad((string) $contenido, $tamano, ' ');
        }
        $ruta = tempnam(sys_get_temp_dir(), 'leg');
        file_put_contents($ruta, (string) $contenido);

        return [
            'codigo' => $codigo, 'nombre' => $nombre ?? "documento.{$tipo}", 'temporal' => $ruta,
            'tamano' => strlen((string) $contenido), 'reemplaza' => null,
        ];
    }

    /**
     * Cliente activo registrado con CU-11.
     *
     * @param 'PersonaHumana'|'PersonaJuridica' $tipo
     * @param array<string, string> $cambios datos que reemplazan a los generados
     * @return array{id: int, datos: array<string, string>}
     */
    public function cliente(string $tipo = 'PersonaHumana', array $cambios = []): array
    {
        $datos = $cambios + $this->datosCliente($tipo);
        $id = (new ClienteService($this->db))->registrar($datos, self::archivosIdentificacion($tipo), $this->responsable());

        return ['id' => $id, 'datos' => $datos];
    }

    /**
     * Cliente activo con su acceso al portal habilitado y la contraseña Fabrica::CLAVE.
     *
     * @return array{id: int, cuenta_id: int, correo: string, clave: string, datos: array<string, string>}
     */
    public function clienteConAcceso(string $tipo = 'PersonaHumana'): array
    {
        $c = $this->cliente($tipo);

        return $this->habilitarPortal($c['id']) + ['datos' => $c['datos']];
    }

    /**
     * Habilita el acceso al portal de un cliente ya registrado, con la contraseña Fabrica::CLAVE (CU-04 abreviado).
     *
     * @return array{id: int, cuenta_id: int, correo: string, clave: string}
     */
    public function habilitarPortal(int $clienteId): array
    {
        $fila = $this->db->query('SELECT c.cuenta_cliente_id, ca.correo FROM dbo.clientes c JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id WHERE c.id = ?', [$clienteId])->getRowArray();
        self::$hashClave ??= password_hash(self::CLAVE, PASSWORD_ARGON2ID);
        $this->db->table('cuentas_acceso')->where('id', $fila['cuenta_cliente_id'])->update([
            'hash_contrasena' => self::$hashClave, 'estado' => 'Habilitada', 'correo_verificado' => 1,
        ]);

        return ['id' => $clienteId, 'cuenta_id' => (int) $fila['cuenta_cliente_id'], 'correo' => (string) $fila['correo'], 'clave' => self::CLAVE];
    }

    // ── Líneas de crédito ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Datos válidos del formulario de línea (CU-16).
     *
     * @return array<string, mixed>
     */
    public static function datosLinea(): array
    {
        $u = strtoupper(substr(self::sufijo(), 0, 8));
        $hoy = Time::now(config('Sigccr')->zonaNegocio);

        return [
            'codigo' => "L{$u}", 'nombre' => "Línea de prueba {$u}", 'descripcion' => 'Línea creada por las pruebas de integración.',
            'tna' => '65', 'gasto_otorgamiento_pct' => '2', 'tipo_gasto_administrativo' => 'Fijo', 'gasto_administrativo_cuota' => '500,00',
            'gasto_administrativo_pct' => '', 'monto_minimo' => '100.000,00', 'monto_maximo' => '1.500.000,00',
            'cuotas_minimas' => '3', 'cuotas_maximas' => '24', 'admite_persona_humana' => '1', 'admite_persona_juridica' => '1',
            'edad_minima' => '18', 'edad_maxima' => '75', 'antiguedad_minima_meses' => '12',
            'categorias' => ['RelacionDependencia', 'Empresa'],
            'requisitos' => ['RelacionDependencia' => ['DOC_IDENTIDAD', 'RECIBO_SUELDO'], 'Empresa' => ['DOC_IDENTIDAD', 'ESTATUTO']],
            'vigencia_inicio' => $hoy->format('d/m/Y'), 'vigencia_fin' => $hoy->addYears(1)->format('d/m/Y'),
        ];
    }

    /**
     * Línea activa registrada con CU-16.
     *
     * @param array<string, mixed> $cambios
     * @return array{id: int, datos: array<string, mixed>}
     */
    public function linea(array $cambios = []): array
    {
        $datos = $cambios + self::datosLinea();

        return ['id' => (new LineaService($this->db))->registrar($datos), 'datos' => $datos];
    }

    // ── Solicitudes de crédito (iteración 2, bloque B) ─────────────────────────────────────────────────────────

    /**
     * Cliente que puede pedir un crédito en una línea de la fábrica: persona humana en relación de dependencia, con el
     * documento de identidad y el recibo de sueldo en el legajo. $verificador elige la respuesta de la Central de
     * Deudores simulada (ver personaHumana).
     *
     * @param array<string, mixed> $cambios
     * @return array{id: int, datos: array<string, mixed>}
     */
    public function clienteParaCredito(int $verificador = 0, array $cambios = []): array
    {
        $p = self::personaHumana($verificador);
        $datos = $cambios + ['nro_documento' => (string) $p['dni'], 'cuit_cuil' => $p['cuil'], 'ingresos_declarados' => '2.500.000,00'] + $this->datosCliente();
        $archivos = [...self::archivosIdentificacion(), self::archivo('RECIBO_SUELDO', 'pdf')];
        $id = (new ClienteService($this->db))->registrar($datos, $archivos, $this->responsable());

        return ['id' => $id, 'datos' => $datos];
    }

    /**
     * Oferta vigente de una campaña activa para un cliente con capacidad de pago y el portal habilitado (CU-93
     * abreviado). La activación real recorre todos los clientes de la base; acá la campaña se registra con los mismos
     * datos que guarda CampanaService::activar() y sin otra campaña activa (RN-66).
     *
     * @return array{id: int, campana_id: int, cliente: array{id: int, datos: array<string, mixed>}, acceso: array<string, mixed>, linea_id: int}
     */
    public function oferta(string $importeMaximo = '1500000.00', int $cuotas = 12, string $capitalAsignado = '100000000.00'): array
    {
        $this->fondos();
        $cliente = $this->clienteParaCredito();
        $acceso = $this->habilitarPortal($cliente['id']);
        $linea = $this->linea();
        $version = (new SimulacionService($this->db))->versionVigente($linea['id']);
        $parametros = (new ParametroService($this->db))->vigente('Campanas');
        $this->db->query("UPDATE dbo.campanas SET estado = 'Finalizada', fecha_cierre = SYSDATETIMEOFFSET(), causa_cierre = 'Vigencia' WHERE estado = 'Activa'");
        $this->db->table('proyecciones_fondos')->insert([
            'periodo_dias' => 30, 'disponible_actual' => '0.00', 'ingresos_esperados' => '0.00', 'egresos_comprometidos' => '0.00',
            'reserva_minima' => '0.00', 'disponible_proyectado' => $capitalAsignado, 'version_parametros_id' => (int) $parametros['id'],
        ]);
        $vence = Time::now('UTC')->addDays(15)->format('Y-m-d H:i:s.vP');
        $this->db->table('campanas')->insert([
            'proyeccion_fondos_id' => (int) $this->db->insertID(), 'version_condiciones_id' => (int) $version['version_id'],
            'version_parametros_id' => (int) $parametros['id'], 'vence' => $vence, 'capital_asignado' => $capitalAsignado, 'estado' => 'Activa',
        ]);
        $campana = (int) $this->db->insertID();
        $this->db->table('ofertas')->insert([
            'campana_id' => $campana, 'cliente_id' => $cliente['id'], 'importe_maximo' => $importeMaximo, 'cantidad_cuotas' => $cuotas,
            'tna' => (string) $version['tna'], 'tea' => (string) $version['tna'], 'cftea' => (string) $version['tna'], 'vence' => $vence, 'estado' => 'Vigente',
        ]);

        return ['id' => (int) $this->db->insertID(), 'campana_id' => $campana, 'cliente' => $cliente, 'acceso' => $acceso, 'linea_id' => $linea['id']];
    }

    /** Disponible libre suficiente para reservar y desembolsar (RN-41, RN-71). */
    public function fondos(): void
    {
        if (bccomp((new FondosService($this->db))->disponibilidad()['libre'], '100000000', 2) >= 0) {
            return;
        }
        $this->db->table('movimientos_fondos')->insert([
            'tipo' => 'AporteCapital', 'caracter' => 'Ingreso', 'importe' => '1000000000.00', 'fecha' => '2020-01-01',
            'concepto' => 'Fondos para las solicitudes de prueba', 'estado' => 'Realizado', 'usuario_id' => $this->responsable(),
        ]);
    }

    public function simulacion(int $clienteId, int $lineaId, string $importe = '500.000,00', string $cuotas = '12'): int
    {
        return (new SimulacionService($this->db))->simular($clienteId, $lineaId, $importe, $cuotas, $this->responsable());
    }

    /**
     * Solicitud llevada hasta el estado pedido por el mismo camino que los casos de uso, con los servicios:
     * PendienteVerificacion (CU-22), PendienteEvaluacion (CU-23), PendienteSeleccionPlan (CU-86 favorable),
     * PendienteContingencia (CU-86 sin respuesta de la Central de Deudores), PendienteResolucion (CU-25),
     * AprobadaPendienteLiquidacion (CU-79 con aprobación automática) y ConConstancia (CU-27 listo para liquidar).
     *
     * @return array{id: int, cliente: array{id: int, datos: array<string, mixed>}, linea_id: int, simulacion_id: int}
     */
    public function solicitud(string $hasta = 'PendienteVerificacion', string $importe = '500.000,00', string $cuotas = '12'): array
    {
        $this->fondos();
        $contingencia = in_array($hasta, ['PendienteContingencia', 'PendienteResolucion'], true);
        $cliente = $this->clienteParaCredito($contingencia ? 9 : 0);
        $linea = $this->linea()['id'];
        $simulacion = $this->simulacion($cliente['id'], $linea, $importe, $cuotas);
        $solicitudes = new SolicitudService($this->db);
        $id = (int) $solicitudes->registrar($simulacion, [], $this->responsable())['id'];
        $r = ['id' => $id, 'cliente' => $cliente, 'linea_id' => $linea, 'simulacion_id' => $simulacion];
        if ($hasta === 'PendienteVerificacion') {
            return $r;
        }
        $s = $solicitudes->obtener($id);
        $verificaciones = [];
        foreach ($s['documentos'] as $d) {
            $verificaciones[(int) $d['id']] = ['resultado' => 'Conforme', 'motivo' => '', 'observacion' => ''];
        }
        $solicitudes->verificar($id, (int) $s['version_fila'], 'Verificada', $verificaciones, '', '', $this->responsable());
        if ($hasta === 'PendienteEvaluacion') {
            return $r;
        }
        (new EvaluacionService($this->db))->evaluar($id);
        $this->exigirEstado($id, $contingencia ? 'PendienteContingencia' : 'PendienteSeleccionPlan');
        if (in_array($hasta, ['PendienteSeleccionPlan', 'PendienteContingencia'], true)) {
            return $r;
        }
        if ($hasta === 'PendienteResolucion') {
            (new EvaluacionService($this->db))->registrarContingencia($id, (int) $solicitudes->obtener($id)['version_fila'], [
                'antecedentes' => ['informacion_economica', 'documentacion'], 'detalle_antecedentes' => 'Recibos de sueldo de los últimos tres meses verificados.',
                'recomendacion' => 'Favorable', 'justificacion' => 'Ingresos estables y suficientes para la cuota solicitada.',
            ], $this->responsable());
            $this->exigirEstado($id, 'PendienteResolucion');

            return $r;
        }
        $alternativa = (int) $this->db->query(
            "SELECT TOP 1 a.id FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE e.solicitud_id = ? AND a.estado = 'Vigente' ORDER BY a.id",
            [$id],
        )->getRow()->id;
        (new EstructuracionService($this->db))->confirmar($id, $alternativa, (int) $solicitudes->obtener($id)['version_fila'], [
            'fecha_conformidad' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'), 'medio_conformidad' => 'PresencialFirma', 'observaciones' => '',
        ], $this->responsable());
        $this->exigirEstado($id, 'AprobadaPendienteLiquidacion');
        if ($hasta === 'ConConstancia') {
            (new ContratoService($this->db))->registrarConstancia($id, self::archivo('CONTRATO', 'pdf'), Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'), $this->responsable());
        }

        return $r;
    }

    // ── Créditos y caja (iteración 3) ─────────────────────────────────────────────────────────────────────────

    /** CBU válido de un banco de prueba (072, sucursal 0045), distinto para cada $n. */
    public static function cbu(int $n = 1): string
    {
        $digito = static function (string $numero, array $pesos): string {
            $suma = 0;
            foreach ($pesos as $i => $peso) {
                $suma += (int) $numero[$i] * $peso;
            }

            return (string) ((10 - $suma % 10) % 10);
        };
        $bloque = '0720045';
        $cuenta = str_pad((string) (90000001 + $n * 137), 13, '0', STR_PAD_LEFT);

        return $bloque . $digito($bloque, [7, 1, 3, 9, 7, 1, 3]) . $cuenta . $digito($cuenta, [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3]);
    }

    /**
     * Crédito vigente liquidado por el camino de CU-21 a CU-27, con sus cuotas pendientes.
     *
     * @return array{id: int, solicitud_id: int, cliente: array{id: int, datos: array<string, mixed>}, cuotas: list<array<string, mixed>>}
     */
    public function credito(string $importe = '500.000,00', string $cuotas = '12'): array
    {
        $s = $this->solicitud('ConConstancia', $importe, $cuotas);
        $liquidacion = new LiquidacionService($this->db);
        $datos = $liquidacion->datos($s['id']);
        $r = $liquidacion->liquidar($s['id'], (int) $datos['version_fila'], [
            'documento_verificado' => (string) $s['cliente']['datos']['nro_documento'], 'conformidad' => '1', 'cbu' => self::cbu(),
            'cuit_titular' => (string) $s['cliente']['datos']['cuit_cuil'], 'evidencia_titularidad' => 'Constancia de CBU del titular',
            'comprobante' => 'OP-' . self::sufijo(), 'fecha_transferencia' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'),
            'importe_transferencia' => str_replace('.', ',', $datos['calculo']['neto']->toFixed(2)),
        ], $this->responsable());
        $id = (int) ($r['credito_id'] ?? $this->db->query('SELECT credito_id FROM dbo.liquidaciones WHERE solicitud_id = ?', [$s['id']])->getRow()->credito_id);

        return ['id' => $id, 'solicitud_id' => $s['id'], 'cliente' => $s['cliente'], 'cuotas' => $this->cuotas($id)];
    }

    /** @return list<array<string, mixed>> cuotas del crédito, por número */
    public function cuotas(int $creditoId): array
    {
        return $this->db->table('cuotas')->where('credito_id', $creditoId)->orderBy('numero')->get()->getResultArray();
    }

    /** Asigna una caja nueva al Cajero desde hoy y abre su jornada (CU-72, CU-31). */
    public function cajaAbierta(int $cajeroId): int
    {
        $this->db->table('cajas')->insert(['codigo' => 'T' . strtoupper(substr(self::sufijo(), -9)), 'denominacion' => 'Caja de prueba', 'estado' => 'Habilitada']);
        $caja = (int) $this->db->insertID();
        $this->db->table('asignaciones_caja')->insert(['caja_id' => $caja, 'usuario_id' => $cajeroId, 'desde' => Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d')]);

        return (new CajaService($this->db))->abrir($cajeroId);
    }

    /** Alternativas vigentes de la solicitud. @return list<array<string, mixed>> */
    public function alternativas(int $solicitudId): array
    {
        return $this->db->query(
            "SELECT a.* FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE e.solicitud_id = ? AND a.estado = 'Vigente' ORDER BY a.id",
            [$solicitudId],
        )->getResultArray();
    }

    private function exigirEstado(int $id, string $esperado): void
    {
        $estado = (new SolicitudService($this->db))->estado($id);
        if ($estado !== $esperado) {
            throw new \RuntimeException("La solicitud {$id} quedó en {$estado}; se esperaba {$esperado}.");
        }
    }
}
