<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\Validacion\ValidadorParametrosCampanas;
use App\Services\Caja\CajaService;
use App\Services\Caja\CancelacionAnticipadaService;
use App\Services\Caja\CobroService;
use App\Services\Campanas\CampanaService;
use App\Services\Cartera\CondonacionService;
use App\Services\Cartera\RefinanciacionService;
use App\Services\Clientes\DerechosTitularService;
use App\Services\Clientes\PreRegistroService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use RuntimeException;
use Throwable;

/**
 * Datos para una presentación del sistema: carga la demostración de la morosidad (DemostracionSeeder) si falta y le
 * suma un escenario de cada parte del sistema, para que todas las pantallas y los reportes tengan qué mostrar:
 *
 * - solicitudes en cada estado del trámite: pendiente de verificación, de documentación, de selección de plan, de
 *   resolución gerencial y de evaluación de contingencia, aprobada pendiente de liquidación y rechazadas (documental y
 *   crediticia), con clientes en relación de dependencia, jubilados y monotributistas;
 * - más créditos al día en la cartera;
 * - una jornada de caja con cobros completos, un pago parcial, una cancelación anticipada y un cobro anulado que deja un
 *   saldo a favor, cerrada con su conciliación;
 * - una refinanciación aplicada y otra propuesta pendiente de resolución, y una condonación de punitorios;
 * - la proyección de fondos con una campaña activa y sus ofertas (y una rechazada), y el régimen informativo del mes
 *   anterior;
 * - pre-registros del portal pendientes de validación y pedidos de los derechos del titular (Ley 25.326).
 *
 *   docker compose exec -e SIGCCR_DB_GRUPO=migraciones app php spark db:seed PresentacionSeeder
 *
 * Todo pasa por los servicios del sistema, con sus reglas y su auditoría. Cada escenario es independiente: si una regla
 * lo rechaza (por ejemplo, porque la caja del Cajero ya está abierta), se informa y se sigue con el siguiente. Se puede
 * ejecutar una sola vez. Los clientes con el portal habilitado no tienen una contraseña conocida: se define con
 * «¿Olvidó su contraseña?» y el correo llega a Mailpit.
 */
class PresentacionSeeder extends DemostracionSeeder
{
    /**
     * Clientes de la presentación: nombre, apellido, DNI, prefijo del CUIL, nacimiento, ocupación, empleador, categoría,
     * ingresos, línea, importe y cuotas. El último dígito del CUIL decide la respuesta del simulador de la Central de
     * Deudores: 0 sin deudas, 3 situación 3, 5 situación 5 y 9 sin respuesta.
     */
    private const CLIENTES = [
        'verificacion' => ['Lucía', 'Fernández', '42111008', '27', '1999-06-14', 'Vendedora', 'Comercial Del Sol', 'RelacionDependencia', '1500000', 'ELECTRO', '200000', 6],
        'documentacion' => ['Tomás', 'Romero', '33222008', '20', '1987-02-09', 'Gasista matriculado', 'Por cuenta propia', 'Monotributista', '1500000', 'PERSONAL', '300000', 12],
        'rechazo_documental' => ['Rocío', 'Díaz', '42111011', '27', '1998-10-02', 'Recepcionista', 'Hotel Apóstoles', 'RelacionDependencia', '1400000', 'MOTOS', '500000', 12],
        'seleccion' => ['Ramón', 'Acuña', '33222011', '20', '1959-08-21', 'Jubilado docente', 'ANSES', 'JubiladoPensionado', '1200000', 'PERSONAL', '400000', 12],
        'aprobada' => ['Paula', 'Ríos', '42111025', '27', '1992-12-05', 'Contadora', 'Estudio Ríos & Asoc.', 'RelacionDependencia', '2500000', 'HOGAR', '800000', 18],
        'rechazo_crediticio' => ['Diego', 'Sosa', '37333009', '20', '1993-03-17', 'Albañil', 'Constructora Misiones', 'RelacionDependencia', '1300000', 'ELECTRO', '150000', 6],
        'resolucion' => ['Carla', 'Ojeda', '38666001', '27', '1995-07-29', 'Peluquera', 'Salón Carla', 'RelacionDependencia', '1600000', 'MOTOS', '400000', 12],
        'contingencia' => ['Hernán', 'Godoy', '36777005', '20', '1991-11-11', 'Técnico en refrigeración', 'Frío Sur', 'RelacionDependencia', '1700000', 'PERSONAL', '250000', 6],
        'cartera_1' => ['Gabriela', 'Paz', '42111039', '27', '1988-04-03', 'Farmacéutica', 'Farmacia Central', 'RelacionDependencia', '3000000', 'PERSONAL', '1000000', 24],
        'cartera_2' => ['Sergio', 'Luna', '33222025', '20', '1984-09-26', 'Supervisor de planta', 'Yerbatera del Sur S.A.', 'RelacionDependencia', '3200000', 'HOGAR', '1500000', 36],
    ];

