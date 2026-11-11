<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Services\Clientes\ClienteService;
use App\Services\Fondos\FondosService;
use App\Services\Lineas\LineaService;
use App\Services\Solicitudes\ContratoService;
use App\Services\Solicitudes\EstructuracionService;
use App\Services\Solicitudes\EvaluacionService;
use App\Services\Solicitudes\LiquidacionService;
use App\Services\Solicitudes\SimulacionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * Datos de demostración de la morosidad: dos líneas nuevas, ocho clientes y un crédito liquidado por cliente, con los
 * vencimientos corridos para que cada uno tenga un atraso distinto (al día, dentro de la tolerancia de 3 días, recién
 * exigible, 5, 15, 45, 75 y 130 días). Solo para demostraciones y revisiones; no se ejecuta en la instalación.
 *
 *   docker compose exec -e SIGCCR_DB_GRUPO=migraciones app php spark db:seed DemostracionSeeder
 *
 * Cada crédito recorre el circuito real con los servicios del sistema (alta del cliente con su legajo, simulación,
 * solicitud, verificación, evaluación, confirmación de la alternativa pedida, constancia del contrato y liquidación por
 * transferencia). Después se simula el paso del tiempo como la consulta C.1 de la prueba integral y se ejecuta el
 * cálculo de punitorios. Se puede ejecutar una sola vez: si ya existen los clientes de demostración, no hace nada.
 */
class DemostracionSeeder extends Seeder
{
    /** Ingresos de todos los clientes: capacidad de pago de $ 600.000 con la afectación del 30 %. */
    private const INGRESOS = '2000000';

    /** Clientes: nombre, apellido, DNI, nacimiento, ocupación, empleador; línea, importe, cuotas y atraso de la cuota 1. */
    private const CREDITOS = [
        ['Valentina', 'Ruiz', '41222305', '1998-04-12', 'Diseñadora gráfica', 'Imprenta del Centro', 'ELECTRO', '300000', 6, -20],
        ['Joaquín', 'Medina', '38111404', '1994-09-03', 'Mecánico', 'Taller Medina Hnos.', 'MOTOS', '600000', 12, 2],
        ['Camila', 'Herrera', '39555103', '1996-01-25', 'Docente', 'Escuela Provincial N.º 412', 'PERSONAL', '400000', 12, 4],
        ['Lautaro', 'Vega', '36444205', '1991-07-18', 'Electricista', 'Cooperativa Eléctrica Apóstoles', 'HOGAR', '1200000', 24, 5],
        ['Micaela', 'Torres', '40777305', '1997-11-30', 'Administrativa', 'Yerbatera del Sur S.A.', 'ELECTRO', '250000', 9, 15],
        ['Franco', 'Molina', '35666406', '1990-03-08', 'Chofer', 'Transporte Apóstoles S.R.L.', 'MOTOS', '800000', 18, 45],
        ['Agustina', 'Castro', '37888517', '1993-05-21', 'Enfermera', 'Hospital de Área Apóstoles', 'PERSONAL', '700000', 24, 75],
        ['Nicolás', 'Suárez', '34999609', '1989-12-14', 'Comerciante', 'Almacén Suárez', 'HOGAR', '900000', 12, 130],
    ];

    /** Líneas que se agregan; MOTOS y PERSONAL se crean con los datos del Anexo A si no existen. */
    private const LINEAS = [
        'ELECTRO' => ['Electrodomésticos', 'Compra de electrodomésticos y tecnología', '50000', '800000', 3, 18, '75', '2', 'Fijo', '300', 18, 80],
        'HOGAR' => ['Mejoras del hogar', 'Refacción y ampliación de la vivienda', '200000', '3000000', 6, 36, '65', '2,5', 'Porcentual', '0,4', 21, 75],
        'MOTOS' => ['Motos', 'Financiación de motos nuevas y usadas', '300000', '1500000', 6, 24, '60', '3', 'Fijo', '500', 18, 75],
        'PERSONAL' => ['Personal', 'Préstamo personal de libre destino', '100000', '2000000', 3, 36, '70', '2', 'Porcentual', '0,5', 21, 70],
    ];

    private int $analista;
    private int $cajero;

