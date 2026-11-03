<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Finanzas\CapacidadPago;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\GastoAdministrativo;
use App\Libraries\Finanzas\Tasas;
use App\Libraries\Validacion\Campos;
use DateTimeImmutable;

/**
 * CU-87 Generar Alternativas de Financiación para una solicitud con evaluación favorable (RF-14, RF-15, RN-23,
 * RN-48, RN-63, RN-68, RN-76, D-19).
 *
 *  - Capacidad de pago = ingresos × porcentaje máximo de afectación − cuotas vigentes y comprometidas (paso 2).
 *  - Monto máximo financiable y alternativas con CapacidadPago (pasos 3 a 6), sobre los plazos sugeridos de la línea:
 *    su mínimo, su máximo y los plazos habituales intermedios (3, 6, 9, 12, 18, 24, 30, 36, 48 y 60 cuotas).
 *  - La combinación pedida en la solicitud se incluye como primera alternativa cuando está dentro del monto máximo.
 * Las alternativas no viables se conservan con su motivo (RF-15).
 */
final class Estructurador
{
    public const PLAZOS_HABITUALES = [3, 6, 9, 12, 18, 24, 30, 36, 48, 60];

    public const MEDIOS_CONFORMIDAD = [
        'PresencialFirma' => 'Presencial, con firma del cliente',
        'Digital' => 'Digital (correo o portal)',
    ];

    /** @return list<int> */
    public static function plazosSugeridos(int $minimo, int $maximo): array
    {
        $p = array_filter(self::PLAZOS_HABITUALES, static fn (int $n): bool => $n > $minimo && $n < $maximo);

        return array_values(array_unique([$minimo, ...$p, $maximo]));
    }

    /**
     * @param array<string, mixed> $version tna, gasto_administrativo_cuota, gasto_otorgamiento_pct, monto_minimo,
     *                                      monto_maximo, cuotas_minimas, cuotas_maximas
     * @param array<string, mixed> $parametros versión de Estructuracion: porcentaje_afectacion, alicuota_iva,
     *                                         paso_redondeo, escalones
     * @return array{capacidad: Decimal, monto_maximo: Decimal, motivo: ?string,
     *     alternativas: list<array{importe: Decimal, cuotas: int, viable: bool, motivo: ?string, pedida: bool,
     *     primera_cuota: Decimal, total_a_pagar: Decimal, tea: Decimal, cftea: Decimal}>}
     */
    public static function generar(
        array $version,
        array $parametros,
        Decimal $ingresos,
        Decimal $obligaciones,
        string $importePedido,
        int $cuotasPedidas,
        DateTimeImmutable $fecha,
    ): array {
        $capacidad = CapacidadPago::capacidad($ingresos, Decimal::of((string) $parametros['porcentaje_afectacion']), $obligaciones);
        $vacio = ['capacidad' => $capacidad, 'monto_maximo' => Decimal::zero(), 'alternativas' => []];
        if (!$ingresos->isPositive()) {
            return $vacio + ['motivo' => 'El cliente no tiene ingresos declarados (CU-87 A3).'];
        }
        if (!$capacidad->isPositive()) {
            return $vacio + ['motivo' => 'La capacidad de pago es nula o negativa: las cuotas vigentes y comprometidas ya ocupan el porcentaje máximo de afectación de los ingresos (CU-87 A1).'];
        }
        $tna = Decimal::of((string) $version['tna']);
        $gasto = GastoAdministrativo::deVersion($version);
        $otorgamiento = Decimal::of((string) $version['gasto_otorgamiento_pct']);
        $iva = Decimal::of((string) $parametros['alicuota_iva']);
        $minimo = Decimal::of((string) $version['monto_minimo']);
        $maximo = Decimal::of((string) $version['monto_maximo']);
        // Tope global por cliente de los parámetros de estructuración (CU-59, D-22)
        if (($parametros['tope_global_cliente'] ?? null) !== null) {
            $maximo = Decimal::min($maximo, Decimal::of((string) $parametros['tope_global_cliente']));
        }
        $paso = Decimal::of((string) ($parametros['paso_redondeo'] ?? '1000'));
        $plazos = self::plazosSugeridos((int) $version['cuotas_minimas'], (int) $version['cuotas_maximas']);
        if ($cuotasPedidas >= (int) $version['cuotas_minimas'] && $cuotasPedidas <= (int) $version['cuotas_maximas'] && !in_array($cuotasPedidas, $plazos, true)) {
            $plazos[] = $cuotasPedidas;
            sort($plazos);
        }
        $r = CapacidadPago::alternativas($capacidad, $tna, $plazos, $gasto, $otorgamiento, $iva, $minimo, $maximo, $paso, (array) $parametros['escalones'], $fecha);
        if ($r['monto_maximo']->isZero()) {
            return $vacio + ['motivo' => 'Con su capacidad de pago, el monto máximo financiable no alcanza el importe mínimo de la línea (' . self::pesos($minimo) . ', CU-87 A2).'];
        }

        $alternativas = [];
        $pedido = Decimal::of($importePedido);
        $clavePedida = $pedido->toFixed(2) . '|' . $cuotasPedidas;
        foreach ($r['alternativas'] as $a) {
            // Las combinaciones de un escalón por debajo del mínimo de la línea no son alternativas: no se conservan
            if ($a['plan'] !== null) {
                $alternativas[] = self::formato($a, $a['importe']->toFixed(2) . '|' . $a['cuotas'] === $clavePedida, $tna, $otorgamiento, $iva);
            }
        }
        // La combinación de la solicitud, si no coincide con un escalón: se calcula con las mismas reglas
        if (!in_array(true, array_column($alternativas, 'pedida'), true) && in_array($cuotasPedidas, $plazos, true) && $pedido->compare($r['monto_maximo']) <= 0) {
            $p = CapacidadPago::alternativas($capacidad, $tna, [$cuotasPedidas], $gasto, $otorgamiento, $iva, $pedido, $pedido, Decimal::of('0.01'), ['1'], $fecha);
            foreach ($p['alternativas'] as $a) {
                array_unshift($alternativas, self::formato($a, true, $tna, $otorgamiento, $iva));
            }
        }
        // Primero la pedida; luego las viables por importe descendente y cuotas ascendentes; al final las descartadas
        usort($alternativas, static fn (array $a, array $b): int => [!$a['pedida'], !$a['viable'], $b['importe']->compare($a['importe']), $a['cuotas']]
            <=> [!$b['pedida'], !$b['viable'], 0, $b['cuotas']]);

        $viables = count(array_filter($alternativas, static fn (array $a): bool => $a['viable']));

        return [
            'capacidad' => $capacidad,
            'monto_maximo' => $r['monto_maximo'],
            'alternativas' => $alternativas,
            'motivo' => $viables === 0 ? 'Ninguna combinación de importe y plazo entra en la capacidad de pago del cliente.' : null,
        ];
    }

