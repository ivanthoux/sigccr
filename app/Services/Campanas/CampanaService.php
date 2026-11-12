<?php

declare(strict_types=1);

namespace App\Services\Campanas;

use App\Exceptions\AccesoAjenoException;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Campanas\ReglasCampanas;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Lineas\Elegibilidad;
use App\Libraries\Solicitudes\Estructurador;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorParametrosCampanas as P;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;
use App\Services\Comunes\ParametroService;
use App\Services\Fondos\FondosService;
use App\Services\Solicitudes\EstructuracionService;
use App\Services\Solicitudes\EvaluacionService;
use App\Services\Solicitudes\SimulacionService;
use App\Services\Solicitudes\SolicitudService;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;

/**
 * Campañas crediticias (SS-08, D-43): CU-50 Consultar, CU-52 Registrar respuesta a oferta, CU-93 Activar y CU-94
 * Cerrar. CU-51 se registra con ParametroService (tipo Campanas).
 */
class CampanaService extends BaseService
{
    public const PROCESO_CIERRE = 'campanas:cerrar';
    private const OBJETO = 'campanas';

    // ── CU-93 Activar Campaña Crediticia ───────────────────────────────────────

    /**
     * CU-93 (A1, A2, A3). Se ejecuta desde CU-92 cuando la disponibilidad proyectada supera el umbral y no hay otra
     * campaña activa (RN-66). Devuelve el resultado para el registro del proceso.
     *
     * @param array{id: int, proyectado: string} $proyeccion
     */
    public function activar(array $proyeccion, ?string $proceso = null): string
    {
        return $this->transaccion(function () use ($proyeccion, $proceso): string {
            $this->db->query("DECLARE @r INT; EXEC @r = sp_getapplock @Resource = 'sigccr:campanas', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 10000; SELECT @r AS r;");
            if ($this->db->table('campanas')->where('estado', 'Activa')->countAllResults() > 0) {
                return 'ya existe una campaña activa (RN-66)';
            }
            $param = (new ParametroService($this->db))->vigente('Campanas');
            $p = P::completar($param['valores']);
            $alertas = new AlertaService($this->db);
            $linea = $p['linea_id'] === null ? null : $this->db->table('lineas_credito')->where('id', (int) $p['linea_id'])->get()->getRowArray();
            $version = $linea === null || $linea['estado'] !== 'Activa' ? null : (new SimulacionService($this->db))->versionVigente((int) $linea['id']);
            if ($version === null) {
                // A2: sin línea habilitada para campañas, o suspendida (RN-13)
                $causa = $linea === null ? 'No hay una línea de crédito habilitada para campañas.' : "La línea {$linea['nombre']} habilitada para campañas no está activa.";
                $alertas->generar('Proceso', 'No se activó la campaña: ' . $causa, 'Configurar la línea de las campañas en los parámetros de campañas (CU-51) o reactivar la línea.', 'Gerente', null, 'proyecciones_fondos', (int) $proyeccion['id']);
                (new AuditoriaService($this->db))->registrar('campanas.activar', 'proyecciones_fondos', (int) $proyeccion['id'], 'Rechazado', $causa, null, $proceso);

                return 'no se activó: ' . $causa;
            }

            $hoy = $this->hoyNegocio();
            $ofertas = [];
            $descartes = [];
            foreach ($this->candidatos((int) $linea['id'], $hoy) as $c) {
                $motivo = ReglasCampanas::motivoNoElegible($c, $p, $hoy) ?? Elegibilidad::motivoNoAdmitida($version, $c['datos'], $hoy);
                $condiciones = $motivo === null ? $this->condiciones($c['datos'], $version, $hoy) : null;
                if ($condiciones === null) {
                    $descartes[$motivo ?? 'Sin capacidad de pago para la línea.'] = ($descartes[$motivo ?? 'Sin capacidad de pago para la línea.'] ?? 0) + 1;
                    continue;
                }
                $ofertas[] = ['cliente' => $c['datos'], 'correo' => $c['correo'], 'nombre' => $c['nombre']] + $condiciones;
                if (count($ofertas) >= (int) $p['cantidad_maxima_ofertas']) {
                    break;
                }
            }
            if ($ofertas === []) {
                // A1: sin clientes elegibles
                $alertas->generar('Proceso', 'La disponibilidad proyectada habilitaba una campaña, pero no hay clientes elegibles.', 'Revisar los criterios de elegibilidad de los parámetros de campañas (CU-51).', 'Gerente', null, 'proyecciones_fondos', (int) $proyeccion['id']);
                (new AuditoriaService($this->db))->registrar('campanas.activar', 'proyecciones_fondos', (int) $proyeccion['id'], 'Rechazado', 'Sin clientes elegibles (A1)', ['descartes' => $descartes], $proceso);

                return 'no se activó: sin clientes elegibles';
            }

            $ahora = $this->ahoraUtc();
            $vence = Time::now('UTC')->addDays((int) $p['duracion_dias'])->format('Y-m-d H:i:s.vP');
            $asignado = ReglasCampanas::capitalAsignado((string) $p['capital_maximo'], (string) $proyeccion['proyectado']);
            $this->db->table('campanas')->insert([
                'proyeccion_fondos_id' => (int) $proyeccion['id'], 'version_condiciones_id' => (int) $version['version_id'], 'version_parametros_id' => (int) $param['id'],
                'activacion' => $ahora, 'vence' => $vence, 'capital_asignado' => $asignado->toFixed(2), 'estado' => 'Activa',
            ]);
            $id = (int) $this->db->insertID();
            $numero = (int) $this->db->table('campanas')->select('numero')->where('id', $id)->get()->getRow()->numero;
            $comunicaciones = new ComunicacionService();
            $venceLocal = Time::parse($vence, 'UTC')->setTimezone(config('Sigccr')->zonaNegocio)->format('d/m/Y H:i');
            foreach ($ofertas as $o) {
                $this->db->table('ofertas')->insert([
                    'campana_id' => $id, 'cliente_id' => (int) $o['cliente']['id'], 'importe_maximo' => $o['importe']->toFixed(2), 'cantidad_cuotas' => $o['cuotas'],
                    'tna' => Decimal::of((string) $version['tna'])->toFixed(12), 'tea' => $o['tea']->toFixed(12), 'cftea' => $o['cftea']->toFixed(12), 'vence' => $vence, 'estado' => 'Vigente',
                ]);
                $ofertaId = (int) $this->db->insertID();
                // A3: la comunicación queda en la cola y se reintenta si el servicio no está disponible (RNF-09)
                foreach ((array) $p['canales'] as $canal) {
                    if ($canal === 'Correo') {
                        $comunicaciones->encolar('Oferta', $o['correo'], 'oferta_crediticia', [
                            'nombre' => $o['nombre'], 'linea' => (string) $linea['nombre'], 'importe' => formato_importe($o['importe']->toFixed(2)),
                            'cuotas' => (string) $o['cuotas'], 'primera_cuota' => formato_importe($o['primera_cuota']->toFixed(2)),
                            'tna' => formato_porcentaje((string) $version['tna']), 'cftea' => formato_porcentaje($o['cftea']->toFixed(12)),
                            'vence' => $venceLocal, 'enlace' => site_url('portal/ofertas/' . $ofertaId), 'mensaje' => (string) $p['mensaje'],
                        ], 'ofertas', $ofertaId);
                    }
                }
            }
            $detalle = ['numero' => $numero, 'linea' => $linea['codigo'], 'version_condiciones' => (int) $version['version'], 'version_parametros' => (int) $param['numero'],
                'capital_asignado' => $asignado->toFixed(2), 'ofertas' => count($ofertas), 'descartes' => $descartes, 'vence' => $vence];
            (new AuditoriaService($this->db))->registrar('campanas.activar', self::OBJETO, $id, 'Exitoso', null, $detalle, $proceso);
            $alertas->generar('Operativa', "Se activó la campaña N.º {$numero} ({$linea['nombre']}) con " . count($ofertas) . ' oferta(s) y un capital asignado de ' . formato_importe($asignado->toFixed(2)) . '.', 'Seguir sus respuestas en Consultar campañas (CU-50).', 'Gerente', null, self::OBJETO, $id);

            return "activada la campaña N.º {$numero} con " . count($ofertas) . ' oferta(s)';
        });
    }