    public function run(): void
    {
        if (getenv('SIGCCR_DB_GRUPO') !== 'migraciones') {
            CLI::error('Ejecutar con el grupo de migraciones: docker compose exec -e SIGCCR_DB_GRUPO=migraciones app php spark db:seed DemostracionSeeder');

            return;
        }
        if ($this->db->table('clientes')->where('cuit_cuil', self::cuil('27', self::CREDITOS[0][2]))->countAllResults() > 0) {
            CLI::write('Los datos de demostración ya están cargados.', 'yellow');

            return;
        }
        $this->analista = $this->cuentaDeRol('AnalistaCredito');
        $this->cajero = $this->cuentaDeRol('Cajero');
        $lineas = $this->lineas();
        $this->fondos();

        $resumen = [];
        foreach (self::CREDITOS as $n => [$nombre, $apellido, $dni, $nacimiento, $ocupacion, $empleador, $linea, $importe, $cuotas, $atraso]) {
            $prefijo = $n % 2 === 0 ? '27' : '20';
            $cuil = self::cuil($prefijo, $dni);
            $clienteId = $this->cliente($nombre, $apellido, $dni, $cuil, $nacimiento, $ocupacion, $empleador, $n);
            $creditoId = $this->credito($clienteId, $lineas[$linea], $importe, $cuotas, $dni, $cuil, $n);
            $this->atrasar($creditoId, $atraso);
            $c = $this->db->query('SELECT numero, capital, cantidad_cuotas FROM dbo.creditos WHERE id = ?', [$creditoId])->getRowArray();
            $resumen[] = [$apellido . ', ' . $nombre, $cuil, $linea, 'N.º ' . $c['numero'] . ': $ ' . number_format((float) $c['capital'], 0, ',', '.') . " en {$c['cantidad_cuotas']} cuotas", $atraso <= 0 ? 'vence en ' . -$atraso . ' días' : "{$atraso} días"];
            CLI::write("Crédito de {$apellido}, {$nombre} liquidado ({$linea}, atraso de la cuota 1: {$atraso} días).");
        }

        $this->calcularPunitorios();
        CLI::newLine();
        CLI::table($resumen, ['Cliente', 'CUIL', 'Línea', 'Crédito', 'Atraso de la cuota 1']);
        CLI::write('Datos de demostración cargados. Los punitorios se ven en Caja y cobranzas → Cobrar y en Cartera.', 'green');
    }

    /** Cuenta habilitada de un rol; si no hay, la del Administrador (los servicios no controlan el rol). */
    private function cuentaDeRol(string $rol): int
    {
        $fila = $this->db->query(
            "SELECT TOP 1 ca.id FROM dbo.cuentas_acceso ca JOIN dbo.usuarios_internos u ON u.id = ca.id JOIN dbo.roles r ON r.id = u.rol_id "
            . "WHERE r.codigo IN (?, 'Administrador') AND ca.estado = 'Habilitada' ORDER BY CASE WHEN r.codigo = ? THEN 0 ELSE 1 END, ca.id",
            [$rol, $rol],
        )->getRowArray();
        if ($fila === null) {
            throw new RuntimeException('No hay usuarios habilitados: instale el sistema y cree el Administrador antes de cargar la demostración.');
        }

        return (int) $fila['id'];
    }

    /**
     * Crea las líneas que falten y devuelve su id por código.
     *
     * @return array<string, int>
     */
    private function lineas(): array
    {
        $ids = [];
        $servicio = new LineaService($this->db);
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        foreach (self::LINEAS as $codigo => [$nombre, $descripcion, $minimo, $maximo, $cuotasMin, $cuotasMax, $tna, $otorgamiento, $tipoGasto, $gasto, $edadMin, $edadMax]) {
            $id = $this->db->query('SELECT TOP (1) id FROM dbo.lineas_credito WHERE codigo = ? OR nombre = ? ORDER BY CASE WHEN codigo = ? THEN 0 ELSE 1 END', [$codigo, $nombre, $codigo])->getRowArray()['id'] ?? null;
            if ($id === null) {
                $categorias = ['RelacionDependencia', 'JubiladoPensionado', 'Monotributista'];
                $id = $this->intentar(fn () => $servicio->registrar([
                    'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $descripcion, 'monto_minimo' => $minimo, 'monto_maximo' => $maximo,
                    'cuotas_minimas' => (string) $cuotasMin, 'cuotas_maximas' => (string) $cuotasMax, 'tna' => $tna, 'gasto_otorgamiento_pct' => $otorgamiento,
                    'tipo_gasto_administrativo' => $tipoGasto, $tipoGasto === 'Fijo' ? 'gasto_administrativo_cuota' : 'gasto_administrativo_pct' => $gasto,
                    'admite_persona_humana' => '1', 'edad_minima' => (string) $edadMin, 'edad_maxima' => (string) $edadMax, 'categorias' => $categorias,
                    'requisitos' => ['RelacionDependencia' => ['RECIBO_SUELDO'], 'JubiladoPensionado' => ['RECIBO_HABERES'], 'Monotributista' => ['CONST_MONOTRIBUTO']],
                    'vigencia_inicio' => $hoy->format('Y-m-d'), 'vigencia_fin' => $hoy->addYears(2)->format('Y-m-d'),
                ]), "línea {$codigo}");
                CLI::write("Línea {$codigo} registrada.");
            }
            $ids[$codigo] = (int) $id;
        }

        return $ids;
    }

