<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\ArchivoLegajo;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\GastoAdministrativo;
use App\Libraries\Solicitudes\Contrato;
use App\Libraries\Solicitudes\ModeloContrato;
use App\Services\BaseService;
use App\Services\Clientes\LegajoService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use Throwable;

/**
 * Contrato de préstamo (SS-04): generación para cada aprobación vigente, CU-103 Registrar Contrato Firmado y el
 * modelo de cláusulas configurable (CU-106, D-56). RN-25, RN-78, RN-79, D-31.
 */
class ContratoService extends BaseService
{
    /**
     * RN-78: contrato de la aprobación vigente de la solicitud. Se genera al aprobarse; si la aprobación es anterior a
     * D-31, se genera al consultarlo. Debe llamarse con la aprobación ya registrada.
     *
     * @return array<string, mixed> fila de contratos_prestamo
     */
    public function generar(int $solicitudId): array
    {
        $r = $this->db->query("SELECT id FROM dbo.resoluciones WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$solicitudId])->getRowArray();
        if ($r === null) {
            throw new ReglaNegocioException('La solicitud no tiene una aprobación vigente: no hay contrato que generar.');
        }
        $c = $this->db->table('contratos_prestamo')->where('resolucion_id', (int) $r['id'])->get()->getRowArray();
        if ($c === null) {
            // D-56: el contrato conserva el modelo vigente al generarse (RN-15)
            $this->db->table('contratos_prestamo')->insert([
                'solicitud_id' => $solicitudId, 'resolucion_id' => (int) $r['id'], 'fecha_generacion' => $this->ahoraUtc(), 'version_contrato_id' => $this->modeloVigente()['id'],
            ]);
            $c = $this->db->table('contratos_prestamo')->where('id', (int) $this->db->insertID())->get()->getRowArray();
        }

        return (array) $c;
    }

    /**
     * CU-103 paso 1: solicitudes aprobadas pendientes de liquidación con el estado de su contrato.
     *
     * @return list<array<string, mixed>>
     */
    public function pendientes(string $texto = ''): array
    {
        $filas = (new LiquidacionService($this->db))->pendientes($texto);
        foreach ($filas as &$f) {
            $f['constancia'] = $this->db->query(
                "SELECT k.fecha_recepcion FROM dbo.contratos_prestamo k JOIN dbo.resoluciones r ON r.id = k.resolucion_id AND r.vigente = 1 AND r.decision = 'Aprobada' "
                . 'WHERE k.solicitud_id = ? AND k.documento_legajo_id IS NOT NULL',
                [(int) $f['id']],
            )->getRowArray()['fecha_recepcion'] ?? null;
        }

        return $filas;
    }

    /**
     * CU-103 paso 2: datos del contrato (RN-78).
     *
     * @return array<string, mixed>
     */
    public function obtener(int $solicitudId): array
    {
        $s = (new LiquidacionService($this->db))->datos($solicitudId);
        if ($s['estado'] !== 'AprobadaPendienteLiquidacion' && $s['liquidacion'] === null) {
            throw new ReglaNegocioException('La solicitud no está aprobada pendiente de liquidación: no tiene un contrato para firmar.');
        }
        if ($s['importe_aprobado'] === null) {
            throw new ReglaNegocioException('La solicitud no tiene una aprobación vigente.');
        }
        $s['contrato'] = $s['liquidacion'] === null ? $this->transaccion(fn (): array => $this->generar($solicitudId))
            : $this->db->table('contratos_prestamo')->where('solicitud_id', $solicitudId)->orderBy('id', 'DESC')->get(1)->getRowArray();
        $s['cliente'] = $this->db->query(
            'SELECT c.domicilio, c.correo, c.codigo_postal, l.nombre AS localidad, p.nombre AS provincia FROM dbo.clientes c '
            . 'LEFT JOIN dbo.localidades l ON l.id = c.localidad_id LEFT JOIN dbo.provincias p ON p.id = l.provincia_id WHERE c.id = ?',
            [(int) $s['cliente_id']],
        )->getRowArray();
        $s['fecha_aprobacion'] = $this->db->table('resoluciones')->select('fecha')->where('id', (int) $s['resolucion_id'])->get()->getRow()->fecha ?? null;
        $morosidad = (new ParametroService($this->db))->vigente('Morosidad')['valores'];
        $s['punitorios'] = Contrato::punitorios($morosidad, Decimal::of((string) $s['tna']));
        $s['entidad'] = ['razon_social' => config('Sigccr')->entidadRazonSocial, 'cuit' => config('Sigccr')->entidadCuit, 'domicilio' => config('Sigccr')->entidadDomicilio];
        // D-56: las cláusulas del modelo con que se generó, con los datos de este préstamo
        $modelo = $this->modelo(isset($s['contrato']['version_contrato_id']) ? (int) $s['contrato']['version_contrato_id'] : null);
        $s['modelo'] = $modelo;
        $s['clausulas'] = ModeloContrato::componer($modelo['clausulas'], $this->valores($s));

        return $s;
    }

    /**
     * CU-106: modelo de contrato vigente. Sin versiones registradas rige el modelo inicial (id y número nulos).
     *
     * @return array{id: ?int, numero: ?int, clausulas: list<array{estilo: string, titulo: string, texto: string}>}
     */
    public function modeloVigente(): array
    {
        try {
            $v = (new ParametroService($this->db))->vigente('Contrato');
        } catch (ReglaNegocioException) {
            return ['id' => null, 'numero' => null, 'clausulas' => ModeloContrato::INICIAL];
        }

        return ['id' => $v['id'], 'numero' => $v['numero'], 'clausulas' => self::clausulasDe($v['valores'])];
    }

    /**
     * Modelo de una versión del grupo Contrato; null es el modelo inicial (contratos anteriores a D-56).
     *
     * @return array{id: ?int, numero: ?int, clausulas: list<array{estilo: string, titulo: string, texto: string}>}
     */
    public function modelo(?int $versionId): array
    {
        $fila = $versionId === null ? null : $this->db->table('versiones_parametros')->select('id, numero, valores')->where('id', $versionId)->where('tipo', 'Contrato')->get()->getRowArray();
        if ($fila === null) {
            return ['id' => null, 'numero' => null, 'clausulas' => ModeloContrato::INICIAL];
        }

        return ['id' => (int) $fila['id'], 'numero' => (int) $fila['numero'], 'clausulas' => self::clausulasDe((array) json_decode((string) $fila['valores'], true))];
    }

    /**
     * Las cláusulas se guardan numeradas desde 1 («clausulas.1.texto»), para que la auditoría compare cada una (D-48).
     *
     * @param list<array{estilo: string, titulo: string, texto: string}> $clausulas
     * @return array{clausulas: array<int, array{estilo: string, titulo: string, texto: string}>}
     */
    public static function valoresDe(array $clausulas): array
    {
        return ['clausulas' => $clausulas === [] ? [] : array_combine(range(1, count($clausulas)), $clausulas)];
    }

    /**
     * @param array<string, mixed> $valores
     * @return list<array{estilo: string, titulo: string, texto: string}>
     */
    private static function clausulasDe(array $valores): array
    {
        return array_values(array_map(static fn ($c): array => [
            'estilo' => (string) ($c['estilo'] ?? 'numerada'), 'titulo' => (string) ($c['titulo'] ?? ''), 'texto' => (string) ($c['texto'] ?? ''),
        ], (array) ($valores['clausulas'] ?? [])));
    }

    /**
     * Valores de las variables del modelo (ModeloContrato::VARIABLES) para un contrato, como texto.
     *
     * @param array<string, mixed> $s datos de obtener()
     * @return array<string, string>
     */
    public function valores(array $s): array
    {
        helper('sigccr');
        $c = $s['calculo'];
        $e = $s['entidad'];
        $juridica = $s['tipo_cliente'] === 'PersonaJuridica';
        $gasto = GastoAdministrativo::deVersion($s);
        $cl = $s['cliente'] ?? [];

        return [
            'entidad' => (string) $e['razon_social'],
            'entidad_datos' => ($e['cuit'] !== '' ? ', CUIT ' . formato_cuit($e['cuit']) : '') . ($e['domicilio'] !== '' ? ', con domicilio en ' . $e['domicilio'] : ''),
            'cliente' => (string) $s['denominacion'],
            'cliente_identificacion' => $juridica
                ? 'CUIT ' . formato_cuit($s['cuit_cuil']) . ', representada por ' . $s['representante_nombre'] . ' (DNI ' . $s['representante_documento'] . ')'
                : $s['tipo_documento'] . ' ' . $s['nro_documento'] . ', CUIL ' . formato_cuit($s['cuit_cuil']),
            'cliente_domicilio' => trim(($cl['domicilio'] ?? '') . (!empty($cl['localidad']) ? ', ' . $cl['localidad'] . ', ' . $cl['provincia'] : '')),
            'cliente_correo' => (string) ($cl['correo'] ?? ''),
            'numero_contrato' => (string) ($s['contrato']['id'] ?? ''),
            'numero_solicitud' => (string) $s['numero'],
            'linea' => (string) $s['linea'],
            'importe' => formato_importe((string) $s['importe_aprobado']),
            'gasto_otorgamiento' => formato_importe($c['gasto_otorgamiento']),
            'iva_otorgamiento' => formato_importe($c['iva_otorgamiento']),
            'neto' => formato_importe($c['neto']),
            'cftea' => formato_porcentaje($c['cftea']),
            'tna' => formato_porcentaje((string) $s['tna']),
            'tea' => formato_porcentaje($c['tea']),
            'tasa_mensual' => formato_porcentaje($c['tasa_mensual'], 4),
            'total_intereses' => formato_importe($c['total_intereses']),
            'total_a_pagar' => formato_importe($c['total_a_pagar']),
            'cuotas' => (string) (int) $s['cuotas_aprobadas'],
            'gasto_administrativo' => $gasto->tipo === 'Porcentual'
                ? 'un gasto administrativo del ' . formato_porcentaje((string) $s['gasto_administrativo_pct']) . ' del capital'
                : 'un gasto administrativo de ' . formato_importe((string) $s['gasto_administrativo_cuota']),
            'tolerancia' => (string) (int) $s['punitorios']['tolerancia'],
            'punitorio_diario' => formato_porcentaje($s['punitorios']['diaria'], 4),
            'punitorio_anual' => formato_porcentaje($s['punitorios']['anual']),
            'plazo_revocacion' => (string) Contrato::DIAS_REVOCACION,
        ];
    }

    /**
     * CU-106 pasos 4 a 6 (A1 a A3): nueva versión del modelo de contrato. Rige para los contratos que se generen desde
     * la fecha indicada; los ya generados conservan el suyo (RN-15).
     *
     * @param list<array{estilo: string, titulo: string, texto: string}> $clausulas
     * @return array{id: int, numero: int, vigente_desde: string}
     */
    public function registrarModelo(array $clausulas, string $fecha, string $motivo, int $usuarioId): array
    {
        $errores = ModeloContrato::validar($clausulas);
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-78');
        }

        // La primera versión se compara con el modelo inicial, que regía hasta entonces (D-48)
        return (new ParametroService($this->db))->registrarVersion('Contrato', self::valoresDe($clausulas), $fecha, $motivo, $usuarioId, self::valoresDe(ModeloContrato::INICIAL));
    }