    /**
     * CU-93 paso 3: clientes activos de la entidad con los datos de la elegibilidad.
     *
     * @return list<array<string, mixed>>
     */
    private function candidatos(int $lineaId, string $hoy): array
    {
        $finales = implode(', ', array_fill(0, count(EstadosSolicitud::FINALES), '?'));
        $filas = $this->db->query(
            'SELECT c.id, c.categoria_ingresos, c.fecha_alta, c.correo, COALESCE(ph.nombre, pj.representante_nombre, c.denominacion) AS nombre, '
            . "CASE WHEN ca.estado = 'Habilitada' THEN 1 ELSE 0 END AS portal, "
            . "(SELECT TOP (1) k.otorgado FROM dbo.consentimientos k WHERE k.cliente_id = c.id AND k.tipo = 'ComunicacionesComerciales' ORDER BY k.fecha DESC, k.id DESC) AS consentimiento, "
            . "(SELECT MAX(DATEDIFF(day, q.vencimiento, CAST(? AS date))) FROM dbo.cuotas q JOIN dbo.creditos k ON k.id = q.credito_id WHERE k.cliente_id = c.id AND k.estado IN ('Activo', 'EnMora') "
            . "AND q.estado IN ('Pendiente', 'Vencida') AND q.vencimiento < CAST(? AS date)) AS atraso, "
            . "(SELECT COUNT(*) FROM dbo.creditos k WHERE k.cliente_id = c.id AND k.estado IN ('Activo', 'EnMora')) AS vigentes, "
            . "(SELECT COUNT(*) FROM dbo.solicitudes_credito s JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id WHERE s.cliente_id = c.id AND v.linea_credito_id = ? AND s.estado NOT IN ({$finales})) AS en_tramite "
            . "FROM dbo.clientes c JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id "
            . "LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id WHERE c.estado = 'Activo' ORDER BY c.fecha_alta, c.id",
            [$hoy, $hoy, $lineaId, ...EstadosSolicitud::FINALES],
        )->getResultArray();
        $simulaciones = new SimulacionService($this->db);

        return array_map(static fn (array $f): array => [
            'categoria_ingresos' => (string) $f['categoria_ingresos'], 'fecha_alta' => (string) $f['fecha_alta'],
            'portal_habilitado' => (int) $f['portal'] === 1, 'consentimiento' => (int) ($f['consentimiento'] ?? 0) === 1,
            'max_dias_atraso' => max(0, (int) ($f['atraso'] ?? 0)), 'creditos_vigentes' => (int) $f['vigentes'], 'solicitud_en_tramite' => (int) $f['en_tramite'] > 0,
            'correo' => (string) $f['correo'], 'nombre' => (string) $f['nombre'], 'datos' => $simulaciones->cliente((int) $f['id']),
        ], $filas);
    }