    /**
     * CU-79 pasos 5 y 6 (A6): constancia de conformidad del cliente.
     *
     * @param array<string, mixed> $d fecha (dd/mm/aaaa), medio, observaciones
     * @return array{errores: array<string, string>, datos: array{fecha: ?string, medio: string, observaciones: string}}
     */
    public static function validarConformidad(array $d, string $hoy, string $generacion): array
    {
        $e = [];
        $fecha = Campos::fecha($d['fecha_conformidad'] ?? '');
        if ($fecha === null) {
            $e['fecha_conformidad'] = 'Indique la fecha en que el cliente prestó su conformidad (dd/mm/aaaa).';
        } elseif ($fecha > $hoy) {
            $e['fecha_conformidad'] = 'La fecha de conformidad no puede ser futura.';
        } elseif ($fecha < substr($generacion, 0, 10)) {
            $e['fecha_conformidad'] = 'La conformidad no puede ser anterior a la generación de la alternativa.';
        }
        $medio = (string) ($d['medio_conformidad'] ?? '');
        if (!isset(self::MEDIOS_CONFORMIDAD[$medio])) {
            $e['medio_conformidad'] = 'Seleccione el medio de la conformidad.';
        }
        $obs = Campos::texto($d['observaciones'] ?? '');
        if (mb_strlen($obs) > 500) {
            $e['observaciones'] = 'Las observaciones admiten hasta 500 caracteres.';
        }

        return ['errores' => $e, 'datos' => ['fecha' => $fecha, 'medio' => $medio, 'observaciones' => $obs]];
    }

    /**
     * @param array<string, mixed> $a alternativa de CapacidadPago::alternativas
     * @return array{importe: Decimal, cuotas: int, viable: bool, motivo: ?string, pedida: bool, primera_cuota: Decimal, total_a_pagar: Decimal, tea: Decimal, cftea: Decimal}
     */
    private static function formato(array $a, bool $pedida, Decimal $tna, Decimal $otorgamiento, Decimal $iva): array
    {
        // Las descartadas por capacidad también informan su costo, para que el Analista vea por qué no entran (RF-15)
        $neto = Tasas::desembolso($a['importe'], $otorgamiento, $iva)['neto'];

        return [
            'importe' => $a['importe'], 'cuotas' => $a['cuotas'], 'viable' => $a['viable'], 'motivo' => $a['motivo'], 'pedida' => $pedida,
            'primera_cuota' => $a['plan']->cuotaTotalMaxima(), 'total_a_pagar' => $a['plan']->totalAPagar(),
            'tea' => $a['tea'] ?? Tasas::tea($tna), 'cftea' => $a['cftea'] ?? Tasas::cftea($neto, $a['plan']->totales()),
        ];
    }

    private static function pesos(Decimal $d): string
    {
        return '$ ' . self::miles($d->toFixed(2));
    }

    private static function miles(string $importe): string
    {
        [$e, $c] = explode('.', $importe);

        return strrev(implode('.', str_split(strrev($e), 3))) . ',' . $c;
    }
}