    /** Documentación de ingresos de cada categoría (la que exigen las líneas de la demostración). */
    private const DOCUMENTOS = ['RelacionDependencia' => ['RECIBO_SUELDO'], 'JubiladoPensionado' => ['RECIBO_HABERES'], 'Monotributista' => ['CONST_MONOTRIBUTO']];

    private int $gerente;

    /** @var list<array{0: string, 1: string}> */
    private array $resultados = [];

    public function run(): void
    {
        if (getenv('SIGCCR_DB_GRUPO') !== 'migraciones') {
            CLI::error('Ejecutar con el grupo de migraciones: docker compose exec -e SIGCCR_DB_GRUPO=migraciones app php spark db:seed PresentacionSeeder');

            return;
        }
        $primero = self::CLIENTES['verificacion'];
        if ($this->db->table('clientes')->where('cuit_cuil', self::cuil($primero[3], $primero[2]))->countAllResults() > 0) {
            CLI::write('Los datos de la presentación ya están cargados.', 'yellow');

            return;
        }
        parent::run();
        CLI::newLine();
        CLI::write('Escenarios de la presentación', 'green');

        $this->analista = $this->cuentaDeRol('AnalistaCredito');
        $this->cajero = $this->cuentaDeRol('Cajero');
        $this->gerente = $this->cuentaDeRol('Gerente');
        $lineas = $this->lineas();
        $this->aporte('5000000');
        $demo = $this->clientesDemostracion();

        $this->escenario('Solicitudes en cada estado del trámite', fn () => $this->solicitudes($lineas));
        $this->escenario('Jornada de caja: cobros, pago parcial, anulación y cancelación anticipada', fn () => $this->caja($demo));
        $this->escenario('Refinanciación aplicada (Suárez) y propuesta pendiente (Castro)', fn () => $this->refinanciaciones($demo));
        $this->escenario('Condonación de punitorios (Molina)', fn () => $this->condonacion($demo['Molina']));
        $this->escenario('Proyección de fondos y campaña con ofertas', fn () => $this->campana($demo));
        $this->escenario('Régimen informativo del mes anterior', fn () => $this->regimen());
        $this->escenario('Pre-registros del portal', fn () => $this->preRegistros());
        $this->escenario('Derechos del titular (Ley 25.326)', fn () => $this->derechos($demo));

        CLI::newLine();
        CLI::table($this->resultados, ['Escenario', 'Resultado']);
        CLI::write('Datos de la presentación cargados. Para enviar los correos pendientes a Mailpit: php spark comunicaciones:enviar', 'green');
    }

    /**
     * Resultado de cada escenario de la última carga.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function resultados(): array
    {
        return $this->resultados;
    }

    /** Ejecuta un escenario y registra su resultado sin detener la carga. */
    private function escenario(string $nombre, callable $paso): void
    {
        try {
            $detalle = (string) $paso();
            $this->resultados[] = [$nombre, 'OK' . ($detalle === '' ? '' : ': ' . $detalle)];
            CLI::write("✓ {$nombre}" . ($detalle === '' ? '' : " — {$detalle}"));
        } catch (Throwable $e) {
            $this->resultados[] = [$nombre, 'Omitido: ' . $e->getMessage()];
            CLI::write("✗ {$nombre}: {$e->getMessage()}", 'yellow');
        }
    }

