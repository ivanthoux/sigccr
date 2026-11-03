<?php

declare(strict_types=1);

namespace App\Services\Lineas;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Lineas\ValidadorLinea;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\I18n\Time;

/**
 * SS-03 Gestión de líneas de crédito: CU-16 Registrar, CU-17 Modificar, CU-18 Suspender, CU-19 Reactivar, CU-20
 * Localizar y CU-73 Consultar. Cada modificación de condiciones genera una versión nueva e inmutable (RN-15); la
 * versión vigente es la de mayor vigente_desde alcanzado. El importe mínimo y máximo prestable integra cada versión
 * (RN-14, RN-76, D-09).
 */
class LineaService extends BaseService
{
    /** Columnas de condiciones de versiones_condiciones. */
    private const CONDICIONES = [
        'tna', 'gasto_otorgamiento_pct', 'gasto_administrativo_cuota', 'monto_minimo', 'monto_maximo', 'cuotas_minimas',
        'cuotas_maximas', 'edad_minima', 'edad_maxima', 'antiguedad_minima_meses', 'admite_persona_humana',
        'admite_persona_juridica', 'vigencia_inicio', 'vigencia_fin',
    ];

    /**
     * CU-20 pasos 2 a 4.
     *
     * @param array<string, string> $c texto, estado, categoria
     * @return list<array<string, mixed>>
     */
    public function buscar(array $c): array
    {
        $donde = [];
        $param = [$this->ahoraUtc()];
        foreach (preg_split('/\s+/u', Campos::texto($c['texto'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $donde[] = '(l.nombre LIKE ? OR l.codigo LIKE ?)';
            $param[] = '%' . $palabra . '%';
            $param[] = '%' . $palabra . '%';
        }
        if (($c['categoria'] ?? '') !== '') {
            $donde[] = 'EXISTS (SELECT 1 FROM dbo.condiciones_categorias cc WHERE cc.version_condiciones_id = v.id AND cc.categoria_ingresos = ?)';
            $param[] = $c['categoria'];
        }
        $sql = 'SELECT TOP (500) l.id, l.codigo, l.nombre, l.estado, v.numero AS version, v.vigencia_inicio, v.vigencia_fin,
                       v.monto_minimo, v.monto_maximo, v.tna, v.cuotas_minimas, v.cuotas_maximas
                FROM dbo.lineas_credito l
                OUTER APPLY (SELECT TOP (1) x.numero, x.vigencia_inicio, x.vigencia_fin, x.monto_minimo, x.monto_maximo, x.tna, x.cuotas_minimas, x.cuotas_maximas, x.id FROM dbo.versiones_condiciones x
                             WHERE x.linea_credito_id = l.id AND x.vigente_desde <= ? ORDER BY x.vigente_desde DESC, x.numero DESC) v'
            . ($donde === [] ? '' : ' WHERE ' . implode(' AND ', $donde)) . ' ORDER BY l.nombre';
        $filas = $this->db->query($sql, $param)->getResultArray();
        $hoy = $this->hoyNegocio();
        foreach ($filas as &$f) {
            $f['estado'] = $f['vigencia_fin'] === null ? $f['estado'] : ValidadorLinea::estadoEfectivo($f['estado'], (string) $f['vigencia_fin'], $hoy);
        }
        unset($f);
        $estado = $c['estado'] ?? '';

        return in_array($estado, ['Activa', 'Suspendida', 'Vencida'], true)
            ? array_values(array_filter($filas, static fn ($f) => $f['estado'] === $estado)) : $filas;
    }

    /**
     * CU-73: línea con su versión vigente, documentación por categoría, historial de versiones y operaciones asociadas.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $linea = $this->db->table('lineas_credito')->select('id, codigo, nombre, descripcion, estado, version_fila, creado_en, actualizado_en')
            ->where('id', $id)->get()->getRowArray();
        if ($linea === null) {
            throw new ReglaNegocioException('La línea de crédito no existe.');
        }
        $versiones = $this->db->table('versiones_condiciones')->where('linea_credito_id', $id)
            ->select('id, numero, vigente_desde, ' . implode(', ', self::CONDICIONES))
            ->orderBy('numero', 'DESC')->get()->getResultArray();
        $ahora = $this->ahoraUtc();
        $vigente = null;
        $pendiente = null;
        foreach ($versiones as $v) {
            if ($this->instante($v['vigente_desde']) <= $this->instante($ahora)) {
                if ($vigente === null || $this->instante($v['vigente_desde']) > $this->instante($vigente['vigente_desde'])) {
                    $vigente = $v;
                }
            } elseif ($pendiente === null || $v['numero'] > $pendiente['numero']) {
                $pendiente = $v;
            }
        }
        if ($vigente === null) {
            throw new ReglaNegocioException('La línea no tiene una versión de condiciones vigente.');
        }
        $linea['estado'] = ValidadorLinea::estadoEfectivo($linea['estado'], (string) $vigente['vigencia_fin'], $this->hoyNegocio());
        $linea['vigente'] = $vigente + $this->detalleVersion((int) $vigente['id']);
        $linea['pendiente'] = $pendiente;
        $linea['versiones'] = $versiones;
        $linea['operaciones'] = $this->operaciones(array_map(static fn ($v) => (int) $v['id'], $versiones));
        $linea['cambios_estado'] = $this->db->table('registros_auditoria')
            ->select('fecha_hora, responsable, accion, motivo')
            ->where('objeto_tipo', 'lineas_credito')->where('objeto_id', (string) $id)
            ->whereIn('accion', ['lineas.suspender', 'lineas.reactivar'])
            ->orderBy('fecha_hora', 'DESC')->get(20)->getResultArray();

        return $linea;
    }

    /**
     * CU-16 pasos 3 a 6.
     *
     * @param array<string, mixed> $datos
     */
    public function registrar(array $datos): int
    {
        $d = ValidadorLinea::normalizar($datos);
        $errores = ValidadorLinea::validar($d, $this->codigosDocumentacion(), $this->hoyNegocio());
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-14');
        }

        return $this->transaccion(function () use ($d): int {
            // CU-16 A1: identificación o nombre ya existentes
            $repetida = $this->db->query('SELECT TOP (1) codigo, nombre FROM dbo.lineas_credito WITH (UPDLOCK, HOLDLOCK) WHERE codigo = ? OR nombre = ?', [$d['codigo'], $d['nombre']])->getRowArray();
            if ($repetida !== null) {
                $campo = $repetida['codigo'] === $d['codigo'] ? 'codigo' : 'nombre';
                throw new ValidacionException([$campo => $campo === 'codigo' ? 'Ya existe una línea con esa identificación.' : 'Ya existe una línea con ese nombre.']);
            }
            $ahora = $this->ahoraUtc();
            $this->db->table('lineas_credito')->insert([
                'codigo' => $d['codigo'], 'nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] !== '' ? $d['descripcion'] : null,
                'estado' => 'Activa', 'creado_en' => $ahora,
            ]);
            $id = (int) $this->db->insertID();
            $versionId = $this->insertarVersion($id, 1, $ahora, $d);
            (new AuditoriaService($this->db))->registrar('lineas.registrar', 'lineas_credito', $id, 'Exitoso', null, [
                'version' => 1, 'version_condiciones_id' => $versionId, 'monto_minimo' => $d['monto_minimo'], 'monto_maximo' => $d['monto_maximo'],
                'cuotas' => "{$d['cuotas_minimas']}-{$d['cuotas_maximas']}", 'tna' => $d['tna'],
            ]);

            return $id;
        });
    }

    /**
     * CU-17 pasos 5 a 9: nueva versión con fecha de entrada en vigencia (no retroactiva) y motivo.
     *
     * @param array<string, mixed> $datos
     * @return array{version: int, vigente_desde: string, operaciones_anteriores: int}
     */
    public function modificar(int $id, int $versionLeida, array $datos, string $fechaVigencia, string $motivo, bool $confirmado = false): array
    {
        $d = ValidadorLinea::normalizar($datos);
        $hoy = $this->hoyNegocio();
        $errores = ValidadorLinea::validar($d, $this->codigosDocumentacion(), $hoy, false);
        $fecha = Campos::fecha($fechaVigencia);
        if ($fecha === null) {
            $errores['fecha_vigencia'] = 'Ingrese la fecha de entrada en vigencia de la nueva versión (dd/mm/aaaa).';
        } elseif ($fecha < $hoy) {
            $errores['fecha_vigencia'] = 'La nueva versión no puede regir desde una fecha anterior a hoy: no se admite retroactividad.';
        }
        $motivo = Campos::texto($motivo);
        if (($m = Campos::motivo($motivo)) !== null) {
            $errores['motivo'] = $m;
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-14');
        }

        return $this->transaccion(function () use ($id, $versionLeida, $d, $fecha, $hoy, $motivo, $confirmado): array {
            $linea = $this->bloquear('lineas_credito', [$id])[0] ?? null;
            if ($linea === null) {
                throw new ReglaNegocioException('La línea de crédito no existe.');
            }
            $repetida = $this->db->query('SELECT TOP (1) id FROM dbo.lineas_credito WHERE nombre = ? AND id <> ?', [$d['nombre'], $id])->getRowArray();
            if ($repetida !== null) {
                throw new ValidacionException(['nombre' => 'Ya existe otra línea con ese nombre.']);
            }
            $anterior = $this->db->query('SELECT TOP (1) id, numero FROM dbo.versiones_condiciones WHERE linea_credito_id = ? AND vigente_desde <= ? ORDER BY vigente_desde DESC, numero DESC', [$id, $this->ahoraUtc()])->getRowArray();
            // D-24: con solicitudes en trámite sobre la versión vigente, el cambio se confirma de forma explícita
            $enCurso = $anterior === null ? ['solicitudes_en_curso' => 0, 'simulaciones_vigentes' => 0] : $this->operaciones([(int) $anterior['id']]);
            if (!$confirmado && $enCurso['solicitudes_en_curso'] > 0) {
                throw new ValidacionException(['confirmar_cambio' => "Hay {$enCurso['solicitudes_en_curso']} solicitud(es) en trámite y {$enCurso['simulaciones_vigentes']} simulación(es) vigente(s) con la versión {$anterior['numero']} de condiciones. Conservan esa versión (RN-15): confirme el cambio para registrar la nueva."], 'RN-15');
            }
            $numero = (int) $this->db->query('SELECT ISNULL(MAX(numero), 0) + 1 AS n FROM dbo.versiones_condiciones WHERE linea_credito_id = ?', [$id])->getRow()->n;
            $vigenteDesde = $fecha === $hoy ? $this->ahoraUtc()
                : Time::parse($fecha . ' 00:00:00', config('Sigccr')->zonaNegocio)->setTimezone('UTC')->format('Y-m-d H:i:s.vP');
            $versionId = $this->insertarVersion($id, $numero, $vigenteDesde, $d);
            $this->actualizarConVersion('lineas_credito', $id, $versionLeida, [
                'nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] !== '' ? $d['descripcion'] : null, 'actualizado_en' => $this->ahoraUtc(),
            ]);
            $operaciones = $anterior === null ? 0 : array_sum($this->operaciones([(int) $anterior['id']]));
            (new AuditoriaService($this->db))->registrar('lineas.modificar', 'lineas_credito', $id, 'Exitoso', $motivo, [
                'version' => $numero, 'version_condiciones_id' => $versionId, 'vigente_desde' => $vigenteDesde,
                'monto_minimo' => $d['monto_minimo'], 'monto_maximo' => $d['monto_maximo'],
            ]);

            return ['version' => $numero, 'vigente_desde' => $vigenteDesde, 'operaciones_anteriores' => $operaciones];
        });
    }

    /** CU-18 pasos 4 a 7 (RN-16, RN-60). @return int solicitudes y ofertas que continúan con su versión */
    public function suspender(int $id, int $versionLeida, string $motivo): int
    {
        return $this->cambiarEstado($id, $versionLeida, $motivo, 'Activa', 'Suspendida', 'lineas.suspender');
    }

    /** CU-19 pasos 4 a 8 (RN-60). */
    public function reactivar(int $id, int $versionLeida, string $motivo): void
    {
        $this->cambiarEstado($id, $versionLeida, $motivo, 'Suspendida', 'Activa', 'lineas.reactivar');
    }

    /** CU-73: consulta registrada en auditoría. */
    public function registrarConsulta(int $id): void
    {
        (new AuditoriaService($this->db))->registrar('lineas.consultar', 'lineas_credito', $id);
    }

    /** @return list<array{codigo: string, nombre: string, aplica_a: string}> */
    public function tiposDocumentacion(): array
    {
        return $this->db->table('tipos_documentacion')->select('codigo, nombre, aplica_a')->where('activo', 1)->orderBy('id')->get()->getResultArray();
    }

    private function cambiarEstado(int $id, int $versionLeida, string $motivo, string $desde, string $hacia, string $accion): int
    {
        $motivo = Campos::texto($motivo);
        if (($m = Campos::motivo($motivo)) !== null) {
            throw new ValidacionException(['motivo' => $m]);
        }

        return $this->transaccion(function () use ($id, $versionLeida, $motivo, $desde, $hacia, $accion): int {
            $linea = $this->bloquear('lineas_credito', [$id])[0] ?? null;
            if ($linea === null) {
                throw new ReglaNegocioException('La línea de crédito no existe.');
            }
            $vigente = $this->db->query('SELECT TOP (1) id, vigencia_fin FROM dbo.versiones_condiciones WHERE linea_credito_id = ? AND vigente_desde <= ? ORDER BY vigente_desde DESC, numero DESC', [$id, $this->ahoraUtc()])->getRowArray();
            $estado = ValidadorLinea::estadoEfectivo($linea['estado'], (string) ($vigente['vigencia_fin'] ?? '1900-01-01'), $this->hoyNegocio());
            if ($estado === 'Vencida') {
                if ($linea['estado'] !== 'Vencida') {
                    $this->db->query("UPDATE dbo.lineas_credito SET estado = 'Vencida', version_fila = version_fila + 1 WHERE id = ?", [$id]);
                }
                throw new ReglaNegocioException('La línea superó su período de vigencia y no admite nuevas operaciones: la operación no corresponde.', 'RN-13');
            }
            if ($estado !== $desde) {
                throw new ReglaNegocioException($hacia === 'Suspendida' ? 'La línea ya está suspendida.' : 'La línea no está suspendida.', 'RN-60');
            }
            $this->actualizarConVersion('lineas_credito', $id, $versionLeida, ['estado' => $hacia, 'actualizado_en' => $this->ahoraUtc()]);
            $continuan = 0;
            if ($hacia === 'Suspendida') {
                $ids = array_map('intval', array_column($this->db->table('versiones_condiciones')->select('id')->where('linea_credito_id', $id)->get()->getResultArray(), 'id'));
                $ops = $this->operaciones($ids);
                $continuan = $ops['solicitudes_en_curso'] + $ops['ofertas_vigentes'];
            }
            (new AuditoriaService($this->db))->registrar($accion, 'lineas_credito', $id, 'Exitoso', $motivo, ['estado_anterior' => $desde, 'estado_nuevo' => $hacia]);

            return $continuan;
        });
    }

    /** @param array<string, mixed> $d */
    private function insertarVersion(int $lineaId, int $numero, string $vigenteDesde, array $d): int
    {
        $this->db->table('versiones_condiciones')->insert([
            'linea_credito_id' => $lineaId, 'numero' => $numero, 'vigente_desde' => $vigenteDesde,
            'tna' => $d['tna'], 'gasto_otorgamiento_pct' => $d['gasto_otorgamiento_pct'], 'gasto_administrativo_cuota' => $d['gasto_administrativo_cuota'],
            'monto_minimo' => $d['monto_minimo'], 'monto_maximo' => $d['monto_maximo'],
            'cuotas_minimas' => $d['cuotas_minimas'], 'cuotas_maximas' => $d['cuotas_maximas'],
            'edad_minima' => $d['edad_minima'], 'edad_maxima' => $d['edad_maxima'], 'antiguedad_minima_meses' => $d['antiguedad_minima_meses'],
            'admite_persona_humana' => $d['admite_persona_humana'] ? 1 : 0, 'admite_persona_juridica' => $d['admite_persona_juridica'] ? 1 : 0,
            'vigencia_inicio' => $d['vigencia_inicio'], 'vigencia_fin' => $d['vigencia_fin'],
        ]);
        $versionId = (int) $this->db->insertID();
        $tipos = array_column($this->db->table('tipos_documentacion')->select('id, codigo')->where('activo', 1)->get()->getResultArray(), 'id', 'codigo');
        foreach ($d['categorias'] as $cat) {
            $this->db->table('condiciones_categorias')->insert(['version_condiciones_id' => $versionId, 'categoria_ingresos' => $cat]);
            foreach ($d['requisitos'][$cat] ?? [] as $codigo) {
                if (isset($tipos[$codigo])) {
                    $this->db->table('requisitos_documentales')->insert([
                        'version_condiciones_id' => $versionId, 'tipo_documentacion_id' => (int) $tipos[$codigo], 'categoria_ingresos' => $cat, 'obligatorio' => 1,
                    ]);
                }
            }
        }

        return $versionId;
    }

    /** @return array{categorias: list<string>, requisitos: array<string, list<array{codigo: string, nombre: string}>>} */
    private function detalleVersion(int $versionId): array
    {
        $categorias = array_column($this->db->table('condiciones_categorias')->select('categoria_ingresos')->where('version_condiciones_id', $versionId)->get()->getResultArray(), 'categoria_ingresos');
        $requisitos = [];
        $filas = $this->db->table('requisitos_documentales r')->select('r.categoria_ingresos, t.codigo, t.nombre')
            ->join('tipos_documentacion t', 't.id = r.tipo_documentacion_id')->where('r.version_condiciones_id', $versionId)
            ->orderBy('t.id')->get()->getResultArray();
        foreach ($filas as $f) {
            $requisitos[(string) $f['categoria_ingresos']][] = ['codigo' => $f['codigo'], 'nombre' => $f['nombre']];
        }

        return ['categorias' => $categorias, 'requisitos' => $requisitos];
    }

    /**
     * Operaciones que usan las versiones indicadas (CU-73 paso 5, CU-17 paso 9, CU-18 paso 7).
     *
     * @param list<int> $versiones
     * @return array{simulaciones_vigentes: int, solicitudes_en_curso: int, ofertas_vigentes: int, creditos: int}
     */
    private function operaciones(array $versiones): array
    {
        $r = ['simulaciones_vigentes' => 0, 'solicitudes_en_curso' => 0, 'ofertas_vigentes' => 0, 'creditos' => 0];
        if ($versiones === []) {
            return $r;
        }
        $r['simulaciones_vigentes'] = $this->db->table('simulaciones')->whereIn('version_condiciones_id', $versiones)->where('estado', 'Vigente')->countAllResults();
        $r['solicitudes_en_curso'] = $this->db->table('solicitudes_credito')->whereIn('version_condiciones_id', $versiones)
            ->whereNotIn('estado', ['Liquidada', 'Rechazada', 'Cancelada', 'Vencida'])->countAllResults();
        $r['ofertas_vigentes'] = $this->db->table('ofertas o')->join('campanas c', 'c.id = o.campana_id')
            ->whereIn('c.version_condiciones_id', $versiones)->where('o.estado', 'Vigente')->countAllResults();
        $r['creditos'] = $this->db->table('creditos')->whereIn('version_condiciones_id', $versiones)->whereIn('estado', ['Activo', 'EnMora'])->countAllResults();

        return $r;
    }

    /** @return list<string> */
    private function codigosDocumentacion(): array
    {
        return array_column($this->tiposDocumentacion(), 'codigo');
    }

    private function instante(string $valor): int
    {
        return (new \DateTimeImmutable($valor))->getTimestamp();
    }

    /**
     * Categorías de ingresos con su nombre, para formularios y filtros.
     *
     * @return array<string, string>
     */
    public static function categorias(): array
    {
        return RequisitosDocumentales::CATEGORIAS['PersonaHumana'] + RequisitosDocumentales::CATEGORIAS['PersonaJuridica'];
    }
}