    /**
     * CU-93 paso 4 (CU-87): condiciones de la oferta para un cliente con sus ingresos y obligaciones actuales.
     *
     * @param array<string, mixed> $cliente fila de SimulacionService::cliente()
     * @param array<string, mixed> $version versión vigente de la línea
     * @return array{importe: Decimal, cuotas: int, primera_cuota: Decimal, tea: Decimal, cftea: Decimal}|null
     */
    private function condiciones(array $cliente, array $version, string $hoy): ?array
    {
        return ReglasCampanas::condicionesOferta($this->alternativas($cliente, (int) $version['version_id'], $hoy));
    }

    /**
     * Alternativas de financiación del cliente para una versión de condiciones (CU-87), sin registrarlas.
     *
     * @param array<string, mixed> $cliente
     * @return list<array<string, mixed>>
     */
    private function alternativas(array $cliente, int $versionId, string $hoy): array
    {
        $version = (new EstructuracionService($this->db))->version($versionId);
        $internos = (new EvaluacionService($this->db))->antecedentesInternos(['id' => $cliente['id'], 'ingresos_declarados' => $cliente['ingresos_declarados'], 'fecha_alta' => $cliente['fecha_alta']], 0);
        $parametros = (new ParametroService($this->db))->vigente('Estructuracion')['valores'];

        return Estructurador::generar($version, $parametros, Decimal::of($internos['ingresos']), Decimal::of($internos['obligaciones']), '0', 0, new DateTimeImmutable($hoy))['alternativas'];
    }