    /**
     * Cliente y crédito de cada cliente de la demostración, por apellido.
     *
     * @return array<string, array{cliente: int, credito: int}>
     */
    private function clientesDemostracion(): array
    {
        $r = [];
        foreach (self::CREDITOS as $n => $c) {
            $fila = $this->db->query(
                'SELECT TOP 1 c.id AS cliente, k.id AS credito FROM dbo.clientes c JOIN dbo.creditos k ON k.cliente_id = c.id WHERE c.cuit_cuil = ? ORDER BY k.id',
                [self::cuil($n % 2 === 0 ? '27' : '20', $c[2])],
            )->getRowArray();
            if ($fila !== null) {
                $r[$c[1]] = ['cliente' => (int) $fila['cliente'], 'credito' => (int) $fila['credito']];
            }
        }

        return $r;
    }

    /** Aporte de capital para los desembolsos y las reservas de la presentación. */
    private function aporte(string $importe): void
    {
        $this->intentar(fn () => (new FondosService($this->db))->registrarMovimiento([
            'tipo' => 'AporteCapital', 'caracter' => 'Ingreso', 'importe' => $importe,
            'fecha' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'), 'concepto' => 'Aporte de capital para la presentación',
        ], $this->cajero), 'aporte de fondos');
    }

    /** @param array<string, int> $lineas */
    private function solicitudes(array $lineas): string
    {
        $estados = [];
        $n = 20;
        foreach (self::CLIENTES as $clave => [$nombre, $apellido, $dni, $prefijo, $nacimiento, $ocupacion, $empleador, $categoria, $ingresos, $linea, $importe, $cuotas]) {
            $n++;
            $cuil = self::cuil($prefijo, $dni);
            $extra = ['categoria_ingresos' => $categoria, 'ingresos_declarados' => $ingresos] + ($categoria === 'Monotributista' ? ['categoria_monotributo' => 'K'] : []);
            $clienteId = $this->cliente($nombre, $apellido, $dni, $cuil, $nacimiento, $ocupacion, $empleador, $n, $extra, self::DOCUMENTOS[$categoria]);
            $id = $this->solicitud($clienteId, $lineas[$linea], $importe, $cuotas);
            $estado = match ($clave) {
                'verificacion' => 'PendienteVerificacion',
                'documentacion' => $this->verificarYEvaluar($id, ['CONST_MONOTRIBUTO' => ['Observado', 'Ilegible']], 'Observada', 'Presentar una constancia de inscripción legible y actualizada.'),
                'rechazo_documental' => $this->verificarYEvaluar($id, ['RECIBO_SUELDO' => ['Rechazado', 'Inconsistente']], 'Rechazada', '', 'El recibo de sueldo no corresponde al empleador declarado.'),
                default => $this->verificarYEvaluar($id),
            };
            if ($clave === 'aprobada' || str_starts_with($clave, 'cartera')) {
                $this->confirmarPedida($id);
                $estado = 'Aprobada';
            }
            if (str_starts_with($clave, 'cartera')) {
                $this->constancia($id);
                $this->liquidar($id, $dni, $cuil, $n);
                $estado = 'Liquidada';
            }
            $estados[] = "{$apellido}: " . ($estado === 'Aprobada' ? (new SolicitudService($this->db))->estado($id) : $estado);
        }

        return implode(', ', $estados);
    }

