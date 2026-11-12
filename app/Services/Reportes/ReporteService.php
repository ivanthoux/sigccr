<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Caja\ReglasCaja;
use App\Libraries\Campanas\ReglasCampanas;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Reportes\Indicadores;
use App\Libraries\Reportes\Periodo;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Validacion\ValidadorParametrosCampanas;
use App\Services\BaseService;
use App\Services\Cartera\RefinanciacionService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use CodeIgniter\I18n\Time;

/**
 * SS-09 Reportes (D-44): tablero gerencial (CU-55), informe de caja diaria (CU-56) y reportes de gestión (CU-65 a
 * CU-71). Solo lee información: ninguna consulta modifica el estado de las operaciones (RN-47). Cada reporte devuelve
 * la misma estructura, que la vista presenta en pantalla e imprime y que se exporta a Excel:
 *   titulo, periodo, referencia, filtros, aviso, kpis [[etiqueta, valor, tipo]], secciones [[titulo, columnas, filas]].
 * Los saldos (deuda, cartera, morosidad) son a la fecha de generación; el período rige los movimientos (otorgamientos,
 * cobros, solicitudes, propuestas, campañas) y su evolución.
 */
class ReporteService extends BaseService
{
    public const REPORTES = [
        'cartera' => ['CU-65', 'Reporte de cartera crediticia', 'reportes.cartera'],
        'morosidad' => ['CU-66', 'Reporte de morosidad', 'reportes.morosidad'],
        'cobranzas' => ['CU-67', 'Reporte de cobranzas y recupero', 'reportes.cobranzas'],
        'solicitudes' => ['CU-68', 'Reporte de solicitudes y otorgamientos', 'reportes.solicitudes'],
        'refinanciaciones' => ['CU-69', 'Reporte de refinanciaciones', 'reportes.refinanciaciones'],
        'proyeccion' => ['CU-70', 'Reporte de proyección de ingresos y disponibilidad de fondos', 'reportes.proyeccion-fondos'],
        'campanas' => ['CU-71', 'Reporte de resultados de campañas crediticias', 'reportes.campanas'],
    ];

    private const ZONA_SQL = "'Argentina Standard Time'";
    private const LINEA = 'JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id';
    private const RIESGO = '(SELECT TOP (1) e.categoria_riesgo FROM dbo.liquidaciones lq JOIN dbo.evaluaciones_crediticias e ON e.solicitud_id = lq.solicitud_id WHERE lq.credito_id = c.id ORDER BY e.id DESC)';
    private const PENDIENTE = 'q.capital_pendiente + q.interes_pendiente + q.gastos_pendiente + q.iva_pendiente + q.no_capitalizable_pendiente + q.punitorio_pendiente + q.iva_punitorio_pendiente';

    private string $hoy = '';

    /**
     * Genera un reporte de gestión y registra su generación en la auditoría (paso 9).
     *
     * @param array<string, mixed> $f criterios del formulario
     * @return array<string, mixed>
     */
    public function generar(string $reporte, array $f, string $formato = 'pantalla'): array
    {
        if (!isset(self::REPORTES[$reporte])) {
            throw new ReglaNegocioException('El reporte no existe.');
        }
        $this->hoy = $this->hoyNegocio();
        $p = Periodo::desde($f, $this->hoy, $reporte === 'proyeccion', $this->periodoProyeccion());
        $r = $this->{$reporte}($p, $f);
        $r += ['titulo' => self::REPORTES[$reporte][1], 'cu' => self::REPORTES[$reporte][0], 'periodo' => $p, 'referencia' => $this->ahoraUtc(), 'aviso' => null];
        (new AuditoriaService($this->db))->registrar('reportes.' . $reporte, 'reportes', null, 'Exitoso', null, [
            'periodo' => $p['etiqueta'], 'formato' => $formato, 'filtros' => array_filter($f, static fn ($v) => $v !== '' && $v !== null && !is_array($v)),
        ]);

        return $r;
    }