    // ── CU-94 Cerrar Campaña Crediticia ────────────────────────────────────────

    /**
     * CU-94 paso 1: campañas activas con su vigencia cumplida o su cupo alcanzado.
     *
     * @return list<array{id: int, causa: string}>
     */
    public function paraCerrar(): array
    {
        $ahora = new DateTimeImmutable('now');
        $r = [];
        foreach ($this->db->table('campanas')->where('estado', 'Activa')->get()->getResultArray() as $c) {
            $causa = ReglasCampanas::causaCierre((string) $c['vence'], $ahora, (string) $c['capital_comprometido'], (string) $c['capital_asignado']);
            if ($causa !== null) {
                $r[] = ['id' => (int) $c['id'], 'causa' => $causa];
            }
        }

        return $r;
    }

    /**
     * CU-94 pasos 2 a 6 (A1, A2). Puede ejecutarse dentro de la transacción de CU-52 (cupo alcanzado) o de CU-92.
     */
    public function cerrar(int $id, string $causa, ?string $proceso = null): bool
    {
        return $this->transaccion(function () use ($id, $causa, $proceso): bool {
            $c = $this->bloquear('campanas', [$id])[0] ?? null;
            if ($c === null || $c['estado'] !== 'Activa') {
                return false;
            }
            $ahora = $this->ahoraUtc();
            $this->db->query(
                "UPDATE dbo.campanas SET estado = 'Finalizada', fecha_cierre = ?, causa_cierre = ?, version_fila = version_fila + 1 WHERE id = ?",
                [$ahora, $causa, $id],
            );
            $this->db->query(
                "UPDATE dbo.comunicaciones SET estado = 'Cancelada', datos_cifrados = NULL, version_fila = version_fila + 1 WHERE tipo = 'Oferta' AND origen_tipo = 'ofertas' "
                . "AND estado IN ('Pendiente', 'ReintentoProgramado') AND origen_id IN (SELECT id FROM dbo.ofertas WHERE campana_id = ? AND estado = 'Vigente')",
                [$id],
            );
            $this->db->query("UPDATE dbo.ofertas SET estado = 'Vencida', version_fila = version_fila + 1 WHERE campana_id = ? AND estado = 'Vigente'", [$id]);
            $resultados = ReglasCampanas::resultados($this->ofertasConResultados($id));
            (new AuditoriaService($this->db))->registrar('campanas.cerrar', self::OBJETO, $id, 'Exitoso', ReglasCampanas::CAUSAS[$causa] ?? $causa, $resultados + [
                'capital_asignado' => (string) $c['capital_asignado'], 'capital_comprometido' => (string) $c['capital_comprometido'],
            ], $proceso);
            (new AlertaService($this->db))->generar('Operativa', "Se cerró la campaña N.º {$c['numero']}: " . mb_strtolower(ReglasCampanas::CAUSAS[$causa] ?? $causa) . ". {$resultados['aceptadas']} de {$resultados['emitidas']} oferta(s) aceptada(s).", 'Consultar sus resultados en Consultar campañas (CU-50).', 'Gerente', null, self::OBJETO, $id);

            return true;
        });
    }