    /** Aporte de capital si el disponible libre no alcanza para reservar los ocho desembolsos (D-37). */
    private function fondos(): void
    {
        $fondos = new FondosService($this->db);
        $necesario = array_sum(array_map(static fn ($c) => (float) $c[7], self::CREDITOS));
        $libre = (float) ($fondos->disponibilidad()['libre'] ?? 0);
        if ($libre >= $necesario) {
            return;
        }
        $this->intentar(fn () => $fondos->registrarMovimiento([
            'tipo' => 'AporteCapital', 'caracter' => 'Ingreso', 'importe' => (string) ceil($necesario - $libre + 1000000),
            'fecha' => Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y'), 'concepto' => 'Aporte de capital para los datos de demostración',
        ], $this->cajero), 'aporte de fondos');
        CLI::write('Aporte de capital registrado para los desembolsos de la demostración.');
    }

    private function cliente(string $nombre, string $apellido, string $dni, string $cuil, string $nacimiento, string $ocupacion, string $empleador, int $n): int
    {
        $localidad = $this->db->query(
            "SELECT TOP 1 l.id, l.provincia_id FROM dbo.localidades l JOIN dbo.provincias p ON p.id = l.provincia_id WHERE l.nombre = N'Apóstoles' AND p.nombre = N'Misiones'",
        )->getRowArray();
        if ($localidad === null) {
            throw new RuntimeException('Falta la geografía (Misiones, Apóstoles): ejecute la instalación completa.');
        }
        $correo = mb_strtolower(self::sinAcentos($nombre . '.' . $apellido)) . '@demo.local';
        $archivos = [
            ['codigo' => 'DOC_IDENTIDAD', 'nombre' => "DNI_{$apellido}.pdf"] + self::pdf("DNI de {$nombre} {$apellido}"),
            ['codigo' => 'RECIBO_SUELDO', 'nombre' => "recibos_sueldo_{$apellido}.pdf"] + self::pdf("Recibos de sueldo de {$nombre} {$apellido}"),
        ];

        return (int) $this->intentar(fn () => (new ClienteService($this->db))->registrar([
            'tipo_cliente' => 'PersonaHumana', 'nombre' => $nombre, 'apellido' => $apellido, 'tipo_documento' => 'DNI', 'nro_documento' => $dni,
            'fecha_nacimiento' => $nacimiento, 'estado_civil' => $n % 3 === 0 ? 'Casado' : 'Soltero', 'nacionalidad' => 'Argentina',
            'ocupacion' => $ocupacion, 'empleador' => $empleador, 'categoria_ingresos' => 'RelacionDependencia', 'ingresos_declarados' => self::INGRESOS,
            'cuit_cuil' => $cuil, 'correo' => $correo, 'telefono' => '3758 43-' . (3000 + $n * 11), 'provincia_id' => (string) $localidad['provincia_id'],
            'localidad_id' => (string) $localidad['id'], 'codigo_postal' => '3350', 'domicilio' => 'Calle Demostración ' . (100 + $n * 25),
            'persona_expuesta' => 'No', 'acepta_politica' => '1', 'acepta_comunicaciones' => '1',
        ], $archivos, $this->analista), "cliente {$apellido}");
    }

