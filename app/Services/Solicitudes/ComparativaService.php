<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Finanzas\CapacidadPago;
use App\Libraries\Finanzas\ComparadorLineas;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\GastoAdministrativo;
use App\Libraries\Solicitudes\Simulador;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use DateTimeImmutable;

/**
 * CU-99 Simular Todas las Líneas de Crédito (D-10): con una sola capacidad de pago compara las líneas activas y
 * vigentes para el cliente y guarda la alternativa recomendada de cada línea viable como una simulación vinculada,
 * que se puede formalizar con CU-22. Es preliminar (RN-17): no consulta la Central de Deudores.
 */
class ComparativaService extends BaseService
{
    public const CRITERIOS = ['Monto' => 'Mayor monto prestable', 'Costo' => 'Menor costo (CFTEA)'];

    /**
     * CU-99 pasos 4 a 10 (A1 a A6).
     *
     * @return int identificador de la simulación comparativa
     */
    public function simular(int $clienteId, string $importeTexto, string $cuotasTexto, string $criterio, int $usuarioId): int
    {
        $errores = [];
        $importe = null;
        if (trim($importeTexto) !== '') {
            $importe = Campos::importe($importeTexto);
            if ($importe === null || !Decimal::of($importe)->isPositive()) {
                $errores['importe'] = 'Ingrese un importe válido, por ejemplo 500.000,00, o déjelo vacío.';
            }
        }
        $cuotas = null;
        if (trim($cuotasTexto) !== '') {
            $cuotas = ctype_digit(trim($cuotasTexto)) ? (int) trim($cuotasTexto) : 0;
            if ($cuotas < 1 || $cuotas > 360) {
                $errores['cantidad_cuotas'] = 'Ingrese una cantidad de cuotas entre 1 y 360, o déjela vacía.';
            }
        }
        if (!isset(self::CRITERIOS[$criterio])) {
            $errores['criterio'] = 'Seleccione el criterio de comparación.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-76');
        }