    // ── CU-50 Consultar Campaña Crediticia ─────────────────────────────────────

    /**
     * CU-50 pasos 2 a 4 (A1).
     *
     * @param array<string, mixed> $f numero, linea, estado, desde, hasta, canal
     * @return list<array<string, mixed>>
     */
    public function buscar(array $f): array
    {
        $b = $this->db->table('campanas c')
            ->select('c.*, l.nombre AS linea, l.codigo AS linea_codigo, v.tna, v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas, (SELECT COUNT(*) FROM dbo.ofertas o WHERE o.campana_id = c.id) AS ofertas', false)
            ->join('versiones_condiciones v', 'v.id = c.version_condiciones_id')->join('lineas_credito l', 'l.id = v.linea_credito_id');
        if (ctype_digit((string) ($f['numero'] ?? ''))) {
            $b->where('c.numero', (int) $f['numero']);
        }
        if (ctype_digit((string) ($f['linea'] ?? ''))) {
            $b->where('l.id', (int) $f['linea']);
        }
        if (in_array($f['estado'] ?? '', ['Activa', 'Finalizada'], true)) {
            $b->where('c.estado', $f['estado']);
        }
        $desde = ($f['desde'] ?? '') === '' ? null : Campos::fecha($f['desde']);
        $hasta = ($f['hasta'] ?? '') === '' ? null : Campos::fecha($f['hasta']);
        if ($desde !== null) {
            $b->where('c.activacion >=', Time::parse($desde . ' 00:00:00', config('Sigccr')->zonaNegocio)->setTimezone('UTC')->format('Y-m-d H:i:s'));
        }
        if ($hasta !== null) {
            $b->where('c.activacion <', Time::parse($hasta . ' 00:00:00', config('Sigccr')->zonaNegocio)->addDays(1)->setTimezone('UTC')->format('Y-m-d H:i:s'));
        }
        // Todas las campañas se comunican por correo: el filtro de canal solo admite ese valor (CU-51)
        if (($f['canal'] ?? '') !== '' && !isset(P::CANALES[$f['canal']])) {
            return [];
        }

        return $b->orderBy('c.activacion', 'DESC')->limit(200)->get()->getResultArray();
    }

    /**
     * CU-50 paso 6 (A2): detalle con sus condiciones, ofertas, comunicaciones, respuestas, solicitudes y resultados.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $c = $this->db->query(
            'SELECT c.*, l.nombre AS linea, l.codigo AS linea_codigo, v.numero AS version_condiciones, v.tna, v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas, '
            . 'vp.numero AS version_parametros, vp.valores AS parametros, p.disponible_proyectado, p.periodo_dias, p.fecha AS fecha_proyeccion '
            . 'FROM dbo.campanas c JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . 'JOIN dbo.versiones_parametros vp ON vp.id = c.version_parametros_id JOIN dbo.proyecciones_fondos p ON p.id = c.proyeccion_fondos_id WHERE c.id = ?',
            [$id],
        )->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('La campaña no existe.');
        }
        $c['parametros'] = P::completar((array) json_decode((string) $c['parametros'], true));
        $c['ofertas'] = $this->ofertasConResultados($id);
        $c['resultados'] = ReglasCampanas::resultados($c['ofertas']);
        $c['comunicaciones'] = $this->db->query(
            "SELECT estado, COUNT(*) AS cantidad FROM dbo.comunicaciones WHERE tipo = 'Oferta' AND origen_tipo = 'ofertas' AND origen_id IN (SELECT id FROM dbo.ofertas WHERE campana_id = ?) GROUP BY estado",
            [$id],
        )->getResultArray();
        (new AuditoriaService($this->db))->registrar('campanas.consultar', self::OBJETO, $id);

        return $c;
    }

    /**
     * CU-50 A4: detalle de una oferta con sus comunicaciones, respuesta y solicitud vinculada.
     *
     * @return array<string, mixed>
     */
    public function oferta(int $id): array
    {
        $o = $this->filaOferta($id);
        if ($o === null) {
            throw new ReglaNegocioException('La oferta no existe.');
        }
        $o['comunicaciones'] = $this->db->query(
            "SELECT id, estado, intentos, creado_en, programada_para FROM dbo.comunicaciones WHERE tipo = 'Oferta' AND origen_tipo = 'ofertas' AND origen_id = ? ORDER BY id",
            [$id],
        )->getResultArray();
        (new AuditoriaService($this->db))->registrar('campanas.consultar-oferta', 'ofertas', $id);

        return $o;
    }