    /** Circuito completo de CU-21 a CU-27 con los servicios del sistema; devuelve el id del crédito. */
    private function credito(int $clienteId, int $lineaId, string $importe, int $cuotas, string $dni, string $cuil, int $n): int
    {
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $simulacion = $this->intentar(fn () => (new SimulacionService($this->db))->simular($clienteId, $lineaId, $importe, (string) $cuotas, $this->analista), 'simulación');
        $solicitudes = new SolicitudService($this->db);
        $id = (int) $this->intentar(fn () => $solicitudes->registrar((int) $simulacion, [], $this->analista), 'solicitud')['id'];

        $s = $solicitudes->obtener($id);
        $verificaciones = [];
        foreach ($s['documentos'] as $d) {
            $verificaciones[(int) $d['id']] = ['resultado' => 'Conforme', 'motivo' => '', 'observacion' => ''];
        }
        $this->intentar(fn () => $solicitudes->verificar($id, (int) $s['version_fila'], 'Verificada', $verificaciones, '', '', $this->analista), 'verificación');
        (new EvaluacionService($this->db))->evaluar($id);
        $estado = $solicitudes->estado($id);
        if ($estado !== 'PendienteSeleccionPlan') {
            throw new RuntimeException("La solicitud {$id} quedó en {$estado} después de la evaluación: revise los parámetros de evaluación.");
        }

        $alternativa = $this->db->query(
            "SELECT TOP 1 a.id FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id JOIN dbo.solicitudes_credito s ON s.id = e.solicitud_id "
            . "WHERE s.id = ? AND a.estado = 'Vigente' ORDER BY CASE WHEN a.importe = s.importe AND a.cantidad_cuotas = s.cantidad_cuotas THEN 0 ELSE 1 END, a.id",
            [$id],
        )->getRowArray();
        if ($alternativa === null) {
            throw new RuntimeException("La solicitud {$id} no tiene alternativas vigentes para confirmar.");
        }
        $version = (int) $solicitudes->obtener($id)['version_fila'];
        $this->intentar(fn () => (new EstructuracionService($this->db))->confirmar($id, (int) $alternativa['id'], $version, [
            'fecha_conformidad' => $hoy->format('d/m/Y'), 'medio_conformidad' => 'PresencialFirma', 'observaciones' => 'Datos de demostración',
        ], $this->analista), 'confirmación de la alternativa');

        $this->intentar(fn () => (new ContratoService($this->db))->registrarConstancia(
            $id,
            ['nombre' => 'contrato_firmado.pdf'] + self::pdf("Contrato firmado de la solicitud {$id}"),
            $hoy->format('Y-m-d'),
            $this->analista,
        ), 'constancia del contrato');

        $liquidacion = new LiquidacionService($this->db);
        $datos = $liquidacion->datos($id);
        $r = $this->intentar(fn () => $liquidacion->liquidar($id, (int) $datos['version_fila'], [
            'documento_verificado' => $dni, 'conformidad' => '1', 'cbu' => self::cbu($n), 'cuit_titular' => $cuil,
            'evidencia_titularidad' => 'Constancia de CBU presentada por el titular (demostración)', 'comprobante' => sprintf('DES-DEMO-%02d', $n + 1),
            'fecha_transferencia' => $hoy->format('d/m/Y'), 'importe_transferencia' => str_replace('.', ',', $datos['calculo']['neto']->toFixed(2)),
        ], $this->cajero), 'liquidación');

        return (int) ($r['credito_id'] ?? $this->db->query('SELECT credito_id FROM dbo.liquidaciones WHERE solicitud_id = ?', [$id])->getRow()->credito_id);
    }