    /** @param array<string, array{cliente: int, credito: int}> $demo */
    private function caja(array $demo): string
    {
        $cajas = new CajaService($this->db);
        $situacion = $cajas->situacion($this->cajero);
        if ($situacion['jornada'] !== null) {
            throw new RuntimeException('el Cajero ya tiene una caja abierta: ciérrela y vuelva a ejecutar la carga, o registre los cobros en la presentación.');
        }
        if ($situacion['asignacion'] === null) {
            $id = $this->db->query("SELECT TOP 1 id FROM dbo.cajas WHERE codigo = 'DEMO-01'")->getRowArray()['id'] ?? null;
            $cajaId = $id === null
                ? $this->intentar(fn () => $cajas->registrar(['codigo' => 'DEMO-01', 'denominacion' => 'Caja de la presentación', 'ubicacion' => 'Sucursal Apóstoles', 'observaciones' => '']), 'caja')
                : (int) $id;
            $this->intentar(fn () => $cajas->asignar((int) $cajaId, $this->cajero, '', ''), 'asignación de la caja');
        }
        $this->intentar(fn () => $cajas->abrir($this->cajero), 'apertura de la caja');

        $cobros = new CobroService($this->db);
        $hechos = [];
        $operacion = 0;
        $cobrar = function (string $apellido, string $importe, string $medio) use ($demo, $cobros, &$operacion): array {
            $operacion++;
            if ($importe === '') {
                // Total actualizado de la cuota 1, con los punitorios hasta hoy
                $o = $cobros->obligaciones($demo[$apellido]['cliente']);
                $cuota = $o['creditos'][$demo[$apellido]['credito']]['cuotas'][0] ?? null;
                if ($cuota === null) {
                    throw new RuntimeException("{$apellido} no tiene cuotas pendientes.");
                }
                $importe = str_replace('.', ',', $cuota['total']->toFixed(2));
            }

            return $this->intentar(fn () => $cobros->registrar($this->cajero, $demo[$apellido]['cliente'], [$demo[$apellido]['credito'] => [1]], $importe, [
                ['medio' => $medio, 'importe' => $importe, 'numero_operacion' => sprintf('DEMO-%04d', $operacion)],
            ]), "cobro de {$apellido}");
        };
        foreach ([['Medina', '', 'Transferencia'], ['Vega', '', 'TarjetaDebito'], ['Torres', '15000', 'Transferencia']] as [$apellido, $importe, $medio]) {
            $r = $cobrar($apellido, $importe, $medio);
            $hechos[] = "{$apellido} $ {$r['importe']}" . ($r['parcial'] !== null ? ' (parcial)' : '');
        }
        $anulado = $cobrar('Herrera', '', 'TarjetaCredito');
        $a = $this->intentar(fn () => $cobros->anular((int) $anulado['cobro_id'], 'Cobro registrado sobre la cuota equivocada (demostración).', true, $this->gerente), 'anulación del cobro');
        $hechos[] = "Herrera anulado (saldo a favor $ {$a['saldo_a_favor']})";

        $cancelacion = new CancelacionAnticipadaService($this->db);
        $calculo = $cancelacion->calcular($demo['Ruiz']['credito']);
        $total = str_replace('.', ',', $calculo['calculo']['total']->toFixed(2));
        $this->intentar(fn () => $cancelacion->registrar($this->cajero, $demo['Ruiz']['credito'], $total, 'Transferencia', 'DEMO-CANC-01'), 'cancelación anticipada');
        $hechos[] = "Ruiz cancelación anticipada $ {$total}";

        $s = $cajas->situacion($this->cajero);
        $registrado = [];
        foreach ($this->db->query(
            "SELECT medio, SUM(importe) AS total FROM dbo.movimientos_caja WHERE jornada_caja_id = ? AND tipo = 'Cobro' GROUP BY medio",
            [(int) $s['jornada']['id']],
        )->getResultArray() as $f) {
            $registrado[(string) $f['medio']] = str_replace('.', ',', number_format((float) $f['total'], 2, '.', ''));
        }
        $this->intentar(fn () => $cajas->cerrar($this->cajero, (int) $s['jornada']['version_fila'], $registrado, []), 'cierre de la caja');
        $hechos[] = 'caja cerrada y conciliada';

        return implode('; ', $hechos);
    }

