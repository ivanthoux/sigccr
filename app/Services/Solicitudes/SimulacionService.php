<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Finanzas\CapacidadPago;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Lineas\Elegibilidad;
use App\Libraries\Solicitudes\Simulador;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;

/**
 * CU-21 Simular Crédito (RN-13, RN-14, RN-16, RN-17, RN-48, RN-50 a RN-52, RN-68, RN-75, RN-76).
 *
 * La simulación guarda la versión de condiciones, el importe, las cuotas, la primera cuota, la TEA, el CFTEA y su
 * vencimiento; el plan detallado se recalcula al consultarla con la misma versión, la alícuota de IVA vigente al
 * generarla y su fecha de generación, por lo que siempre coincide con lo presentado.
 */
class SimulacionService extends BaseService
{
    /**
     * Cliente para simular o solicitar (CU-21 paso 2, CU-22 paso 2).
     *
     * @return array<string, mixed>
     */
    public function cliente(int $id): array
    {
        $c = $this->db->query(
            'SELECT c.id, c.denominacion, c.tipo_cliente, c.categoria_ingresos, c.estado, c.cuit_cuil, c.ingresos_declarados, c.fecha_alta, '
            . 'ph.fecha_nacimiento, ph.tipo_documento, ph.nro_documento, ph.ocupacion, ph.empleador, pj.fecha_constitucion, pj.actividad_principal '
            . 'FROM dbo.clientes c LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id WHERE c.id = ?',
            [$id],
        )->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('El cliente no existe.');
        }