    /** @return list<array<string, mixed>> */
    private function ofertasConResultados(int $campanaId): array
    {
        return $this->db->query(
            'SELECT o.*, cl.denominacion, cl.cuit_cuil, s.numero AS solicitud_numero, s.estado AS solicitud_estado, s.importe AS solicitud_importe, '
            . '(SELECT TOP (1) l.capital FROM dbo.liquidaciones l WHERE l.solicitud_id = s.id) AS liquidado '
            . 'FROM dbo.ofertas o JOIN dbo.clientes cl ON cl.id = o.cliente_id LEFT JOIN dbo.solicitudes_credito s ON s.id = o.solicitud_id '
            . 'WHERE o.campana_id = ? ORDER BY cl.denominacion',
            [$campanaId],
        )->getResultArray();
    }

    /** @return array<string, mixed>|null */
    private function filaOferta(int $id, bool $bloquear = false): ?array
    {
        return $this->db->query(
            'SELECT o.*, c.numero AS campana_numero, c.estado AS campana_estado, c.vence AS campana_vence, c.capital_asignado, c.capital_comprometido, c.version_condiciones_id, '
            . 'v.linea_credito_id, l.nombre AS linea, cl.denominacion, cl.cuit_cuil, s.numero AS solicitud_numero, s.estado AS solicitud_estado, '
            . 'CASE WHEN o.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS en_vigencia '
            . 'FROM dbo.ofertas o' . ($bloquear ? ' WITH (UPDLOCK, ROWLOCK)' : '') . ' JOIN dbo.campanas c ON c.id = o.campana_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id '
            . 'JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id JOIN dbo.clientes cl ON cl.id = o.cliente_id LEFT JOIN dbo.solicitudes_credito s ON s.id = o.solicitud_id WHERE o.id = ?',
            [$id],
        )->getRowArray();
    }

    // ── CU-52 Registrar Respuesta a Oferta Crediticia ──────────────────────────

    /**
     * CU-52 pasos 1 y 2: ofertas del cliente, primero las vigentes.
     *
     * @return list<array<string, mixed>>
     */
    public function ofertasDelCliente(int $clienteId): array
    {
        return $this->db->query(
            'SELECT o.*, c.numero AS campana_numero, l.nombre AS linea, s.numero AS solicitud_numero, CASE WHEN o.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS en_vigencia '
            . 'FROM dbo.ofertas o JOIN dbo.campanas c ON c.id = o.campana_id JOIN dbo.versiones_condiciones v ON v.id = c.version_condiciones_id '
            . 'JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id LEFT JOIN dbo.solicitudes_credito s ON s.id = o.solicitud_id '
            . "WHERE o.cliente_id = ? ORDER BY CASE WHEN o.estado = 'Vigente' THEN 0 ELSE 1 END, o.vence DESC",
            [$clienteId],
        )->getResultArray();
    }