    /** @param array<string, array{cliente: int, credito: int}> $demo */
    private function refinanciaciones(array $demo): string
    {
        $servicio = new RefinanciacionService($this->db);
        $hoy = Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d');

        $sim = $this->intentar(fn () => $servicio->simular($this->analista, $demo['Suárez']['cliente'], [$demo['Suárez']['credito']], '45', '18'), 'simulación de la refinanciación');
        $p = $this->intentar(fn () => $servicio->registrarPropuesta($this->analista, $sim), 'propuesta de refinanciación');
        $this->intentar(fn () => $servicio->resolver($this->gerente, (int) $p['id'], (int) $p['version'], 'Aprobada', 'Cliente con voluntad de pago y capacidad suficiente para el nuevo plan.'), 'aprobación de la propuesta');
        $version = (int) $servicio->propuesta((int) $p['id'])['version_fila'];
        $r = $this->intentar(fn () => $servicio->aplicar($this->analista, (int) $p['id'], $version, $hoy, 'Presencial, con firma de la aceptación', 'Datos de la presentación'), 'aplicación de la refinanciación');
        if (($r['ok'] ?? false) !== true) {
            throw new RuntimeException((string) $r['mensaje']);
        }

        $sim = $this->intentar(fn () => $servicio->simular($this->analista, $demo['Castro']['cliente'], [$demo['Castro']['credito']], '50', '24'), 'simulación de la segunda refinanciación');
        $q = $this->intentar(fn () => $servicio->registrarPropuesta($this->analista, $sim), 'segunda propuesta');

        return "propuesta N.º {$p['numero']} aplicada (crédito N.º {$r['credito']}); N.º {$q['numero']} pendiente de resolución";
    }

    /** @param array{cliente: int, credito: int} $molina */
    private function condonacion(array $molina): string
    {
        $servicio = new CondonacionService($this->db);
        $o = $servicio->obligaciones($molina['cliente']);
        if ($o['cuotas'] === []) {
            throw new RuntimeException('el cliente no tiene punitorios pendientes.');
        }
        $ids = array_map(static fn ($q) => (int) $q['id'], $o['cuotas']);
        $punitorios = array_sum(array_map(static fn ($q) => (float) $q['punitorio_pendiente'], $o['cuotas']));
        $importe = floor($punitorios * (float) $o['limite'] * 50) / 100;
        if ($importe < 0.01) {
            throw new RuntimeException('los punitorios pendientes son demasiado bajos para condonar.');
        }
        $texto = str_replace('.', ',', number_format($importe, 2, '.', ''));
        $this->intentar(fn () => $servicio->condonar($this->gerente, $molina['cliente'], $ids, $texto, 'Atraso por enfermedad acreditada con certificado médico (demostración).'), 'condonación');

        return "$ {$texto} de punitorios";
    }