        return $c;
    }

    /**
     * Datos del cliente que acompañan la simulación y la comparación (D-23): edad o antigüedad de la empresa,
     * situación laboral, ingresos, obligaciones vigentes y comprometidas, y capacidad de pago con los parámetros de
     * estructuración vigentes. Es informativo: no consulta la Central de Deudores (RN-17).
     *
     * @param array<string, mixed> $c fila de cliente()
     * @return array{edad: ?int, antiguedad_empresa: ?int, ingresos: string, obligaciones: string, capacidad: string, afectacion: string, solicitudes_en_tramite: int}
     */
    public function perfil(array $c): array
    {
        $hoy = $this->hoyNegocio();
        $internos = (new EvaluacionService($this->db))->antecedentesInternos($c, 0);
        $p = (new ParametroService($this->db))->vigente('Estructuracion')['valores'];
        $capacidad = CapacidadPago::capacidad(Decimal::of($internos['ingresos']), Decimal::of((string) $p['porcentaje_afectacion']), Decimal::of($internos['obligaciones']));

        return [
            'edad' => $c['fecha_nacimiento'] === null ? null : intdiv(Elegibilidad::mesesCumplidos(substr((string) $c['fecha_nacimiento'], 0, 10), $hoy), 12),
            'antiguedad_empresa' => ($c['fecha_constitucion'] ?? null) === null ? null : Elegibilidad::mesesCumplidos(substr((string) $c['fecha_constitucion'], 0, 10), $hoy),
            'ingresos' => $internos['ingresos'],
            'obligaciones' => $internos['obligaciones'],
            'capacidad' => $capacidad->toFixed(2),
            'afectacion' => (string) $p['porcentaje_afectacion'],
            'solicitudes_en_tramite' => $internos['solicitudes_en_tramite'],
        ];
    }

    /**
     * Paso 3: líneas con su versión vigente y el motivo por el que no admiten al cliente (null si lo admiten).
     *
     * @return list<array<string, mixed>>
     */
    public function lineasParaCliente(int $clienteId): array
    {
        $cliente = $this->cliente($clienteId);
        $hoy = $this->hoyNegocio();
        $lineas = [];
        foreach ($this->versionesVigentes() as $v) {
            $v['motivo_no_admitida'] = Elegibilidad::motivoNoAdmitida($v, $cliente, $hoy);
            $lineas[] = $v;
        }
        usort($lineas, static fn ($a, $b) => [$a['motivo_no_admitida'] !== null, $a['nombre']] <=> [$b['motivo_no_admitida'] !== null, $b['nombre']]);

        return $lineas;
    }

    /**
     * Pasos 6 a 10 y A2, A3, A5: valida, calcula y registra la simulación.
     *
     * @return int identificador de la simulación
     */
    public function simular(int $clienteId, int $lineaId, string $importe, string $cuotas, int $usuarioId): int
    {
        return $this->transaccion(function () use ($clienteId, $lineaId, $importe, $cuotas, $usuarioId): int {
            // El bloqueo de la línea serializa la simulación con una suspensión concurrente (A3, RN-16)
            if ($this->bloquear('lineas_credito', [$lineaId]) === []) {
                throw new ReglaNegocioException('La línea de crédito no existe.');
            }
            $version = $this->versionesVigentes($lineaId)[0] ?? null;
            if ($version === null) {
                throw new ReglaNegocioException('La línea no tiene una versión de condiciones vigente.');
            }
            $motivo = Elegibilidad::motivoNoAdmitida($version, $this->cliente($clienteId), $this->hoyNegocio());
            if ($motivo !== null) {
                throw new ReglaNegocioException($motivo . ' Seleccione otra línea.', 'RN-13');
            }
            $v = Simulador::validar($version, $importe, $cuotas);
            if ($v['errores'] !== []) {
                throw new ValidacionException($v['errores'], 'RN-76');
            }
            $iva = (string) (new ParametroService($this->db))->vigente('Estructuracion')['valores']['alicuota_iva'];
            $r = Simulador::calcular($version, (string) $v['importe'], (int) $v['cuotas'], $iva, new DateTimeImmutable($this->hoyNegocio()));
            $ahora = Time::now('UTC');
            $this->db->table('simulaciones')->insert([
                'cliente_id' => $clienteId,
                'version_condiciones_id' => (int) $version['version_id'],
                'fecha_generacion' => $ahora->format('Y-m-d H:i:s.vP'),
                'importe' => $v['importe'],
                'cantidad_cuotas' => $v['cuotas'],
                'valor_cuota' => $r['primera_cuota']->toFixed(2),
                'tea' => $r['tea']->toFixed(12),
                'cftea' => $r['cftea']->toFixed(12),
                'vence' => $ahora->addDays(Simulador::VIGENCIA_DIAS)->format('Y-m-d H:i:s.vP'),
                'estado' => 'Vigente',
            ]);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('solicitudes.simular', 'simulaciones', $id, 'Exitoso', null, [
                'cliente' => $clienteId, 'linea' => $lineaId, 'version_condiciones_id' => (int) $version['version_id'],
                'importe' => $v['importe'], 'cuotas' => $v['cuotas'], 'primera_cuota' => $r['primera_cuota']->toFixed(2),
            ]);

            return $id;
        });
    }

    /**
     * Simulación con su plan recalculado (CU-21 paso 10, CU-22 paso 7).
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $s = $this->db->query(
            'SELECT s.id, s.cliente_id, s.version_condiciones_id, s.fecha_generacion, s.importe, s.cantidad_cuotas, s.valor_cuota, s.tea, s.cftea, '
            . 's.vence, s.estado, s.simulacion_comparativa_id, CASE WHEN s.estado = \'Vigente\' AND s.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente, '
            . 'c.denominacion, l.id AS linea_id, l.nombre AS linea, v.numero AS version, v.tna, v.gasto_otorgamiento_pct, v.gasto_administrativo_cuota, v.tipo_gasto_administrativo, v.gasto_administrativo_pct, '
            . 'v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas '
            . 'FROM dbo.simulaciones s JOIN dbo.clientes c ON c.id = s.cliente_id JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id '
            . 'JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id WHERE s.id = ?',
            [$id],
        )->getRowArray();
        if ($s === null) {
            throw new ReglaNegocioException('La simulación no existe.');
        }
        $s['calculo'] = $this->calculo($s, (string) $s['fecha_generacion']);

        return $s;
    }

    /**
     * Plan de una simulación o de una solicitud con la alícuota vigente en un instante.
     *
     * @param array<string, mixed> $s importe, cantidad_cuotas y condiciones de la versión
     * @return array<string, mixed>
     */
    public function calculo(array $s, string $instanteUtc): array
    {
        $iva = (string) (new ParametroService($this->db))->vigente('Estructuracion', $instanteUtc)['valores']['alicuota_iva'];
        $fecha = (new DateTimeImmutable($instanteUtc))->setTimezone(new \DateTimeZone(config('Sigccr')->zonaNegocio));

        return Simulador::calcular($s, Decimal::of((string) $s['importe'])->toFixed(2), (int) $s['cantidad_cuotas'], $iva, new DateTimeImmutable($fecha->format('Y-m-d'))) + ['alicuota_iva' => $iva];
    }

    /**
     * CU-22 paso 3: simulaciones vigentes del cliente, individuales o de una comparación.
     *
     * @return list<array<string, mixed>>
     */
    public function vigentesDelCliente(int $clienteId): array
    {
        return $this->db->query(
            'SELECT s.id, s.fecha_generacion, s.importe, s.cantidad_cuotas, s.valor_cuota, s.cftea, s.vence, s.simulacion_comparativa_id, '
            . 'l.id AS linea_id, l.nombre AS linea, v.numero AS version '
            . 'FROM dbo.simulaciones s JOIN dbo.versiones_condiciones v ON v.id = s.version_condiciones_id JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id '
            . "WHERE s.cliente_id = ? AND s.estado = 'Vigente' AND s.vence > SYSDATETIMEOFFSET() ORDER BY s.fecha_generacion DESC",
            [$clienteId],
        )->getResultArray();
    }

    /**
     * Versiones vigentes de las líneas (o de una), con sus categorías admitidas.
     *
     * @return list<array<string, mixed>>
     */
    private function versionesVigentes(?int $lineaId = null): array
    {
        $filas = $this->db->query(
            'SELECT l.id, l.codigo, l.nombre, l.estado, v.id AS version_id, v.numero AS version, v.tna, v.gasto_otorgamiento_pct, '
            . 'v.gasto_administrativo_cuota, v.tipo_gasto_administrativo, v.gasto_administrativo_pct, v.monto_minimo, v.monto_maximo, v.cuotas_minimas, v.cuotas_maximas, v.edad_minima, v.edad_maxima, '
            . 'v.antiguedad_minima_meses, v.admite_persona_humana, v.admite_persona_juridica, v.vigencia_inicio, v.vigencia_fin '
            . 'FROM dbo.lineas_credito l CROSS APPLY (SELECT TOP (1) x.id, x.numero, x.tna, x.gasto_otorgamiento_pct, x.gasto_administrativo_cuota, x.tipo_gasto_administrativo, x.gasto_administrativo_pct, x.monto_minimo, '
            . 'x.monto_maximo, x.cuotas_minimas, x.cuotas_maximas, x.edad_minima, x.edad_maxima, x.antiguedad_minima_meses, x.admite_persona_humana, '
            . 'x.admite_persona_juridica, x.vigencia_inicio, x.vigencia_fin FROM dbo.versiones_condiciones x WHERE x.linea_credito_id = l.id '
            . 'AND x.vigente_desde <= ? ORDER BY x.vigente_desde DESC, x.numero DESC) v' . ($lineaId === null ? '' : ' WHERE l.id = ?') . ' ORDER BY l.nombre',
            $lineaId === null ? [$this->ahoraUtc()] : [$this->ahoraUtc(), $lineaId],
        )->getResultArray();
        if ($filas === []) {
            return [];
        }
        $categorias = [];
        $ids = array_map(static fn ($f) => (int) $f['version_id'], $filas);
        foreach ($this->db->table('condiciones_categorias')->whereIn('version_condiciones_id', $ids)->get()->getResultArray() as $c) {
            $categorias[(int) $c['version_condiciones_id']][] = (string) $c['categoria_ingresos'];
        }
        foreach ($filas as &$f) {
            $f['categorias'] = $categorias[(int) $f['version_id']] ?? [];
        }

        return $filas;
    }
}