    /**
     * CU-52 pasos 3, 4 y 7: detalle de una oferta propia con las alternativas que el cliente puede elegir hoy.
     *
     * @return array<string, mixed>
     */
    public function ofertaParaCliente(int $clienteId, int $id): array
    {
        $o = $this->filaOferta($id);
        if ($o === null || (int) $o['cliente_id'] !== $clienteId) {
            $this->denegar($id, $o === null);
        }
        $o['aceptables'] = [];
        $o['primera_cuota'] = null;
        if ($o['estado'] === 'Vigente' && (int) $o['en_vigencia'] === 1 && $o['campana_estado'] === 'Activa') {
            $cliente = (new SimulacionService($this->db))->cliente($clienteId);
            $o['aceptables'] = ReglasCampanas::aceptables($this->alternativas($cliente, (int) $o['version_condiciones_id'], $this->hoyNegocio()), (string) $o['importe_maximo']);
            foreach ($o['aceptables'] as $a) {
                if ($a['importe']->compare((string) $o['importe_maximo']) === 0 && $a['cuotas'] === (int) $o['cantidad_cuotas']) {
                    $o['primera_cuota'] = $a['primera_cuota'];
                }
            }
        }
        (new AuditoriaService($this->db))->registrar('portal.consultar-oferta', 'ofertas', $id);

        return $o;
    }

    /**
     * CU-52 pasos 6 a 10 (A2, A3, A4, A7): acepta la oferta con la alternativa elegida. Genera la solicitud con origen
     * Oferta, constituye el compromiso temporal (RN-41) y, si se alcanza el cupo, cierra la campaña (CU-94).
     *
     * @return array{solicitud_id: int, numero: int, cupo_alcanzado: bool}
     */
    public function aceptar(int $clienteId, int $id, int $versionLeida, string $clave): array
    {
        // A2: si la oferta perdió vigencia, cupo o condiciones, queda vencida aunque la aceptación no se registre
        $r = $this->transaccion(function () use ($clienteId, $id, $versionLeida, $clave): array {
            $o = $this->filaOferta($id, true);
            if ($o === null || (int) $o['cliente_id'] !== $clienteId) {
                $this->denegar($id, $o === null);
            }
            if ($o['solicitud_id'] !== null) {
                throw new ReglaNegocioException("Ya aceptó esta oferta: generó la solicitud N.º {$o['solicitud_numero']} y no puede generarse otra (A3, RN-42).", 'RN-42');
            }
            if ((int) $o['version_fila'] !== $versionLeida) {
                throw new ReglaNegocioException('La oferta cambió desde que la abrió: revísela nuevamente.');
            }
            $campana = $this->bloquear('campanas', [(int) $o['campana_id']])[0];
            if ($o['estado'] !== 'Vigente') {
                throw new ReglaNegocioException('La oferta ya fue respondida o venció: no puede aceptarse.', 'RN-42');
            }
            if ((int) $o['en_vigencia'] !== 1 || $campana['estado'] !== 'Activa') {
                return $this->vencerOferta($id, 'La oferta perdió vigencia porque la campaña finalizó: no se generó la solicitud (A2).');
            }
            $cliente = (new SimulacionService($this->db))->cliente($clienteId);
            $hoy = $this->hoyNegocio();
            $eleccion = ReglasCampanas::eleccion(ReglasCampanas::aceptables($this->alternativas($cliente, (int) $o['version_condiciones_id'], $hoy), (string) $o['importe_maximo']), $clave);
            $importe = $eleccion['importe']->toFixed(2);
            // Cupo de la campaña (RN-40): la aceptación no puede superar el capital asignado
            if (Decimal::of((string) $campana['capital_comprometido'])->add($importe)->compare((string) $campana['capital_asignado']) > 0) {
                return $this->vencerOferta($id, 'La campaña ya no tiene cupo para esta oferta: quedó vencida y no se generó la solicitud (A2).');
            }
            // A7: la línea no admite otra solicitud no finalizada del cliente (RN-18); lo controla también el registro
            $version = (new SimulacionService($this->db))->versionVigente((int) $o['linea_credito_id']);
            if ($version === null || (int) $version['version_id'] !== (int) $o['version_condiciones_id']) {
                return $this->vencerOferta($id, 'Las condiciones de la línea cambiaron desde que se emitió la oferta: quedó vencida y no se generó la solicitud (A2).');
            }
            $simulacion = (new SimulacionService($this->db))->simular($clienteId, (int) $o['linea_credito_id'], $importe, (string) $eleccion['cuotas'], null);
            $s = (new SolicitudService($this->db))->registrar($simulacion, [], null, 'Oferta');
            if ((new FondosService($this->db))->comprometerParaSolicitud($s['id'], $importe) === null) {
                throw new ReglaNegocioException('En este momento la entidad no tiene fondos disponibles para esta oferta: intente más tarde.', 'RN-41');
            }
            $ahora = $this->ahoraUtc();
            $this->db->query(
                "UPDATE dbo.ofertas SET estado = 'Aceptada', fecha_respuesta = ?, solicitud_id = ?, version_fila = version_fila + 1 WHERE id = ?",
                [$ahora, $s['id'], $id],
            );
            $comprometido = Decimal::of((string) $campana['capital_comprometido'])->add($importe);
            $this->db->query('UPDATE dbo.campanas SET capital_comprometido = ?, version_fila = version_fila + 1 WHERE id = ?', [$comprometido->toFixed(2), $campana['id']]);
            (new AuditoriaService($this->db))->registrar('campanas.aceptar-oferta', 'ofertas', $id, 'Exitoso', null, [
                'campana' => (int) $o['campana_numero'], 'solicitud' => $s['numero'], 'importe' => $importe, 'cuotas' => $eleccion['cuotas'],
            ]);
            $cupo = $comprometido->compare((string) $campana['capital_asignado']) >= 0;
            if ($cupo) {
                $this->cerrar((int) $campana['id'], 'Cupo');
            }

            return ['solicitud_id' => $s['id'], 'numero' => $s['numero'], 'cupo_alcanzado' => $cupo];
        });
        if (isset($r['vencida'])) {
            throw new ReglaNegocioException($r['vencida'], 'RN-43');
        }

        return $r;
    }