        return $this->transaccion(function () use ($clienteId, $importe, $cuotas, $criterio, $usuarioId): int {
            $simulaciones = new SimulacionService($this->db);
            $cliente = $simulaciones->cliente($clienteId);
            if ($cliente['estado'] !== 'Activo') {
                throw new ReglaNegocioException('Solo se puede simular para un cliente activo.');
            }
            if (!Decimal::of((string) $cliente['ingresos_declarados'])->isPositive()) {
                throw new ReglaNegocioException('El cliente no tiene ingresos declarados: regístrelos al modificar sus datos (CU-12) y vuelva a simular.');
            }
            $lineas = $simulaciones->lineasParaCliente($clienteId);
            // El bloqueo de las líneas serializa la comparación con una suspensión concurrente (A6, RN-16)
            $this->bloquear('lineas_credito', array_map(static fn ($l) => (int) $l['id'], $lineas));

            $fila = $this->db->table('clientes')->select('id, ingresos_declarados, fecha_alta')->where('id', $clienteId)->get()->getRowArray();
            $internos = (new EvaluacionService($this->db))->antecedentesInternos((array) $fila, 0);
            $parametros = (new ParametroService($this->db))->vigente('Estructuracion');
            $p = $parametros['valores'];
            $capacidad = CapacidadPago::capacidad(Decimal::of($internos['ingresos']), Decimal::of((string) $p['porcentaje_afectacion']), Decimal::of($internos['obligaciones']));
            $hoy = new DateTimeImmutable($this->hoyNegocio());
            $r = ComparadorLineas::comparar(
                $capacidad,
                array_map(static fn ($l) => [
                    'id' => (int) $l['id'], 'nombre' => (string) $l['nombre'], 'tna' => (string) $l['tna'], 'gasto_administrativo_cuota' => (string) $l['gasto_administrativo_cuota'],
                    'tipo_gasto_administrativo' => (string) ($l['tipo_gasto_administrativo'] ?? 'Fijo'), 'gasto_administrativo_pct' => $l['gasto_administrativo_pct'] ?? null,
                    'gasto_otorgamiento_pct' => (string) $l['gasto_otorgamiento_pct'], 'monto_minimo' => (string) $l['monto_minimo'], 'monto_maximo' => ($p['tope_global_cliente'] ?? null) === null ? (string) $l['monto_maximo'] : Decimal::min(Decimal::of((string) $l['monto_maximo']), Decimal::of((string) $p['tope_global_cliente']))->toFixed(2),
                    'cuotas_minimas' => (int) $l['cuotas_minimas'], 'cuotas_maximas' => (int) $l['cuotas_maximas'], 'motivo_no_admitida' => $l['motivo_no_admitida'],
                ], $lineas),
                Decimal::of((string) $p['alicuota_iva']),
                Decimal::of((string) ($p['paso_redondeo'] ?? '1000')),
                $hoy,
                $importe === null ? null : Decimal::of($importe),
                $cuotas,
                $criterio === 'Costo' ? ComparadorLineas::CRITERIO_COSTO : ComparadorLineas::CRITERIO_MONTO,
            );

            $ahora = $this->ahoraUtc();
            $vence = (new DateTimeImmutable($ahora))->modify('+' . Simulador::VIGENCIA_DIAS . ' days')->format('Y-m-d H:i:s.vP');
            $this->db->table('simulaciones_comparativas')->insert([
                'cliente_id' => $clienteId, 'version_parametros_id' => $parametros['id'], 'fecha_generacion' => $ahora,
                'ingresos_considerados' => $internos['ingresos'], 'obligaciones_vigentes' => $internos['obligaciones'], 'capacidad_pago' => $capacidad->toFixed(2),
                'importe_pedido' => $importe, 'cuotas_pedidas' => $cuotas, 'criterio' => $criterio,
                'lineas_no_viables' => $r['no_viables'] === [] ? null : json_encode($r['no_viables'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'vence' => $vence, 'usuario_id' => $usuarioId,
            ]);
            $id = (int) $this->db->insertID();
            $versiones = array_column($lineas, 'version_id', 'id');
            foreach ($r['lineas'] as $i => $l) {
                $this->db->table('simulaciones')->insert([
                    'cliente_id' => $clienteId, 'version_condiciones_id' => (int) $versiones[$l['id']], 'fecha_generacion' => $ahora,
                    'importe' => $l['importe']->toFixed(2), 'cantidad_cuotas' => $l['cuotas'], 'valor_cuota' => $l['primera_cuota']->toFixed(2),
                    'tea' => $l['tea']->toFixed(12), 'cftea' => $l['cftea']->toFixed(12), 'vence' => $vence, 'estado' => 'Vigente',
                    'simulacion_comparativa_id' => $id, 'posicion' => $i + 1,
                ]);
            }
            (new AuditoriaService($this->db))->registrar('solicitudes.simular_comparativa', 'simulaciones_comparativas', $id, 'Exitoso', null, [
                'cliente' => $clienteId, 'capacidad' => $capacidad->toFixed(2), 'viables' => count($r['lineas']), 'no_viables' => count($r['no_viables']),
                'mayor_monto' => $r['mayor_monto'], 'menor_costo' => $r['menor_costo'], 'criterio' => $criterio,
            ]);

            return $id;
        });
    }

    /**
     * CU-99 paso 9: cuadro comparativo registrado.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $c = $this->db->query(
            'SELECT sc.*, c.denominacion, CASE WHEN sc.vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente FROM dbo.simulaciones_comparativas sc '
            . 'JOIN dbo.clientes c ON c.id = sc.cliente_id WHERE sc.id = ?',
            [$id],
        )->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('La simulación comparativa no existe.');
        }
        $c['no_viables'] = $c['lineas_no_viables'] === null ? [] : json_decode((string) $c['lineas_no_viables'], true);
        $valores = $this->db->table('versiones_parametros')->select('valores')->where('id', (int) $c['version_parametros_id'])->get()->getRowArray();
        $c['paso'] = (string) (json_decode((string) ($valores['valores'] ?? '{}'), true)['paso_redondeo'] ?? '1000');
        $simulaciones = new SimulacionService($this->db);
        $c['lineas'] = [];
        foreach ($this->db->table('simulaciones')->select('id')->where('simulacion_comparativa_id', $id)->orderBy('posicion')->get()->getResultArray() as $f) {
            $c['lineas'][] = $simulaciones->obtener((int) $f['id']);
        }
        // Mayor monto prestable y menor costo (menor CFTEA; ante igualdad, menor total a pagar)
        $c['mayor_monto'] = null;
        $c['menor_costo'] = null;
        foreach ($c['lineas'] as $l) {
            $monto = $this->montoMaximo($c, $l);
            if ($c['mayor_monto'] === null || $monto->compare($c['mayor_monto'][1]) > 0) {
                $c['mayor_monto'] = [(int) $l['id'], $monto];
            }
            $costo = [Decimal::of((string) $l['cftea']), $l['calculo']['total_a_pagar']];
            if ($c['menor_costo'] === null || ($costo[0]->compare($c['menor_costo'][1][0]) ?: $costo[1]->compare($c['menor_costo'][1][1])) < 0) {
                $c['menor_costo'] = [(int) $l['id'], $costo];
            }
            $c['montos'][(int) $l['id']] = $monto;
        }

        return $c;
    }

    /**
     * Monto máximo prestable de la línea con la capacidad y el redondeo de la comparativa: se recalcula para
     * presentarlo y coincide con el de la generación, porque usa los mismos datos.
     *
     * @param array<string, mixed> $c
     * @param array<string, mixed> $l
     */
    private function montoMaximo(array $c, array $l): Decimal
    {
        $iva = Decimal::of((string) ($l['calculo']['alicuota_iva'] ?? '0.21'));
        $maximo = Decimal::zero();
        foreach (range((int) $l['cuotas_minimas'], (int) $l['cuotas_maximas']) as $n) {
            $maximo = Decimal::max($maximo, CapacidadPago::capitalMaximo(Decimal::of((string) $c['capacidad_pago']), Decimal::of((string) $l['tna']), $n, GastoAdministrativo::deVersion($l), $iva, Decimal::of($c['paso'])));
        }

        return Decimal::min($maximo, Decimal::of((string) $l['monto_maximo']));
    }
}