    /**
     * Proyección de fondos con una configuración de campañas que admite a los clientes al día de la demostración, con el
     * portal habilitado (sin contraseña conocida: se define con «¿Olvidó su contraseña?»).
     *
     * @param array<string, array{cliente: int, credito: int}> $demo
     */
    private function campana(array $demo): string
    {
        foreach (['Medina', 'Vega', 'Herrera', 'Torres'] as $apellido) {
            if (!isset($demo[$apellido])) {
                continue;
            }
            $this->db->query(
                "UPDATE ca SET ca.estado = 'Habilitada', ca.hash_contrasena = ?, ca.correo_verificado = 1, ca.version_fila = ca.version_fila + 1 "
                . "FROM dbo.cuentas_acceso ca JOIN dbo.clientes c ON c.cuenta_cliente_id = ca.id WHERE c.id = ? AND ca.estado = 'PendienteActivacion'",
                [password_hash(bin2hex(random_bytes(24)), PASSWORD_ARGON2ID), $demo[$apellido]['cliente']],
            );
        }
        $parametros = new ParametroService($this->db);
        $vigente = $parametros->vigente('Campanas');
        $linea = (int) $this->db->query("SELECT TOP 1 id FROM dbo.lineas_credito WHERE codigo = 'PERSONAL'")->getRow()->id;
        $f = array_merge(ValidadorParametrosCampanas::aFormulario(ValidadorParametrosCampanas::completar($vigente['valores'])), [
            'umbral_disponibilidad' => '0', 'linea_id' => (string) $linea, 'capital_maximo' => '3000000', 'cantidad_maxima_ofertas' => '50',
            'categorias_admitidas' => ['RelacionDependencia'], 'antiguedad_minima_meses' => '0', 'morosidad_maxima_dias' => '30',
            'admite_obligaciones_vigentes' => '1', 'mensaje' => 'Oferta especial de fin de año', 'duracion_dias' => '15', 'cierre_por_disponibilidad' => '',
        ]);
        $p = ValidadorParametrosCampanas::normalizar($f);
        $errores = ValidadorParametrosCampanas::validar($p, [$linea]);
        if ($errores !== []) {
            throw new RuntimeException('parámetros de campañas: ' . implode(' ', $errores));
        }
        $this->intentar(fn () => $parametros->registrarVersion('Campanas', $p, Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d'), 'Configuración para la presentación del sistema', $this->gerente), 'parámetros de campañas');

        $r = (new FondosService($this->db))->proyectar('fondos:proyectar');
        $detalle = 'proyección registrada; ' . ($r['campana'] ?? 'sin campaña');
        $oferta = $this->db->query(
            "SELECT TOP 1 o.id, o.cliente_id, o.version_fila FROM dbo.ofertas o JOIN dbo.campanas k ON k.id = o.campana_id WHERE k.estado = 'Activa' AND o.estado = 'Vigente' ORDER BY o.id DESC",
        )->getRowArray();
        if ($oferta !== null) {
            $this->intentar(fn () => (new CampanaService($this->db))->rechazar((int) $oferta['cliente_id'], (int) $oferta['id'], (int) $oferta['version_fila']), 'rechazo de una oferta');
            $detalle .= '; una oferta rechazada';
        }

        return $detalle;
    }

    private function regimen(): string
    {
        command('regimen:generar');
        $r = $this->db->query('SELECT TOP 1 periodo, cantidad_deudores FROM dbo.regimenes_informativos ORDER BY id DESC')->getRowArray();

        return $r === null ? 'sin período a generar' : "período {$r['periodo']} con {$r['cantidad_deudores']} deudores";
    }

    private function preRegistros(): string
    {
        $servicio = new PreRegistroService($this->db);
        $personas = [
            ['Valeria', 'Maidana', '42111053', '27', true],
            ['Julián', 'Cabrera', '33222039', '20', false],
        ];
        $hechos = [];
        foreach ($personas as [$nombre, $apellido, $dni, $prefijo, $verificar]) {
            $correo = mb_strtolower(self::sinAcentos($nombre . '.' . $apellido)) . '@prueba.local';
            $this->intentar(fn () => $servicio->registrar([
                'nombre' => $nombre, 'apellido' => $apellido, 'tipo_documento' => 'DNI', 'nro_documento' => $dni,
                'cuit_cuil' => self::cuil($prefijo, $dni), 'correo' => $correo, 'telefono' => '3758 40-1234', 'acepta_politica' => '1',
            ], '127.0.0.1'), "pre-registro de {$apellido}");
            if ($verificar) {
                // El enlace del correo no se conoce: se reemplaza por uno propio y se verifica como lo haría la persona
                $codigo = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $this->db->query("UPDATE dbo.pre_registros SET hash_codigo = ? WHERE correo = ? AND estado = 'PendienteVerificacion'", [hash('sha256', $codigo), $correo]);
                $this->intentar(fn () => $servicio->verificar($codigo), "verificación del pre-registro de {$apellido}");
            }
            $hechos[] = $apellido . ($verificar ? ' (pendiente de validación)' : ' (sin verificar el correo)');
        }

        return implode(', ', $hechos);
    }

    /** @param array<string, array{cliente: int, credito: int}> $demo */
    private function derechos(array $demo): string
    {
        $servicio = new DerechosTitularService($this->db);
        $servicio->registrar($demo['Ruiz']['cliente'], ['tipo' => 'Acceso', 'detalle' => 'Solicito conocer qué datos personales míos conserva la entidad y con qué finalidad.'], 'Sucursal');
        $servicio->registrar($demo['Molina']['cliente'], ['tipo' => 'Rectificacion', 'detalle' => 'Mi domicilio cambió: ahora vivo en Calle San Martín 1450, Apóstoles.'], 'Sucursal');

        return 'un pedido de acceso y uno de rectificación, pendientes';
    }
}