    /**
     * CU-103 pasos 4 a 6 (A1 a A3): copia escaneada de la constancia firmada y fecha de recepción del contrato.
     *
     * @param array{nombre: string, temporal: string, tamano: int}|null $archivo
     */
    public function registrarConstancia(int $solicitudId, ?array $archivo, string $fechaTexto, int $usuarioId): void
    {
        $errores = [];
        $doc = null;
        if ($archivo === null) {
            $errores['constancia'] = 'Adjunte la copia escaneada de la constancia firmada (PDF, JPG o PNG).';
        } else {
            $mime = is_file($archivo['temporal']) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($archivo['temporal']) : '';
            $error = ArchivoLegajo::validar(ArchivoLegajo::nombreSeguro($archivo['nombre']), $archivo['tamano'], $mime);
            if ($error !== null) {
                $errores['constancia'] = $error;
            } else {
                $doc = $archivo + ['mime' => $mime];
            }
        }
        $ruta = null;
        try {
            $this->transaccion(function () use ($solicitudId, $doc, $fechaTexto, $usuarioId, &$errores, &$ruta): void {
                $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
                if ($s === null || $s['estado'] !== 'AprobadaPendienteLiquidacion') {
                    throw new ReglaNegocioException('La solicitud no está aprobada pendiente de liquidación: no se puede registrar la constancia (A2).');
                }
                $contrato = $this->generar($solicitudId);
                $aprobacion = (string) $this->db->table('resoluciones')->select('fecha')->where('id', (int) $contrato['resolucion_id'])->get()->getRow()->fecha;
                $fechaAprobacion = (new \DateTimeImmutable($aprobacion))->setTimezone(new \DateTimeZone(config('Sigccr')->zonaNegocio))->format('Y-m-d');
                $f = Contrato::validarRecepcion($fechaTexto, $fechaAprobacion, $this->hoyNegocio());
                if ($f['error'] !== null) {
                    $errores['fecha_recepcion'] = $f['error'];
                }
                if ($errores !== [] || $doc === null) {
                    throw new ValidacionException($errores, 'RN-78');
                }
                $tipo = $this->db->table('tipos_documentacion')->select('id')->where('codigo', Contrato::TIPO_DOCUMENTO)->get()->getRow();
                if ($tipo === null) {
                    throw new ReglaNegocioException('Falta el tipo de documento de la constancia firmada: ejecute las migraciones.');
                }
                // A3: una nueva constancia reemplaza a la anterior, que se conserva (RN-44)
                $g = (new LegajoService($this->db))->guardar((int) $s['cliente_id'], $doc + ['tipo_id' => (int) $tipo->id, 'reemplaza' => $contrato['documento_legajo_id']], $usuarioId);
                $ruta = $g['ruta'];
                $this->db->table('contratos_prestamo')->where('id', (int) $contrato['id'])->update([
                    'documento_legajo_id' => $g['id'], 'fecha_recepcion' => $f['fecha'], 'fecha_registro' => $this->ahoraUtc(), 'usuario_id' => $usuarioId,
                ]);
                (new AuditoriaService($this->db))->registrar('contratos.registrar_firmado', 'solicitudes_credito', $solicitudId, 'Exitoso', null, [
                    'contrato' => (int) $contrato['id'], 'documento' => $g['id'], 'fecha_recepcion' => $f['fecha'], 'reemplaza' => $contrato['documento_legajo_id'],
                ]);
            });
        } catch (Throwable $e) {
            if ($ruta !== null) {
                LegajoService::descartar([$ruta]);
            }
            throw $e;
        }
    }

    /** RN-25: la aprobación vigente tiene su constancia firmada en el legajo. */
    public function constanciaRegistrada(int $solicitudId): bool
    {
        return $this->db->query(
            "SELECT 1 AS si FROM dbo.contratos_prestamo k JOIN dbo.resoluciones r ON r.id = k.resolucion_id AND r.vigente = 1 AND r.decision = 'Aprobada' "
            . 'WHERE k.solicitud_id = ? AND k.documento_legajo_id IS NOT NULL',
            [$solicitudId],
        )->getRowArray() !== null;
    }
}