    // ── CU-65 Cartera crediticia ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function cartera(array $p, array $f): array
    {
        [$filtro, $b] = $this->filtroCredito($f);
        $creditos = $this->q(
            'SELECT c.id, c.numero, c.estado, c.capital, c.cantidad_cuotas, c.fecha_liquidacion, cl.denominacion, l.nombre AS linea, ' . self::RIESGO . ' AS riesgo, '
            . "COALESCE(x.capital, 0) AS capital_pendiente, COALESCE(x.interes, 0) AS interes_pendiente, COALESCE(x.punitorio, 0) AS punitorio_pendiente, COALESCE(x.gastos, 0) AS gastos_pendiente, COALESCE(x.vencido, 0) AS capital_vencido "
            . 'FROM dbo.creditos c ' . self::LINEA . ' JOIN dbo.clientes cl ON cl.id = c.cliente_id '
            . 'OUTER APPLY (SELECT SUM(q.capital_pendiente) AS capital, SUM(q.interes_pendiente) AS interes, SUM(q.punitorio_pendiente + q.iva_punitorio_pendiente) AS punitorio, '
            . 'SUM(q.gastos_pendiente + q.iva_pendiente + q.no_capitalizable_pendiente) AS gastos, '
            . 'SUM(CASE WHEN DATEDIFF(day, q.vencimiento, CAST(? AS date)) > c.tolerancia_dias THEN q.capital_pendiente ELSE 0 END) AS vencido '
            . "FROM dbo.cuotas q WHERE q.credito_id = c.id AND q.estado IN ('Pendiente', 'Vencida')) x WHERE 1 = 1" . $filtro . ' ORDER BY c.numero',
            [$this->hoy, ...$b],
        );
        $estadosFiltro = (string) ($f['estado'] ?? '');
        $vigentes = array_filter($creditos, static fn ($c) => in_array($c['estado'], ['Activo', 'EnMora'], true));
        $delPeriodo = array_filter($creditos, fn ($c) => $this->enPeriodo((string) $c['fecha_liquidacion'], $p));
        $suma = static fn (array $filas, string $k): string => Decimal::sum(array_map(static fn ($x) => Decimal::of((string) $x[$k]), $filas))->toFixed(2);
        $capitalPendiente = $suma($vigentes, 'capital_pendiente');
        $vencido = $suma($vigentes, 'capital_vencido');
        $saldo = Decimal::of($capitalPendiente)->add($suma($vigentes, 'interes_pendiente'))->add($suma($vigentes, 'punitorio_pendiente'))->add($suma($vigentes, 'gastos_pendiente'))->toFixed(2);

        $estados = ['Activo' => 'Activos', 'EnMora' => 'En mora', 'Cancelado' => 'Cancelados', 'Refinanciado' => 'Refinanciados', 'Revocado' => 'Revocados'];
        $porEstado = [];
        foreach ($estados as $k => $t) {
            $g = array_filter($creditos, static fn ($c) => $c['estado'] === $k);
            $porEstado[] = [$t, count($g), $suma($g, 'capital'), $suma($g, 'capital_pendiente')];
        }
        $porLinea = [];
        foreach ($creditos as $c) {
            $l = $porLinea[$c['linea']] ?? [$c['linea'], 0, 0, '0', '0', '0'];
            $l[1]++;
            if (in_array($c['estado'], ['Activo', 'EnMora'], true)) {
                $l[2]++;
                $l[4] = Decimal::of($l[4])->add((string) $c['capital_pendiente'])->toFixed(2);
                $l[5] = Decimal::of($l[5])->add((string) $c['capital_vencido'])->toFixed(2);
            }
            if ($this->enPeriodo((string) $c['fecha_liquidacion'], $p)) {
                $l[3] = Decimal::of($l[3])->add((string) $c['capital'])->toFixed(2);
            }
            $porLinea[$c['linea']] = $l;
        }
        $evolucion = $this->evolucion($p, $delPeriodo, 'fecha_liquidacion', ['cantidad' => null, 'capital' => 'capital']);

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $creditos === [] ? 'No se registraron créditos con los criterios seleccionados: los indicadores figuran en cero (A1).' : null,
            'kpis' => [
                ['Créditos otorgados en el período', count($delPeriodo), 'entero'],
                ['Capital colocado en el período', $suma($delPeriodo, 'capital'), 'importe'],
                ['Créditos activos (al día y en mora)', count($vigentes), 'entero'],
                ['Saldo deudor total', $saldo, 'importe'],
                ['Capital pendiente', $capitalPendiente, 'importe'],
                ['Intereses ordinarios pendientes', $suma($vigentes, 'interes_pendiente'), 'importe'],
                ['Intereses punitorios acumulados (con IVA)', $suma($vigentes, 'punitorio_pendiente'), 'importe'],
                ['Gastos, IVA y conceptos no capitalizables', $suma($vigentes, 'gastos_pendiente'), 'importe'],
                ['Capital vencido (atraso mayor a la tolerancia)', $vencido, 'importe'],
                ['Índice de morosidad (RN-74)', Indicadores::porcentaje($vencido, $capitalPendiente), 'porcentaje'],
            ],
            'secciones' => [
                ['titulo' => 'Composición por estado', 'columnas' => [['Estado', 'texto'], ['Créditos', 'entero'], ['Capital original', 'importe'], ['Capital pendiente', 'importe']], 'filas' => $estadosFiltro === '' ? $porEstado : array_values(array_filter($porEstado, static fn ($x) => $x[1] > 0))],
                ['titulo' => 'Distribución por línea de crédito', 'columnas' => [['Línea', 'texto'], ['Créditos', 'entero'], ['Vigentes', 'entero'], ['Colocado en el período', 'importe'], ['Capital pendiente', 'importe'], ['Capital vencido', 'importe']], 'filas' => array_values($porLinea)],
                ['titulo' => 'Evolución del período', 'columnas' => [['Intervalo', 'texto'], ['Créditos otorgados', 'entero'], ['Capital colocado', 'importe']], 'filas' => $evolucion],
                ['titulo' => 'Créditos', 'columnas' => [['N.º', 'entero'], ['Cliente', 'texto'], ['Línea', 'texto'], ['Riesgo', 'texto'], ['Liquidación', 'fecha'], ['Capital', 'importe'], ['Estado', 'texto'], ['Capital pendiente', 'importe'], ['Capital vencido', 'importe']],
                    'filas' => array_map(static fn ($c) => [(int) $c['numero'], $c['denominacion'], $c['linea'], $c['riesgo'] ?? '—', $c['fecha_liquidacion'], $c['capital'], $estados[$c['estado']] ?? $c['estado'], $c['capital_pendiente'], $c['capital_vencido']], array_slice($creditos, 0, 1000))],
            ],
        ];
    }

    // ── CU-66 Morosidad ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function morosidad(array $p, array $f): array
    {
        [$filtro, $b] = $this->filtroCredito($f);
        $cuotas = $this->q(
            'SELECT q.id, q.numero, q.vencimiento, q.capital_pendiente, q.punitorio_pendiente + q.iva_punitorio_pendiente AS punitorio, ' . self::PENDIENTE . ' AS pendiente, '
            . 'DATEDIFF(day, q.vencimiento, CAST(? AS date)) AS dias, c.id AS credito_id, c.numero AS credito, c.tolerancia_dias, cl.id AS cliente_id, cl.denominacion, l.nombre AS linea '
            . 'FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id ' . self::LINEA . ' JOIN dbo.clientes cl ON cl.id = c.cliente_id '
            . "WHERE c.estado IN ('Activo', 'EnMora') AND q.estado IN ('Pendiente', 'Vencida') AND q.vencimiento < CAST(? AS date)" . $filtro . ' ORDER BY dias DESC, c.numero, q.numero',
            [$this->hoy, $this->hoy, ...$b],
        );
        $tramoFiltro = (string) ($f['antiguedad'] ?? '');
        if (isset(Indicadores::TRAMOS[$tramoFiltro])) {
            $cuotas = array_values(array_filter($cuotas, static fn ($q) => Indicadores::tramo((int) $q['dias']) === $tramoFiltro));
        }
        $total = $this->uno(
            "SELECT COALESCE(SUM(q.capital_pendiente), 0) AS t FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id " . self::LINEA
            . " WHERE c.estado IN ('Activo', 'EnMora') AND q.estado IN ('Pendiente', 'Vencida')" . $filtro,
            $b,
        );
        $morosas = array_filter($cuotas, static fn ($q) => (int) $q['dias'] > (int) $q['tolerancia_dias']);
        $suma = static fn (array $filas, string $k): string => Decimal::sum(array_map(static fn ($x) => Decimal::of((string) $x[$k]), $filas))->toFixed(2);
        $tramos = [];
        foreach (Indicadores::TRAMOS as $k => $t) {
            $g = array_filter($cuotas, static fn ($q) => Indicadores::tramo((int) $q['dias']) === $k);
            $tramos[] = [$t, count($g), count(array_unique(array_column($g, 'cliente_id'))), $suma($g, 'capital_pendiente'), $suma($g, 'punitorio')];
        }
        $agrupar = static function (array $filas, string $clave, callable $fila): array {
            $g = [];
            foreach ($filas as $x) {
                $g[(string) $x[$clave]][] = $x;
            }

            return array_map($fila, array_values($g));
        };
        $porLinea = $agrupar($cuotas, 'linea', static fn ($g) => [$g[0]['linea'], count($g), $suma($g, 'capital_pendiente'), $suma($g, 'punitorio')]);
        $porCliente = $agrupar($cuotas, 'cliente_id', static fn ($g) => [$g[0]['denominacion'], count($g), max(array_map('intval', array_column($g, 'dias'))), $suma($g, 'capital_pendiente'), $suma($g, 'punitorio'), $suma($g, 'pendiente')]);
        usort($porCliente, static fn ($a, $b) => $b[2] <=> $a[2]);
        // Evolución: cuotas que vencieron en cada intervalo del período y siguen impagas
        $delPeriodo = array_filter($cuotas, fn ($q) => $this->enPeriodo((string) $q['vencimiento'], $p, false));

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $cuotas === [] ? 'No hay cuotas vencidas impagas con los criterios seleccionados: los indicadores figuran en cero (A1).' : null,
            'kpis' => [
                ['Cuotas vencidas impagas', count($cuotas), 'entero'],
                ['Clientes con cuotas vencidas', count(array_unique(array_column($cuotas, 'cliente_id'))), 'entero'],
                ['Capital vencido', $suma($cuotas, 'capital_pendiente'), 'importe'],
                ['Intereses punitorios acumulados (con IVA)', $suma($cuotas, 'punitorio'), 'importe'],
                ['Deuda vencida total', $suma($cuotas, 'pendiente'), 'importe'],
                ['Índice de morosidad (RN-74)', Indicadores::porcentaje($suma($morosas, 'capital_pendiente'), $total), 'porcentaje'],
            ],
            'secciones' => [
                ['titulo' => 'Antigüedad de la mora (RN-74)', 'columnas' => [['Tramo', 'texto'], ['Cuotas', 'entero'], ['Clientes', 'entero'], ['Capital vencido', 'importe'], ['Punitorios', 'importe']], 'filas' => $tramos],
                ['titulo' => 'Distribución por línea de crédito', 'columnas' => [['Línea', 'texto'], ['Cuotas', 'entero'], ['Capital vencido', 'importe'], ['Punitorios', 'importe']], 'filas' => $porLinea],
                ['titulo' => 'Distribución por cliente', 'columnas' => [['Cliente', 'texto'], ['Cuotas', 'entero'], ['Atraso máximo (días)', 'entero'], ['Capital vencido', 'importe'], ['Punitorios', 'importe'], ['Deuda vencida', 'importe']], 'filas' => $porCliente],
                ['titulo' => 'Evolución del período (cuotas vencidas en cada intervalo, aún impagas)', 'columnas' => [['Intervalo', 'texto'], ['Cuotas', 'entero'], ['Capital vencido', 'importe']], 'filas' => $this->evolucion($p, $delPeriodo, 'vencimiento', ['cantidad' => null, 'capital' => 'capital_pendiente'])],
                ['titulo' => 'Punitorios por crédito y cuota', 'columnas' => [['Crédito', 'entero'], ['Cuota', 'entero'], ['Cliente', 'texto'], ['Línea', 'texto'], ['Vencimiento', 'fecha'], ['Atraso (días)', 'entero'], ['Capital vencido', 'importe'], ['Punitorios', 'importe']],
                    'filas' => array_map(static fn ($q) => [(int) $q['credito'], (int) $q['numero'], $q['denominacion'], $q['linea'], $q['vencimiento'], (int) $q['dias'], $q['capital_pendiente'], $q['punitorio']], array_slice($cuotas, 0, 1000))],
            ],
        ];
    }

    // ── CU-67 Cobranzas y recupero ─────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function cobranzas(array $p, array $f): array
    {
        [$ini, $fin] = $this->limites($p);
        $w = ' AND b.fecha_hora >= ? AND b.fecha_hora < ?';
        $b = [$ini, $fin];
        if (ctype_digit((string) ($f['caja'] ?? ''))) {
            $w .= ' AND j.caja_id = ?';
            $b[] = (int) $f['caja'];
        }
        if (ctype_digit((string) ($f['cajero'] ?? ''))) {
            $w .= ' AND b.usuario_id = ?';
            $b[] = (int) $f['cajero'];
        }
        if (isset(self::medios()[$f['medio'] ?? ''])) {
            $w .= ' AND EXISTS (SELECT 1 FROM dbo.cobros_medios m WHERE m.cobro_id = b.id AND m.medio = ?)';
            $b[] = $f['medio'];
        }
        if (ctype_digit((string) ($f['linea'] ?? ''))) {
            $w .= ' AND EXISTS (SELECT 1 FROM dbo.imputaciones i JOIN dbo.cuotas q ON q.id = i.cuota_id JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id WHERE i.cobro_id = b.id AND v.linea_credito_id = ?)';
            $b[] = (int) $f['linea'];
        }
        $clientes = $this->clientes((string) ($f['cliente'] ?? ''));
        if ($clientes !== null) {
            $w .= $clientes === [] ? ' AND 1 = 0' : ' AND b.cliente_id IN (' . implode(', ', $clientes) . ')';
        }
        $cobros = $this->q(
            'SELECT b.id, b.numero, b.fecha_hora, b.importe, b.estado, b.canal, b.tipo, cl.denominacion FROM dbo.cobros b JOIN dbo.clientes cl ON cl.id = b.cliente_id '
            . 'LEFT JOIN dbo.jornadas_caja j ON j.id = b.jornada_caja_id WHERE 1 = 1' . $w . ' ORDER BY b.fecha_hora',
            $b,
        );
        $confirmados = array_values(array_filter($cobros, static fn ($c) => $c['estado'] === 'Confirmado'));
        $ids = array_map(static fn ($c) => (int) $c['id'], $confirmados);
        $suma = static fn (array $filas, string $k): string => Decimal::sum(array_map(static fn ($x) => Decimal::of((string) $x[$k]), $filas))->toFixed(2);
        $lista = $ids === [] ? '0' : implode(', ', $ids);
        $medios = $this->q("SELECT m.medio, COUNT(*) AS cantidad, SUM(m.importe) AS importe FROM dbo.cobros_medios m WHERE m.cobro_id IN ({$lista}) GROUP BY m.medio ORDER BY importe DESC");
        // Recupero de mora (RN-74 c): imputaciones a cuotas pagadas con un atraso mayor a la tolerancia
        $recupero = $this->q(
            "SELECT i.concepto, SUM(i.importe) AS importe FROM dbo.imputaciones i JOIN dbo.cobros b ON b.id = i.cobro_id JOIN dbo.cuotas q ON q.id = i.cuota_id JOIN dbo.creditos c ON c.id = q.credito_id "
            . "WHERE b.id IN ({$lista}) AND DATEDIFF(day, q.vencimiento, CAST(COALESCE(b.fecha_efectiva_pago, b.fecha_hora) AT TIME ZONE " . self::ZONA_SQL . ' AS date)) > c.tolerancia_dias GROUP BY i.concepto',
        );
        $conceptos = ['Capital' => 'Capital', 'InteresOrdinario' => 'Intereses ordinarios', 'Punitorio' => 'Intereses punitorios', 'GastosCargos' => 'Gastos y cargos', 'IVA' => 'IVA', 'NoCapitalizable' => 'Concepto no capitalizable'];
        $rec = array_column($recupero, 'importe', 'concepto');
        // Efectividad (RN-74 b): cobrado de las cuotas que vencieron en el período sobre su exigible
        $efect = $this->q(
            'SELECT COALESCE(SUM(q.capital + q.interes + q.gastos + q.iva + q.concepto_no_capitalizable), 0) AS exigible, '
            . 'COALESCE(SUM(q.capital + q.interes + q.gastos + q.iva + q.concepto_no_capitalizable - q.capital_pendiente - q.interes_pendiente - q.gastos_pendiente - q.iva_pendiente - q.no_capitalizable_pendiente), 0) AS cobrado '
            . "FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id WHERE q.estado <> 'Sustituida' AND c.estado <> 'Revocado' AND q.vencimiento BETWEEN CAST(? AS date) AND CAST(? AS date)",
            [$p['desde'], min($p['hasta'], $this->hoy)],
        )[0];
        $anulaciones = $this->q(
            'SELECT b.numero, a.fecha, b.importe, a.motivo, cl.denominacion FROM dbo.anulaciones_cobro a JOIN dbo.cobros b ON b.id = a.cobro_id JOIN dbo.clientes cl ON cl.id = b.cliente_id '
            . 'WHERE a.fecha >= ? AND a.fecha < ? ORDER BY a.fecha',
            [$ini, $fin],
        );
        $saldos = [
            ['Generados', $this->uno('SELECT COALESCE(SUM(importe_original), 0) AS t FROM dbo.saldos_a_favor WHERE fecha >= ? AND fecha < ?', [$ini, $fin])],
            ['Aplicados a obligaciones', $this->uno('SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.aplicaciones_saldo WHERE fecha >= ? AND fecha < ?', [$ini, $fin])],
            ['Devueltos', $this->uno("SELECT COALESCE(SUM(a.importe), 0) AS t FROM dbo.ejecuciones_devolucion e JOIN dbo.autorizaciones_devolucion a ON a.id = e.autorizacion_devolucion_id WHERE e.resultado = 'Confirmada' AND e.fecha_confirmacion >= ? AND e.fecha_confirmacion < ?", [$ini, $fin])],
        ];
        $ordenes = $this->q(
            "SELECT o.numero, o.emision, o.vence, o.importe, o.estado, cl.denominacion FROM dbo.ordenes_pago_electronico o JOIN dbo.clientes cl ON cl.id = o.cliente_id "
            . "WHERE o.emision < ? AND (o.estado = 'EnConciliacion' OR (o.estado = 'PendienteConfirmacion' AND o.vence > ?)) ORDER BY o.emision",
            [$fin, min($fin, $this->ahoraUtc())],
        );
        $total = $suma($confirmados, 'importe');

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $cobros === [] ? 'No se registraron cobros en el período con los criterios seleccionados: los indicadores figuran en cero (A1).' : null,
            'kpis' => [
                ['Cobros confirmados', count($confirmados), 'entero'],
                ['Total cobrado', $total, 'importe'],
                ['Recupero de cartera en mora (RN-74)', Decimal::sum(array_map(static fn ($v) => Decimal::of((string) $v), array_values($rec)))->toFixed(2), 'importe'],
                ['Efectividad de cobranza (RN-74)', Indicadores::porcentaje((string) $efect['cobrado'], (string) $efect['exigible']), 'porcentaje'],
                ['Cobros anulados', count($anulaciones), 'entero'],
                ['Órdenes electrónicas pendientes', count($ordenes), 'entero'],
            ],
            'secciones' => [
                ['titulo' => 'Cobros confirmados por medio de pago', 'columnas' => [['Medio', 'texto'], ['Pagos', 'entero'], ['Importe', 'importe']], 'filas' => array_map(static fn ($m) => [self::medios()[$m['medio']] ?? $m['medio'], (int) $m['cantidad'], $m['importe']], $medios)],
                ['titulo' => 'Recupero de cartera en mora por concepto', 'columnas' => [['Concepto', 'texto'], ['Importe recuperado', 'importe']], 'filas' => array_map(static fn ($k, $t) => [$t, (string) ($rec[$k] ?? '0')], array_keys($conceptos), $conceptos)],
                ['titulo' => 'Efectividad de cobranza', 'columnas' => [['Concepto', 'texto'], ['Importe', 'importe']], 'filas' => [['Exigible de las cuotas vencidas en el período', (string) $efect['exigible']], ['Cobrado de esas cuotas', (string) $efect['cobrado']]]],
                ['titulo' => 'Anulaciones', 'columnas' => [['Cobro', 'entero'], ['Fecha', 'fechahora'], ['Cliente', 'texto'], ['Importe', 'importe'], ['Motivo', 'texto']], 'filas' => array_map(static fn ($a) => [(int) $a['numero'], $a['fecha'], $a['denominacion'], $a['importe'], $a['motivo']], $anulaciones)],
                ['titulo' => 'Saldos a favor', 'columnas' => [['Movimiento', 'texto'], ['Importe', 'importe']], 'filas' => $saldos],
                ['titulo' => 'Órdenes electrónicas pendientes al cierre del período', 'columnas' => [['Orden', 'entero'], ['Emisión', 'fechahora'], ['Vence', 'fechahora'], ['Cliente', 'texto'], ['Importe', 'importe'], ['Estado', 'texto']], 'filas' => array_map(static fn ($o) => [(int) $o['numero'], $o['emision'], $o['vence'], $o['denominacion'], $o['importe'], $o['estado'] === 'EnConciliacion' ? 'En conciliación' : 'Pendiente'], $ordenes),
                    'nota' => 'No se computan como cobros confirmados (RN-47).'],
                ['titulo' => 'Evolución del período', 'columnas' => [['Intervalo', 'texto'], ['Cobros', 'entero'], ['Importe', 'importe']], 'filas' => $this->evolucion($p, $confirmados, 'fecha_hora', ['cantidad' => null, 'importe' => 'importe'])],
            ],
        ];
    }

    // ── CU-68 Solicitudes y otorgamientos ──────────────────────────────────────

    /** @return array<string, mixed> */
    private function solicitudes(array $p, array $f): array
    {
        [$ini, $fin] = $this->limites($p);
        $w = ' AND s.fecha_registro >= ? AND s.fecha_registro < ?';
        $b = [$ini, $fin];
        if (ctype_digit((string) ($f['linea'] ?? ''))) {
            $w .= ' AND v.linea_credito_id = ?';
            $b[] = (int) $f['linea'];
        }
        if (isset(EstadosSolicitud::ETIQUETAS[$f['estado'] ?? ''])) {
            $w .= ' AND s.estado = ?';
            $b[] = $f['estado'];
        }
        if (($f['analista'] ?? '') === 'portal') {
            $w .= ' AND s.usuario_id IS NULL';
        } elseif (ctype_digit((string) ($f['analista'] ?? ''))) {
            $w .= ' AND s.usuario_id = ?';
            $b[] = (int) $f['analista'];
        }
        $clientes = $this->clientes((string) ($f['cliente'] ?? ''));
        if ($clientes !== null) {
            $w .= $clientes === [] ? ' AND 1 = 0' : ' AND s.cliente_id IN (' . implode(', ', $clientes) . ')';
        }
        $filas = $this->q(
            "SELECT s.id, s.numero, s.estado, s.importe, s.cantidad_cuotas, s.fecha_registro, s.origen, cl.denominacion, l.nombre AS linea, "
            . "COALESCE(u.apellido + ', ' + u.nombre, 'Portal (ofertas)') AS analista, lq.capital AS otorgado, lq.fecha AS liquidacion, "
            . "(SELECT MIN(r.fecha) FROM dbo.resoluciones r WHERE r.solicitud_id = s.id AND r.decision IN ('Aprobada', 'Rechazada')) AS resolucion, "
            . "(SELECT TOP (1) r.decision FROM dbo.resoluciones r WHERE r.solicitud_id = s.id AND r.decision IN ('Aprobada', 'Rechazada') ORDER BY r.fecha DESC) AS decision "
            . 'FROM dbo.solicitudes_credito s JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . 'JOIN dbo.clientes cl ON cl.id = s.cliente_id LEFT JOIN dbo.usuarios_internos u ON u.id = s.usuario_id LEFT JOIN dbo.liquidaciones lq ON lq.solicitud_id = s.id '
            . 'WHERE 1 = 1' . $w . ' ORDER BY s.fecha_registro',
            $b,
        );
        $suma = static fn (array $g, string $k): string => Decimal::sum(array_map(static fn ($x) => Decimal::of((string) ($x[$k] ?? '0')), $g))->toFixed(2);
        $aprobadas = array_filter($filas, static fn ($s) => $s['decision'] === 'Aprobada' || $s['liquidacion'] !== null);
        $rechazadas = array_filter($filas, static fn ($s) => $s['estado'] === 'Rechazada');
        $cuenta = static fn (string $e): int => count(array_filter($filas, static fn ($s) => $s['estado'] === $e));
        $dias = static fn (array $g, string $a, string $b): string => $g === [] ? '0.0' : number_format(array_sum(array_map(static fn ($s) => (strtotime((string) $s[$b]) - strtotime((string) $s[$a])) / 86400, $g)) / count($g), 1, '.', '');
        $liquidadas = array_values(array_filter($filas, static fn ($s) => $s['liquidacion'] !== null));
        $resueltas = array_values(array_filter($filas, static fn ($s) => $s['resolucion'] !== null));
        $agrupar = static function (string $clave) use ($filas, $suma): array {
            $g = [];
            foreach ($filas as $s) {
                $g[(string) $s[$clave]][] = $s;
            }
            ksort($g);

            return array_map(static fn ($k, $x) => [$k, count($x), count(array_filter($x, static fn ($s) => $s['liquidacion'] !== null)), count(array_filter($x, static fn ($s) => $s['estado'] === 'Rechazada')), $suma($x, 'importe'), $suma($x, 'otorgado')], array_keys($g), $g);
        };
        $columnasGrupo = static fn (string $t): array => [[$t, 'texto'], ['Registradas', 'entero'], ['Liquidadas', 'entero'], ['Rechazadas', 'entero'], ['Importe solicitado', 'importe'], ['Importe otorgado', 'importe']];

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $filas === [] ? 'No se registraron solicitudes en el período con los criterios seleccionados: los indicadores figuran en cero (A1).' : null,
            'kpis' => [
                ['Solicitudes registradas', count($filas), 'entero'],
                ['Aprobadas', count($aprobadas), 'entero'],
                ['Rechazadas', count($rechazadas), 'entero'],
                ['Canceladas', $cuenta('Cancelada'), 'entero'],
                ['Vencidas', $cuenta('Vencida'), 'entero'],
                ['Liquidadas', count($liquidadas), 'entero'],
                ['Importe solicitado', $suma($filas, 'importe'), 'importe'],
                ['Importe otorgado', $suma($filas, 'otorgado'), 'importe'],
                ['Días promedio de registro a resolución', $dias($resueltas, 'fecha_registro', 'resolucion'), 'decimal'],
                ['Días promedio de registro a liquidación', $dias($liquidadas, 'fecha_registro', 'liquidacion'), 'decimal'],
                ['Tasa de aprobación (RN-74)', Indicadores::porcentaje(count($aprobadas), count($aprobadas) + count($rechazadas)), 'porcentaje'],
                ['Tasa de rechazo', Indicadores::porcentaje(count($rechazadas), count($aprobadas) + count($rechazadas)), 'porcentaje'],
            ],
            'secciones' => [
                ['titulo' => 'Solicitudes por estado', 'columnas' => [['Estado', 'texto'], ['Solicitudes', 'entero'], ['Importe solicitado', 'importe']],
                    'filas' => array_values(array_filter(array_map(static fn ($e, $t) => [$t, count(array_filter($filas, static fn ($s) => $s['estado'] === $e)), $suma(array_filter($filas, static fn ($s) => $s['estado'] === $e), 'importe')], array_keys(EstadosSolicitud::ETIQUETAS), EstadosSolicitud::ETIQUETAS), static fn ($x) => $x[1] > 0))],
                ['titulo' => 'Distribución por línea de crédito', 'columnas' => $columnasGrupo('Línea'), 'filas' => $agrupar('linea')],
                ['titulo' => 'Distribución por Analista de Crédito', 'columnas' => $columnasGrupo('Analista'), 'filas' => $agrupar('analista')],
                ['titulo' => 'Evolución del período', 'columnas' => [['Intervalo', 'texto'], ['Registradas', 'entero'], ['Importe solicitado', 'importe']], 'filas' => $this->evolucion($p, $filas, 'fecha_registro', ['cantidad' => null, 'importe' => 'importe'])],
                ['titulo' => 'Solicitudes', 'columnas' => [['N.º', 'entero'], ['Registro', 'fecha'], ['Cliente', 'texto'], ['Línea', 'texto'], ['Analista', 'texto'], ['Importe', 'importe'], ['Cuotas', 'entero'], ['Estado', 'texto'], ['Otorgado', 'importe']],
                    'filas' => array_map(static fn ($s) => [(int) $s['numero'], $s['fecha_registro'], $s['denominacion'], $s['linea'], $s['analista'], $s['importe'], (int) $s['cantidad_cuotas'], EstadosSolicitud::etiqueta((string) $s['estado']), $s['otorgado'] ?? '0'], array_slice($filas, 0, 1000))],
            ],
        ];
    }

    // ── CU-69 Refinanciaciones ─────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function refinanciaciones(array $p, array $f): array
    {
        [$ini, $fin] = $this->limites($p);
        $w = ' AND pr.fecha_registro >= ? AND pr.fecha_registro < ?';
        $b = [$ini, $fin];
        if (ctype_digit((string) ($f['linea'] ?? ''))) {
            $w .= ' AND EXISTS (SELECT 1 FROM dbo.propuestas_cuotas pc JOIN dbo.cuotas q ON q.id = pc.cuota_id JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id WHERE pc.propuesta_id = pr.id AND v.linea_credito_id = ?)';
            $b[] = (int) $f['linea'];
        }
        if (isset(RefinanciacionService::ESTADOS[$f['estado'] ?? ''])) {
            $w .= ' AND pr.estado = ?';
            $b[] = $f['estado'];
        }
        if (ctype_digit((string) ($f['analista'] ?? ''))) {
            $w .= ' AND pr.usuario_id = ?';
            $b[] = (int) $f['analista'];
        }
        $clientes = $this->clientes((string) ($f['cliente'] ?? ''));
        if ($clientes !== null) {
            $w .= $clientes === [] ? ' AND 1 = 0' : ' AND pr.cliente_id IN (' . implode(', ', $clientes) . ')';
        }
        $filas = $this->q(
            "SELECT pr.id, pr.numero, pr.version, pr.estado, pr.fecha_registro, pr.capital_refinanciado, pr.concepto_no_capitalizable, pr.cantidad_cuotas, pr.tasa, cl.denominacion, u.apellido + ', ' + u.nombre AS analista, "
            . '(SELECT COALESCE(SUM(pc.punitorio), 0) FROM dbo.propuestas_cuotas pc WHERE pc.propuesta_id = pr.id) AS punitorios, '
            . '(SELECT COUNT(*) FROM dbo.propuestas_cuotas pc WHERE pc.propuesta_id = pr.id) AS cuotas_incluidas, '
            . "(SELECT MIN(r.fecha) FROM dbo.resoluciones_propuesta r WHERE r.propuesta_id = pr.id) AS resolucion, "
            . "(SELECT MIN(r.fecha) FROM dbo.resoluciones_propuesta r WHERE r.propuesta_id = pr.id AND r.decision = 'Aprobada') AS aprobacion, "
            . '(SELECT TOP (1) a.fecha FROM dbo.aceptaciones_refinanciacion a WHERE a.propuesta_id = pr.id) AS aplicacion, '
            . '(SELECT TOP (1) c.numero FROM dbo.creditos c WHERE c.propuesta_origen_id = pr.id) AS credito_nuevo '
            . 'FROM dbo.propuestas_refinanciacion pr JOIN dbo.clientes cl ON cl.id = pr.cliente_id JOIN dbo.usuarios_internos u ON u.id = pr.usuario_id WHERE 1 = 1' . $w . ' ORDER BY pr.fecha_registro',
            $b,
        );
        $condonaciones = $this->q(
            'SELECT co.fecha, co.importe, co.fundamento, cl.denominacion FROM dbo.condonaciones co LEFT JOIN dbo.clientes cl ON cl.id = co.cliente_id WHERE co.fecha >= ? AND co.fecha < ? ORDER BY co.fecha',
            [$ini, $fin],
        );
        $suma = static fn (array $g, string $k): string => Decimal::sum(array_map(static fn ($x) => Decimal::of((string) ($x[$k] ?? '0')), $g))->toFixed(2);
        $cuenta = static fn (string $e): int => count(array_filter($filas, static fn ($x) => $x['estado'] === $e));
        $aplicadas = array_values(array_filter($filas, static fn ($x) => $x['estado'] === 'Aplicada'));
        $aprobadas = count(array_filter($filas, static fn ($x) => $x['aprobacion'] !== null));
        $resueltas = array_values(array_filter($filas, static fn ($x) => $x['resolucion'] !== null));
        $dias = static fn (array $g, string $a, string $b): string => ($g = array_values(array_filter($g, static fn ($x) => $x[$a] !== null && $x[$b] !== null))) === [] ? '0.0'
            : number_format(array_sum(array_map(static fn ($x) => (strtotime((string) $x[$b]) - strtotime((string) $x[$a])) / 86400, $g)) / count($g), 1, '.', '');

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $filas === [] && $condonaciones === [] ? 'No se registraron propuestas ni condonaciones en el período: los indicadores figuran en cero (A1).' : null,
            'kpis' => [
                ['Propuestas registradas', count($filas), 'entero'],
                ['Aprobadas (incluye aplicadas)', $aprobadas, 'entero'],
                ['Aplicadas', count($aplicadas), 'entero'],
                ['Observadas', $cuenta('Observada'), 'entero'],
                ['Rechazadas', $cuenta('Rechazada'), 'entero'],
                ['Deuda refinanciada (capital)', $suma($aplicadas, 'capital_refinanciado'), 'importe'],
                ['Concepto no capitalizable de las aplicadas', $suma($aplicadas, 'concepto_no_capitalizable'), 'importe'],
                ['Punitorios comprendidos en las aplicadas', $suma($aplicadas, 'punitorios'), 'importe'],
                ['Punitorios condonados', $suma($condonaciones, 'importe'), 'importe'],
                ['Días promedio de registro a resolución', $dias($filas, 'fecha_registro', 'resolucion'), 'decimal'],
                ['Días promedio de aprobación a aplicación', $dias($filas, 'aprobacion', 'aplicacion'), 'decimal'],
                ['Tasa de aprobación', Indicadores::porcentaje($aprobadas, count($resueltas)), 'porcentaje'],
            ],
            'secciones' => [
                ['titulo' => 'Propuestas por estado', 'columnas' => [['Estado', 'texto'], ['Propuestas', 'entero'], ['Capital', 'importe'], ['Tasa sobre resueltas', 'porcentaje']],
                    'filas' => array_map(static fn ($e, $t) => [$t, $cuenta($e), $suma(array_filter($filas, static fn ($x) => $x['estado'] === $e), 'capital_refinanciado'), in_array($e, ['Observada', 'Rechazada'], true) ? Indicadores::porcentaje($cuenta($e), count($resueltas)) : null], array_keys(RefinanciacionService::ESTADOS), RefinanciacionService::ESTADOS)],
                ['titulo' => 'Impacto sobre la cartera', 'columnas' => [['Propuesta', 'entero'], ['Cliente', 'texto'], ['Cuotas sustituidas', 'entero'], ['Capital refinanciado', 'importe'], ['Crédito nuevo', 'entero'], ['Aplicada', 'fecha']],
                    'filas' => array_map(static fn ($x) => [(int) $x['numero'], $x['denominacion'], (int) $x['cuotas_incluidas'], $x['capital_refinanciado'], $x['credito_nuevo'] === null ? null : (int) $x['credito_nuevo'], $x['aplicacion']], $aplicadas),
                    'nota' => 'Las obligaciones originales quedan sustituidas por el nuevo plan, no pagadas ni eliminadas (RN-37).'],
                ['titulo' => 'Condonaciones de intereses punitorios', 'columnas' => [['Fecha', 'fechahora'], ['Cliente', 'texto'], ['Importe', 'importe'], ['Fundamento', 'texto']], 'filas' => array_map(static fn ($c) => [$c['fecha'], $c['denominacion'] ?? '—', $c['importe'], $c['fundamento']], $condonaciones)],
                ['titulo' => 'Evolución del período', 'columnas' => [['Intervalo', 'texto'], ['Propuestas', 'entero'], ['Capital', 'importe']], 'filas' => $this->evolucion($p, $filas, 'fecha_registro', ['cantidad' => null, 'capital' => 'capital_refinanciado'])],
                ['titulo' => 'Propuestas', 'columnas' => [['N.º', 'entero'], ['Versión', 'entero'], ['Registro', 'fecha'], ['Cliente', 'texto'], ['Analista', 'texto'], ['Capital', 'importe'], ['No capitalizable', 'importe'], ['Cuotas', 'entero'], ['Estado', 'texto']],
                    'filas' => array_map(static fn ($x) => [(int) $x['numero'], (int) $x['version'], $x['fecha_registro'], $x['denominacion'], $x['analista'], $x['capital_refinanciado'], $x['concepto_no_capitalizable'], (int) $x['cantidad_cuotas'], RefinanciacionService::ESTADOS[$x['estado']] ?? $x['estado']], $filas)],
            ],
        ];
    }

    // ── CU-70 Proyección de ingresos y disponibilidad ──────────────────────────

    /** @return array<string, mixed> */
    private function proyeccion(array $p, array $f): array
    {
        $param = (new ParametroService($this->db))->vigente('Campanas');
        $v = ValidadorParametrosCampanas::completar($param['valores']);
        $factor = Decimal::of((string) $v['factor_cobrabilidad']);
        $linea = ctype_digit((string) ($f['linea'] ?? '')) ? ' AND v.linea_credito_id = ' . (int) $f['linea'] : '';
        $aVencer = $this->uno(
            'SELECT COALESCE(SUM(' . self::PENDIENTE . '), 0) AS t FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id '
            . "WHERE c.estado IN ('Activo', 'EnMora') AND q.estado IN ('Pendiente', 'Vencida') AND q.vencimiento BETWEEN CAST(? AS date) AND CAST(? AS date)" . $linea,
            [max($p['desde'], $this->hoy), $p['hasta']],
        );
        $vencidas = $this->uno(
            'SELECT COALESCE(SUM(' . self::PENDIENTE . '), 0) AS t FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id '
            . "WHERE c.estado IN ('Activo', 'EnMora') AND q.estado IN ('Pendiente', 'Vencida') AND q.vencimiento < CAST(? AS date)" . $linea,
            [$this->hoy],
        );
        $programados = static fn (string $caracter, ?string $tipo, ReporteService $s, array $p): string => $s->uno(
            "SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.movimientos_fondos WHERE estado = 'Programado' AND caracter = ? AND fecha BETWEEN CAST(? AS date) AND CAST(? AS date)" . ($tipo === null ? '' : ($tipo === 'GastoOperativo' ? " AND tipo = 'GastoOperativo'" : " AND tipo <> 'GastoOperativo'")),
            [$caracter, $p['desde'], $p['hasta']],
        );
        $cobranzas = Decimal::of($aVencer)->mul($factor)->round(2);
        $recupero = Decimal::of($vencidas)->mul($factor)->round(2);
        $otrosIngresos = Decimal::of($programados('Ingreso', null, $this, $p));
        $reservas = $this->uno("SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.afectaciones_fondos WHERE estado = 'Vigente' AND tipo = 'Reserva'");
        $compromisos = $this->uno("SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.afectaciones_fondos WHERE estado = 'Vigente' AND tipo = 'Compromiso'");
        $gastos = Decimal::of($programados('Egreso', 'GastoOperativo', $this, $p));
        $otrosEgresos = Decimal::of($programados('Egreso', 'Otro', $this, $p));
        $d = (new FondosService($this->db))->disponibilidad();
        $ingresos = $cobranzas->add($recupero)->add($otrosIngresos);
        $egresos = Decimal::of($reservas)->add($gastos)->add($otrosEgresos);
        $proyectado = Decimal::of($d['fondos'])->add($ingresos)->sub($egresos)->sub($compromisos)->sub($d['reserva_minima']);
        $umbral = Decimal::of((string) $v['umbral_disponibilidad']);
        $campanas = $this->q(
            'SELECT c.numero, c.capital_asignado, c.capital_comprometido, c.vence, l.nombre AS linea FROM dbo.campanas c ' . self::LINEA . " WHERE c.estado = 'Activa'"
            . (ctype_digit((string) ($f['campana'] ?? '')) ? ' AND c.numero = ' . (int) $f['campana'] : ''),
        );
        $debajo = $proyectado->compare($umbral) <= 0;

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $debajo ? 'La disponibilidad proyectada no supera el umbral configurado: no existirían condiciones para activar nuevas campañas en el período (A3).' : null,
            'kpis' => [
                ['Disponibilidad actual (fondos)', $d['fondos'], 'importe'],
                ['Ingresos esperados', $ingresos->toFixed(2), 'importe'],
                ['Egresos esperados', $egresos->toFixed(2), 'importe'],
                ['Compromisos temporales de ofertas', $compromisos, 'importe'],
                ['Reserva mínima de fondos', $d['reserva_minima'], 'importe'],
                ['Disponibilidad proyectada', $proyectado->toFixed(2), 'importe'],
                ['Umbral de disponibilidad', $umbral->toFixed(2), 'importe'],
                ['Capital disponible para nuevas colocaciones', Decimal::max(Decimal::zero(), $proyectado)->toFixed(2), 'importe'],
            ],
            'secciones' => [
                ['titulo' => 'Ingresos esperados por origen', 'columnas' => [['Origen', 'texto'], ['A cobrar', 'importe'], ['Esperado', 'importe']], 'filas' => [
                    ['Cobranzas de cartera (cuotas que vencen en el período)', $aVencer, $cobranzas->toFixed(2)],
                    ['Recupero de mora (cuotas ya vencidas)', $vencidas, $recupero->toFixed(2)],
                    ['Otros ingresos programados', $otrosIngresos->toFixed(2), $otrosIngresos->toFixed(2)],
                ], 'nota' => 'Los ingresos proyectados no son dinero efectivamente recibido (RN-39). Se aplica el factor de cobrabilidad a las cuotas.'],
                ['titulo' => 'Egresos esperados por tipo', 'columnas' => [['Tipo', 'texto'], ['Importe', 'importe']], 'filas' => [
                    ['Desembolsos por liquidación (reservas de aprobaciones vigentes)', $reservas], ['Gastos operativos programados', $gastos->toFixed(2)], ['Otros egresos programados', $otrosEgresos->toFixed(2)],
                ]],
                ['titulo' => 'Campañas crediticias activas', 'columnas' => [['Campaña', 'entero'], ['Línea', 'texto'], ['Capital asignado', 'importe'], ['Capital comprometido', 'importe'], ['Vence', 'fechahora']],
                    'filas' => array_map(static fn ($c) => [(int) $c['numero'], $c['linea'], $c['capital_asignado'], $c['capital_comprometido'], $c['vence']], $campanas),
                    'nota' => 'El capital comprometido en ofertas aceptadas ya integra los compromisos temporales.'],
                ['titulo' => 'Comparación con el umbral', 'columnas' => [['Concepto', 'texto'], ['Importe', 'importe']], 'filas' => [
                    ['Disponibilidad proyectada', $proyectado->toFixed(2)], ['Umbral de disponibilidad', $umbral->toFixed(2)], ['Diferencia', $proyectado->sub($umbral)->toFixed(2)],
                ], 'nota' => $debajo ? 'Por debajo del umbral: no se activarían campañas.' : 'Supera el umbral: la proyección diaria activaría una campaña si no hay otra activa.'],
                ['titulo' => 'Supuestos utilizados', 'columnas' => [['Supuesto', 'texto'], ['Valor', 'texto']], 'filas' => [
                    ['Fecha de generación', Time::now(config('Sigccr')->zonaNegocio)->format('d/m/Y H:i')], ['Período de proyección', $p['etiqueta']],
                    ['Parámetros de campañas', 'versión ' . $param['numero']], ['Factor de cobrabilidad', str_replace('.', ',', $factor->mul(100)->round(2)->toFixed(2)) . ' %'],
                ]],
            ],
        ];
    }

    // ── CU-71 Resultados de campañas ───────────────────────────────────────────

    /** @return array<string, mixed> */
    private function campanas(array $p, array $f): array
    {
        [$ini, $fin] = $this->limites($p);
        $w = ' AND c.activacion >= ? AND c.activacion < ?';
        $b = [$ini, $fin];
        if (ctype_digit((string) ($f['linea'] ?? ''))) {
            $w .= ' AND v.linea_credito_id = ?';
            $b[] = (int) $f['linea'];
        }
        if (in_array($f['estado'] ?? '', ['Activa', 'Finalizada'], true)) {
            $w .= ' AND c.estado = ?';
            $b[] = $f['estado'];
        }
        $vacio = ($f['canal'] ?? '') !== '' && !isset(ValidadorParametrosCampanas::CANALES[$f['canal']]);
        $campanas = $vacio ? [] : $this->q('SELECT c.*, l.nombre AS linea FROM dbo.campanas c ' . self::LINEA . ' WHERE 1 = 1' . $w . ' ORDER BY c.activacion', $b);
        $categoria = (string) ($f['categoria'] ?? '');
        $ids = array_map(static fn ($c) => (int) $c['id'], $campanas);
        $lista = $ids === [] ? '0' : implode(', ', $ids);
        $ofertas = $this->q(
            'SELECT o.*, c.numero AS campana, cl.denominacion, cl.categoria_ingresos, s.numero AS solicitud, s.estado AS solicitud_estado, s.importe AS solicitud_importe, '
            . '(SELECT TOP (1) lq.capital FROM dbo.liquidaciones lq WHERE lq.solicitud_id = s.id) AS liquidado '
            . 'FROM dbo.ofertas o JOIN dbo.campanas c ON c.id = o.campana_id JOIN dbo.clientes cl ON cl.id = o.cliente_id LEFT JOIN dbo.solicitudes_credito s ON s.id = o.solicitud_id '
            . "WHERE o.campana_id IN ({$lista})" . ($categoria !== '' ? ' AND cl.categoria_ingresos = ' . $this->db->escape($categoria) : '') . ' ORDER BY c.numero, cl.denominacion',
        );
        $r = ReglasCampanas::resultados($ofertas);
        $liquidadas = count(array_filter($ofertas, static fn ($o) => $o['liquidado'] !== null));
        $comunicaciones = $this->q("SELECT estado, COUNT(*) AS cantidad FROM dbo.comunicaciones WHERE tipo = 'Oferta' AND origen_tipo = 'ofertas' AND origen_id IN (SELECT id FROM dbo.ofertas WHERE campana_id IN ({$lista})) GROUP BY estado");
        $estadosCom = ['Pendiente' => 'Pendiente', 'AceptadaProveedor' => 'Aceptada por el proveedor', 'Entregada' => 'Entregada', 'ReintentoProgramado' => 'Reintento programado', 'Fallida' => 'Fallida', 'Cancelada' => 'Cancelada', 'EnRevision' => 'En revisión'];
        $porCampana = array_map(static function (array $c) use ($ofertas): array {
            $o = array_values(array_filter($ofertas, static fn ($x) => (int) $x['campana_id'] === (int) $c['id']));
            $x = ReglasCampanas::resultados($o);

            return [(int) $c['numero'], $c['linea'], $c['activacion'], $c['fecha_cierre'] ?? $c['vence'], $c['estado'] === 'Activa' ? 'Activa' : (ReglasCampanas::CAUSAS[$c['causa_cierre']] ?? $c['causa_cierre']),
                $x['emitidas'], $x['aceptadas'], $x['rechazadas'], $x['vencidas'], $c['capital_asignado'], $c['capital_comprometido'], $x['capital_colocado']];
        }, $campanas);

        return [
            'filtros' => $this->descripcionFiltros($f),
            'aviso' => $campanas === [] ? 'No se registraron campañas en el período con los criterios seleccionados: los indicadores figuran en cero (A1).' : null,
            'kpis' => [
                ['Campañas activadas', count($campanas), 'entero'],
                ['Ofertas emitidas', $r['emitidas'], 'entero'],
                ['Aceptadas', $r['aceptadas'], 'entero'],
                ['Rechazadas', $r['rechazadas'], 'entero'],
                ['Solicitudes originadas', $r['solicitudes'], 'entero'],
                ['Capital comprometido', Decimal::sum(array_map(static fn ($c) => Decimal::of((string) $c['capital_comprometido']), $campanas))->toFixed(2), 'importe'],
                ['Capital colocado', $r['capital_colocado'], 'importe'],
                ['Tasa de aceptación', $r['tasa_aceptacion'], 'porcentaje'],
                ['Tasa de rechazo', Indicadores::porcentaje($r['rechazadas'], $r['emitidas']), 'porcentaje'],
                ['Tasa de conversión (RN-74)', Indicadores::porcentaje($liquidadas, $r['emitidas']), 'porcentaje'],
            ],
            'secciones' => [
                ['titulo' => 'Campañas', 'columnas' => [['N.º', 'entero'], ['Línea', 'texto'], ['Activación', 'fechahora'], ['Vigencia o cierre', 'fechahora'], ['Estado o causa', 'texto'], ['Ofertas', 'entero'], ['Aceptadas', 'entero'], ['Rechazadas', 'entero'], ['Vencidas', 'entero'], ['Asignado', 'importe'], ['Comprometido', 'importe'], ['Colocado', 'importe']], 'filas' => $porCampana],
                ['titulo' => 'Comunicaciones realizadas', 'columnas' => [['Estado', 'texto'], ['Correos', 'entero']], 'filas' => array_map(static fn ($c) => [$estadosCom[$c['estado']] ?? $c['estado'], (int) $c['cantidad']], $comunicaciones)],
                ['titulo' => 'Ofertas y respuestas', 'columnas' => [['Campaña', 'entero'], ['Cliente', 'texto'], ['Importe máximo', 'importe'], ['Cuotas', 'entero'], ['Estado', 'texto'], ['Respuesta', 'fechahora'], ['Solicitud', 'entero'], ['Estado de la solicitud', 'texto']],
                    'filas' => array_map(static fn ($o) => [(int) $o['campana'], $o['denominacion'], $o['importe_maximo'], (int) $o['cantidad_cuotas'], ReglasCampanas::ESTADOS_OFERTA[$o['estado']] ?? $o['estado'], $o['fecha_respuesta'], $o['solicitud'] === null ? null : (int) $o['solicitud'], $o['solicitud_estado'] === null ? '—' : EstadosSolicitud::etiqueta((string) $o['solicitud_estado'])], $ofertas)],
                ['titulo' => 'Evolución del período', 'columnas' => [['Intervalo', 'texto'], ['Campañas activadas', 'entero'], ['Capital asignado', 'importe']], 'filas' => $this->evolucion($p, $campanas, 'activacion', ['cantidad' => null, 'capital' => 'capital_asignado'])],
            ],
        ];
    }

    // ── CU-55 Tablero gerencial ────────────────────────────────────────────────

    /**
     * CU-55 pasos 2 a 4 (A1): indicadores agrupados; cada grupo enlaza con el reporte que lo detalla (pasos 5 y 6).
     *
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    public function tablero(array $f): array
    {
        $this->hoy = $this->hoyNegocio();
        $p = Periodo::desde($f, $this->hoy);
        [$ini, $fin] = $this->limites($p);
        $k = static fn (array $r): array => array_column($r['kpis'], 1, 0);
        $cartera = $k($this->cartera($p, []));
        $morosidad = $k($this->morosidad($p, []));
        $cobranzas = $k($this->cobranzas($p, []));
        $solicitudes = $k($this->solicitudes($p, []));
        $refin = $k($this->refinanciaciones($p, []));
        $campanas = $k($this->campanas($p, []));
        $proyeccion = $k($this->proyeccion(Periodo::desde(['tipo' => 'rango'], $this->hoy, true, $this->periodoProyeccion()), []));
        $ingresosFondos = $this->uno("SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.movimientos_fondos WHERE estado = 'Realizado' AND caracter = 'Ingreso' AND fecha BETWEEN CAST(? AS date) AND CAST(? AS date)", [$p['desde'], $p['hasta']]);
        $egresosFondos = $this->uno("SELECT COALESCE(SUM(importe), 0) AS t FROM dbo.movimientos_fondos WHERE estado = 'Realizado' AND caracter = 'Egreso' AND fecha BETWEEN CAST(? AS date) AND CAST(? AS date)", [$p['desde'], $p['hasta']]);
        $desembolsos = $this->uno('SELECT COALESCE(SUM(importe_neto), 0) AS t FROM dbo.liquidaciones WHERE fecha >= ? AND fecha < ?', [$ini, $fin]);
        $ingresos = Decimal::of((string) $cobranzas['Total cobrado'])->add($ingresosFondos);
        $egresos = Decimal::of($desembolsos)->add($egresosFondos);
        $activas = (int) $this->uno("SELECT COUNT(*) AS t FROM dbo.campanas WHERE estado = 'Activa'");
        $grupos = [
            ['Flujo de caja', 'cobranzas', [['Ingresos (cobros y aportes)', $ingresos->toFixed(2), 'importe'], ['Egresos (desembolsos y gastos)', $egresos->toFixed(2), 'importe'], ['Saldo del período', $ingresos->sub($egresos)->toFixed(2), 'importe']]],
            ['Cartera crediticia', 'cartera', [['Capital colocado', $cartera['Capital colocado en el período'], 'importe'], ['Créditos activos', $cartera['Créditos activos (al día y en mora)'], 'entero'], ['Saldo deudor total', $cartera['Saldo deudor total'], 'importe']]],
            ['Morosidad', 'morosidad', [['Índice de morosidad', $morosidad['Índice de morosidad (RN-74)'], 'porcentaje'], ['Cuotas vencidas', $morosidad['Cuotas vencidas impagas'], 'entero'], ['Capital vencido', $morosidad['Capital vencido'], 'importe'], ['Punitorios acumulados', $morosidad['Intereses punitorios acumulados (con IVA)'], 'importe']]],
            ['Cobranzas y recupero', 'cobranzas', [['Cobros confirmados', $cobranzas['Total cobrado'], 'importe'], ['Recupero de mora', $cobranzas['Recupero de cartera en mora (RN-74)'], 'importe'], ['Efectividad de cobranza', $cobranzas['Efectividad de cobranza (RN-74)'], 'porcentaje']]],
            ['Solicitudes y otorgamientos', 'solicitudes', [['Registradas', $solicitudes['Solicitudes registradas'], 'entero'], ['Aprobadas', $solicitudes['Aprobadas'], 'entero'], ['Rechazadas', $solicitudes['Rechazadas'], 'entero'], ['Liquidadas', $solicitudes['Liquidadas'], 'entero']]],
            ['Refinanciaciones', 'refinanciaciones', [['Registradas', $refin['Propuestas registradas'], 'entero'], ['Aprobadas', $refin['Aprobadas (incluye aplicadas)'], 'entero'], ['Aplicadas', $refin['Aplicadas'], 'entero'], ['Deuda refinanciada', $refin['Deuda refinanciada (capital)'], 'importe']]],
            ['Campañas crediticias', 'campanas', [['Campañas activas', $activas, 'entero'], ['Ofertas emitidas', $campanas['Ofertas emitidas'], 'entero'], ['Aceptadas', $campanas['Aceptadas'], 'entero'], ['Rechazadas', $campanas['Rechazadas'], 'entero'], ['Capital comprometido', $campanas['Capital comprometido'], 'importe']]],
            ['Proyección de fondos', 'proyeccion', [['Disponibilidad proyectada', $proyeccion['Disponibilidad proyectada'], 'importe'], ['Reserva mínima', $proyeccion['Reserva mínima de fondos'], 'importe'], ['Disponible para nuevas colocaciones', $proyeccion['Capital disponible para nuevas colocaciones'], 'importe']]],
        ];
        $sinMovimientos = (int) $solicitudes['Solicitudes registradas'] === 0 && (int) $cartera['Créditos otorgados en el período'] === 0 && (int) $cobranzas['Cobros confirmados'] === 0
            && (int) $refin['Propuestas registradas'] === 0 && (int) $campanas['Campañas activadas'] === 0 && $ingresos->isZero() && $egresos->isZero();
        (new AuditoriaService($this->db))->registrar('tablero.consultar', 'reportes', null, 'Exitoso', null, ['periodo' => $p['etiqueta']]);

        return ['periodo' => $p, 'grupos' => $grupos, 'aviso' => $sinMovimientos ? 'No se registraron operaciones en el período: los indicadores de movimientos figuran en cero (A1).' : null];
    }

    // ── CU-56 Informe de caja diaria ───────────────────────────────────────────

    /**
     * CU-56 pasos 2 a 4 (A1 a A3). $soloCajero restringe el informe a las jornadas del Cajero (A2).
     *
     * @param array<string, mixed> $f desde, hasta, caja, cajero
     * @return array<string, mixed>
     */
    public function cajaDiaria(array $f, ?int $soloCajero): array
    {
        $this->hoy = $this->hoyNegocio();
        $p = Periodo::desde(['tipo' => 'rango', 'desde' => ($f['desde'] ?? '') ?: $this->hoy, 'hasta' => ($f['hasta'] ?? '') ?: (($f['desde'] ?? '') ?: $this->hoy)], $this->hoy);
        [$ini, $fin] = $this->limites($p);
        $aviso = null;
        $w = ' AND j.apertura >= ? AND j.apertura < ?';
        $b = [$ini, $fin];
        if ($soloCajero !== null) {
            if ((ctype_digit((string) ($f['cajero'] ?? '')) && (int) $f['cajero'] !== $soloCajero)) {
                $aviso = 'Solo puede consultar sus propias jornadas: el informe se restringió a ellas (A2).';
            }
            $w .= ' AND j.usuario_id = ?';
            $b[] = $soloCajero;
        } elseif (ctype_digit((string) ($f['cajero'] ?? ''))) {
            $w .= ' AND j.usuario_id = ?';
            $b[] = (int) $f['cajero'];
        }
        if (ctype_digit((string) ($f['caja'] ?? ''))) {
            $w .= ' AND j.caja_id = ?';
            $b[] = (int) $f['caja'];
        }
        $jornadas = $this->q(
            "SELECT j.id, j.apertura, j.cierre, j.estado, c.codigo, c.denominacion AS caja, u.apellido + ', ' + u.nombre AS cajero, "
            . "(SELECT COALESCE(SUM(b.importe), 0) FROM dbo.cobros b WHERE b.jornada_caja_id = j.id AND b.estado = 'Confirmado') AS cobrado, "
            . "(SELECT COUNT(*) FROM dbo.cobros b WHERE b.jornada_caja_id = j.id AND b.estado = 'Confirmado') AS cobros "
            . 'FROM dbo.jornadas_caja j JOIN dbo.cajas c ON c.id = j.caja_id JOIN dbo.usuarios_internos u ON u.id = j.usuario_id WHERE 1 = 1' . $w . ' ORDER BY j.apertura',
            $b,
        );
        $ids = array_map(static fn ($j) => (int) $j['id'], $jornadas);
        $lista = $ids === [] ? '0' : implode(', ', $ids);
        $medios = $this->q(
            "SELECT m.medio, SUM(m.importe) AS importe, COUNT(*) AS cantidad FROM dbo.cobros_medios m JOIN dbo.cobros b ON b.id = m.cobro_id WHERE b.jornada_caja_id IN ({$lista}) AND b.estado = 'Confirmado' GROUP BY m.medio ORDER BY importe DESC",
        );
        $conciliaciones = $this->q(
            "SELECT k.jornada_caja_id, k.medio, k.total_registrado, k.total_acreditado, k.diferencia, k.explicacion, c.codigo, j.apertura FROM dbo.conciliaciones_jornada k JOIN dbo.jornadas_caja j ON j.id = k.jornada_caja_id JOIN dbo.cajas c ON c.id = j.caja_id "
            . "WHERE k.jornada_caja_id IN ({$lista}) ORDER BY j.apertura, k.medio",
        );
        $ordenes = $this->q(
            "SELECT o.numero, o.emision, o.vence, o.importe, o.estado, cl.denominacion FROM dbo.ordenes_pago_electronico o JOIN dbo.clientes cl ON cl.id = o.cliente_id "
            . "WHERE o.emision >= ? AND o.emision < ? AND o.estado IN ('PendienteConfirmacion', 'EnConciliacion') ORDER BY o.emision",
            [$ini, $fin],
        );
        $suma = static fn (array $g, string $k): string => Decimal::sum(array_map(static fn ($x) => Decimal::of((string) $x[$k]), $g))->toFixed(2);
        (new AuditoriaService($this->db))->registrar('cajas.informe-diario', 'jornadas_caja', null, 'Exitoso', null, ['periodo' => $p['etiqueta'], 'jornadas' => count($jornadas)]);

        return [
            'periodo' => $p, 'aviso' => $aviso, 'jornadas' => $jornadas, 'medios' => $medios, 'conciliaciones' => $conciliaciones, 'ordenes' => $ordenes,
            'totales' => ['cobrado' => $suma($jornadas, 'cobrado'), 'diferencias' => $suma($conciliaciones, 'diferencia'), 'ordenes' => $suma($ordenes, 'importe'), 'abiertas' => count(array_filter($jornadas, static fn ($j) => $j['estado'] === 'Abierta'))],
        ];
    }

    // ── Opciones de los formularios ────────────────────────────────────────────

    /** @return array<string, list<array<string, mixed>>> */
    public function opciones(): array
    {
        return [
            'lineas' => $this->q('SELECT id, nombre FROM dbo.lineas_credito ORDER BY nombre'),
            'analistas' => $this->q("SELECT u.id, u.apellido + ', ' + u.nombre AS nombre FROM dbo.usuarios_internos u JOIN dbo.roles r ON r.id = u.rol_id WHERE r.codigo = 'AnalistaCredito' ORDER BY u.apellido"),
            'cajeros' => $this->q("SELECT u.id, u.apellido + ', ' + u.nombre AS nombre FROM dbo.usuarios_internos u JOIN dbo.roles r ON r.id = u.rol_id WHERE r.codigo = 'Cajero' ORDER BY u.apellido"),
            'cajas' => $this->q('SELECT id, codigo + \' · \' + denominacion AS nombre FROM dbo.cajas ORDER BY codigo'),
        ];
    }

    /** @return array<string, string> */
    public static function medios(): array
    {
        return ReglasCaja::MEDIOS_VENTANILLA + ['Efectivo' => 'Efectivo', 'PagoElectronico' => 'Pago electrónico'];
    }

    // ── Utilidades ─────────────────────────────────────────────────────────────

    /**
     * Filtro de créditos por línea, estado, categoría de riesgo y cliente.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function filtroCredito(array $f): array
    {
        $w = '';
        $b = [];
        if (ctype_digit((string) ($f['linea'] ?? ''))) {
            $w .= ' AND v.linea_credito_id = ?';
            $b[] = (int) $f['linea'];
        }
        if (in_array($f['estado'] ?? '', ['Activo', 'EnMora', 'Cancelado', 'Refinanciado', 'Revocado'], true)) {
            $w .= ' AND c.estado = ?';
            $b[] = $f['estado'];
        }
        if (in_array($f['riesgo'] ?? '', ['A', 'B', 'C', 'D', 'E'], true)) {
            $w .= ' AND ' . self::RIESGO . ' = ?';
            $b[] = $f['riesgo'];
        }
        $clientes = $this->clientes((string) ($f['cliente'] ?? ''));
        if ($clientes !== null) {
            $w .= $clientes === [] ? ' AND 1 = 0' : ' AND c.cliente_id IN (' . implode(', ', $clientes) . ')';
        }

        return [$w, $b];
    }

    /**
     * Clientes que coinciden con el texto (nombre, CUIT/CUIL o documento), o null sin filtro.
     *
     * @return list<int>|null
     */
    private function clientes(string $texto): ?array
    {
        $t = trim($texto);
        if ($t === '') {
            return null;
        }
        $digitos = preg_replace('/\D/', '', $t) ?? '';
        $b = $this->db->table('clientes c')->select('c.id')->join('personas_humanas ph', 'ph.cliente_id = c.id', 'left')->groupStart()->like('c.denominacion', $t);
        if ($digitos !== '') {
            $b->orWhere('c.cuit_cuil', $digitos)->orWhere('ph.nro_documento', $digitos);
        }

        return array_map('intval', array_column($b->groupEnd()->limit(500)->get()->getResultArray(), 'id'));
    }

    /** @return list<string> */
    private function descripcionFiltros(array $f): array
    {
        $r = [];
        foreach (['linea' => 'Línea', 'estado' => 'Estado', 'riesgo' => 'Categoría de riesgo', 'antiguedad' => 'Antigüedad', 'cliente' => 'Cliente', 'analista' => 'Analista', 'caja' => 'Caja', 'cajero' => 'Cajero', 'medio' => 'Medio', 'canal' => 'Canal', 'categoria' => 'Categoría de ingresos', 'campana' => 'Campaña'] as $k => $t) {
            if (($f[$k] ?? '') !== '' && !is_array($f[$k])) {
                $valor = match ($k) {
                    'linea' => (string) ($this->db->table('lineas_credito')->select('nombre')->where('id', (int) $f[$k])->get()->getRow()->nombre ?? $f[$k]),
                    'antiguedad' => Indicadores::TRAMOS[$f[$k]] ?? (string) $f[$k],
                    'medio' => self::medios()[$f[$k]] ?? (string) $f[$k],
                    'analista', 'cajero' => $f[$k] === 'portal' ? 'Portal (ofertas)' : (string) ($this->db->query("SELECT apellido + ', ' + nombre AS n FROM dbo.usuarios_internos WHERE id = ?", [(int) $f[$k]])->getRow()->n ?? $f[$k]),
                    'caja' => (string) ($this->db->table('cajas')->select('codigo')->where('id', (int) $f[$k])->get()->getRow()->codigo ?? $f[$k]),
                    default => (string) $f[$k],
                };
                $r[] = "{$t}: {$valor}";
            }
        }

        return $r;
    }

    /**
     * Evolución del período: cantidad y sumas por intervalo de las filas según su fecha.
     *
     * @param list<array<string, mixed>> $filas
     * @param array<string, string|null> $medidas nombre => columna a sumar (null cuenta filas)
     * @return list<list<mixed>>
     */
    private function evolucion(array $p, array $filas, string $columnaFecha, array $medidas): array
    {
        $intervalos = Periodo::intervalos($p['desde'], $p['hasta'], $p['granularidad']);
        $acum = [];
        foreach ($filas as $x) {
            $clave = Periodo::clave($this->fechaLocal((string) $x[$columnaFecha]), $p['granularidad']);
            foreach ($medidas as $m => $col) {
                $acum[$clave][$m] = $col === null ? ($acum[$clave][$m] ?? 0) + 1 : Decimal::of((string) ($acum[$clave][$m] ?? '0'))->add((string) $x[$col])->toFixed(2);
            }
        }

        return array_map(static fn ($k, $t) => [$t, ...array_map(static fn ($m, $col) => $acum[$k][$m] ?? ($col === null ? 0 : '0.00'), array_keys($medidas), $medidas)], array_keys($intervalos), $intervalos);
    }

    private function enPeriodo(string $fecha, array $p, bool $convertir = true): bool
    {
        if ($fecha === '') {
            return false;
        }
        $d = $convertir ? $this->fechaLocal($fecha) : substr($fecha, 0, 10);

        return $d >= $p['desde'] && $d <= $p['hasta'];
    }

    /** Fecha de negocio (aaaa-mm-dd) de un instante o una fecha de la base. */
    private function fechaLocal(string $valor): string
    {
        if (strlen($valor) <= 10) {
            return $valor;
        }

        return Time::parse($valor)->setTimezone(config('Sigccr')->zonaNegocio)->format('Y-m-d');
    }

    /** @return array{0: string, 1: string} instantes UTC del inicio del período y del día siguiente a su fin */
    private function limites(array $p): array
    {
        $zona = config('Sigccr')->zonaNegocio;

        return [
            Time::parse($p['desde'] . ' 00:00:00', $zona)->setTimezone('UTC')->format('Y-m-d H:i:s'),
            Time::parse($p['hasta'] . ' 00:00:00', $zona)->addDays(1)->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];
    }

    private function periodoProyeccion(): int
    {
        return (int) ((new ParametroService($this->db))->vigente('Campanas')['valores']['periodo_proyeccion_dias'] ?? 30);
    }

    /** @return list<array<string, mixed>> */
    private function q(string $sql, array $b = []): array
    {
        return $this->db->query($sql, $b)->getResultArray();
    }

    /** Valor de la columna t de la primera fila. */
    public function uno(string $sql, array $b = []): string
    {
        return (string) ($this->db->query($sql, $b)->getRowArray()['t'] ?? '0');
    }
}