    /**
     * Simula el paso del tiempo (consulta C.1): corre la liquidación, el contrato y los vencimientos para que la cuota 1
     * tenga el atraso indicado hoy (negativo: faltan esos días para su vencimiento).
     */
    private function atrasar(int $creditoId, int $atraso): void
    {
        $hoy = Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d');
        $vencimiento = (string) $this->db->query('SELECT vencimiento FROM dbo.cuotas WHERE credito_id = ? AND numero = 1', [$creditoId])->getRow()->vencimiento;
        $dias = (int) ((strtotime(substr($vencimiento, 0, 10)) - strtotime($hoy)) / 86400) + $atraso;
        if ($dias === 0) {
            return;
        }
        $this->db->query('UPDATE dbo.cuotas SET vencimiento = DATEADD(day, ?, vencimiento) WHERE credito_id = ?', [-$dias, $creditoId]);
        $this->db->query('UPDATE dbo.creditos SET fecha_liquidacion = DATEADD(day, ?, fecha_liquidacion) WHERE id = ?', [-$dias, $creditoId]);
        $this->db->query('UPDATE dbo.liquidaciones SET fecha = DATEADD(day, ?, fecha) WHERE credito_id = ?', [-$dias, $creditoId]);
        $this->db->query(
            'UPDATE k SET k.fecha_recepcion = DATEADD(day, ?, k.fecha_recepcion) FROM dbo.contratos_prestamo k '
            . 'JOIN dbo.liquidaciones q ON q.solicitud_id = k.solicitud_id WHERE q.credito_id = ? AND k.fecha_recepcion IS NOT NULL',
            [-$dias, $creditoId],
        );
    }

    /**
     * Cálculo de punitorios sobre los días simulados: la configuración inicial de morosidad e IVA rige desde un año
     * antes y se vuelve a procesar el día de ayer (el proceso calcula cada día una sola vez, RN-53).
     */
    private function calcularPunitorios(): void
    {
        $this->db->query(
            "UPDATE dbo.versiones_parametros SET vigente_desde = DATEADD(year, -1, vigente_desde) "
            . "WHERE tipo IN ('Morosidad', 'Estructuracion') AND numero = 1 AND vigente_desde > DATEADD(month, -6, SYSDATETIMEOFFSET())",
        );
        $ayer = Time::now(config('Sigccr')->zonaNegocio)->subDays(1)->format('Y-m-d');
        $this->db->query("DELETE FROM dbo.ejecuciones_proceso WHERE proceso = 'cartera:punitorios' AND fecha_referencia >= ?", [$ayer]);
        command('cartera:punitorios');
    }

    /** Ejecuta una operación del circuito y, si una regla la rechaza, informa cuál y en qué paso. */
    private function intentar(callable $operacion, string $paso): mixed
    {
        try {
            return $operacion();
        } catch (ValidacionException $e) {
            throw new RuntimeException("Demostración, {$paso}: " . implode(' ', $e->errores));
        } catch (ReglaNegocioException $e) {
            throw new RuntimeException("Demostración, {$paso}: " . $e->getMessage());
        }
    }

    /**
     * PDF mínimo en un archivo temporal (el legajo lo mueve a su carpeta).
     *
     * @return array{temporal: string, tamano: int}
     */
    private static function pdf(string $texto): array
    {
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 100]>>endobj\n% DOCUMENTO DE DEMOSTRACION - SIN VALIDEZ: {$texto}\n"
            . "trailer<</Root 1 0 R>>\n%%EOF\n";
        $ruta = tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($ruta, $contenido);

        return ['temporal' => $ruta, 'tamano' => strlen($contenido)];
    }

    /** CUIL con su dígito verificador. Los DNI de la demostración dan 0: el simulador de la Central de Deudores no informa deudas. */
    public static function cuil(string $prefijo, string $dni): string
    {
        $base = $prefijo . str_pad($dni, 8, '0', STR_PAD_LEFT);
        $suma = 0;
        foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) {
            $suma += (int) $base[$i] * $peso;
        }
        $resto = 11 - $suma % 11;

        return $base . ($resto === 11 ? '0' : ($resto === 10 ? '9' : (string) $resto));
    }

    /** CBU válido de un banco de prueba (072, sucursal 0045), distinto por cliente. */
    private static function cbu(int $n): string
    {
        $bloque = '0720045';
        $cuenta = str_pad((string) (90000001 + $n * 137), 13, '0', STR_PAD_LEFT);

        return $bloque . self::digito($bloque, [7, 1, 3, 9, 7, 1, 3]) . $cuenta . self::digito($cuenta, [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3]);
    }

    /** @param list<int> $pesos */
    private static function digito(string $numero, array $pesos): string
    {
        $suma = 0;
        foreach ($pesos as $i => $peso) {
            $suma += (int) $numero[$i] * $peso;
        }

        return (string) ((10 - $suma % 10) % 10);
    }

    private static function sinAcentos(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n']);
    }
}