    /** CU-52 A1: rechazo de una oferta vigente; no genera solicitud. */
    public function rechazar(int $clienteId, int $id, int $versionLeida): void
    {
        $this->transaccion(function () use ($clienteId, $id, $versionLeida): void {
            $o = $this->filaOferta($id, true);
            if ($o === null || (int) $o['cliente_id'] !== $clienteId) {
                $this->denegar($id, $o === null);
            }
            if ($o['estado'] !== 'Vigente') {
                throw new ReglaNegocioException('La oferta ya fue respondida o venció: no puede rechazarse.');
            }
            if ((int) $o['version_fila'] !== $versionLeida) {
                throw new ReglaNegocioException('La oferta cambió desde que la abrió: revísela nuevamente.');
            }
            $this->db->query("UPDATE dbo.ofertas SET estado = 'Rechazada', fecha_respuesta = ?, version_fila = version_fila + 1 WHERE id = ?", [$this->ahoraUtc(), $id]);
            (new AuditoriaService($this->db))->registrar('campanas.rechazar-oferta', 'ofertas', $id, 'Exitoso', null, ['campana' => (int) $o['campana_numero']]);
        });
    }

    /**
     * CU-52 A2: la oferta queda vencida y se audita el intento; devuelve el mensaje para informarlo después de confirmar
     * la transacción.
     *
     * @return array{vencida: string}
     */
    private function vencerOferta(int $id, string $mensaje): array
    {
        $this->db->query("UPDATE dbo.ofertas SET estado = 'Vencida', version_fila = version_fila + 1 WHERE id = ? AND estado = 'Vigente'", [$id]);
        (new AuditoriaService($this->db))->registrar('campanas.aceptar-oferta', 'ofertas', $id, 'Rechazado', $mensaje);

        return ['vencida' => $mensaje];
    }

    /** @return never */
    private function denegar(int $id, bool $inexistente): void
    {
        (new AuditoriaService($this->db))->registrar('portal.acceso-denegado', 'ofertas', $id, 'Rechazado', $inexistente ? 'Objeto inexistente' : 'Objeto de otro cliente');
        throw new AccesoAjenoException();
    }
}
