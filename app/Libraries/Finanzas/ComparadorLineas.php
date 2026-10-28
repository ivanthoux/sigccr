<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;

/**
 * Simulación comparativa de todas las líneas para un cliente (CU-99, D-10).
 *
 * Con una sola capacidad de pago (CU-87 pasos 1 y 2) se calcula, para cada línea admitida:
 *  - el monto máximo prestable: el mayor capital, entre las cantidades de cuotas de la línea, cuya primera cuota con
 *    IVA no supera la capacidad, limitado al importe mínimo y máximo de la línea (D-01, D-09);
 *  - una alternativa recomendada: el importe pedido por el cliente (o el monto máximo, si no pidió uno), en la menor
 *    cantidad de cuotas que entra en su capacidad (o en la cantidad pedida, si la indicó), que es la de menor costo
 *    total entre las que puede pagar.
 * Las líneas se ordenan por el criterio elegido: mayor monto prestable o menor CFTEA (desempate: menor total a pagar).
 * El resultado es preliminar (RN-17): no consulta la Central de Deudores ni reemplaza la evaluación crediticia.
 */
final class ComparadorLineas
{
    public const CRITERIO_MONTO = 'monto';
    public const CRITERIO_COSTO = 'costo';

    /**
     * @param list<array{id: int, nombre: string, tna: string, gasto_administrativo_cuota: string, gasto_otorgamiento_pct: string,
     *     monto_minimo: string, monto_maximo: string, cuotas_minimas: int, cuotas_maximas: int, motivo_no_admitida?: ?string}> $lineas
     *     motivo_no_admitida informa por qué el cliente no cumple la elegibilidad de la línea (RN-75); null si la cumple
     *
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     no_viables: list<array{id: int, nombre: string, motivo: string}>,
     *     mayor_monto: ?int,
     *     menor_costo: ?int
     * }
     */
    public static function comparar(
        Decimal $capacidad,
        array $lineas,
        Decimal $alicuotaIva,
        Decimal $paso,
        DateTimeImmutable $fechaReferencia,
        ?Decimal $importePedido = null,
        ?int $cuotasPedidas = null,
        string $criterio = self::CRITERIO_MONTO,
    ): array {
        $viables = [];
        $noViables = [];
        foreach ($lineas as $l) {
            $base = ['id' => $l['id'], 'nombre' => $l['nombre']];
            if (($l['motivo_no_admitida'] ?? null) !== null) {
                $noViables[] = $base + ['motivo' => (string) $l['motivo_no_admitida']];
                continue;
            }
            if (!$capacidad->isPositive()) {
                $noViables[] = $base + ['motivo' => 'El cliente no tiene capacidad de pago disponible.'];
                continue;
            }
            $tna = Decimal::of($l['tna']);
            $gasto = Decimal::of($l['gasto_administrativo_cuota']);
            $minimo = Decimal::of($l['monto_minimo']);
            $maximoLinea = Decimal::of($l['monto_maximo']);
            $plazos = range($l['cuotas_minimas'], $l['cuotas_maximas']);

            $montoMaximo = Decimal::zero();
            foreach ($plazos as $n) {
                $c = Decimal::min(CapacidadPago::capitalMaximo($capacidad, $tna, $n, $gasto, $alicuotaIva, $paso), $maximoLinea);
                $montoMaximo = Decimal::max($montoMaximo, $c);
            }
            if ($montoMaximo->compare($minimo) < 0) {
                $noViables[] = $base + ['motivo' => 'Su capacidad de pago no alcanza el importe mínimo de la línea ($ ' . $minimo->toFixed(2) . ').'];
                continue;
            }

            $importe = $montoMaximo;
            $nota = null;
            if ($importePedido !== null) {
                if ($importePedido->compare($minimo) < 0) {
                    $noViables[] = $base + ['motivo' => 'El importe pedido es menor que el mínimo de la línea ($ ' . $minimo->toFixed(2) . ').'];
                    continue;
                }
                $importe = Decimal::min($importePedido, $montoMaximo);
                if ($importe->compare($importePedido) < 0) {
                    $nota = 'Se ofrece el monto máximo prestable: el importe pedido supera ' . ($montoMaximo->equals($maximoLinea) ? 'el máximo de la línea.' : 'su capacidad de pago.');
                }
            }

            $candidatos = $cuotasPedidas !== null ? array_values(array_filter($plazos, static fn ($n) => $n === $cuotasPedidas)) : $plazos;
            if ($candidatos === []) {
                $noViables[] = $base + ['motivo' => "La línea no admite {$cuotasPedidas} cuotas (admite de {$l['cuotas_minimas']} a {$l['cuotas_maximas']})."];
                continue;
            }
            $elegido = null;
            foreach ($candidatos as $n) {
                $plan = AmortizacionFrancesa::plan($importe, $tna, $n, $fechaReferencia, $gasto, $alicuotaIva);
                if ($plan->cuotaTotalMaxima()->compare($capacidad) <= 0) {
                    $elegido = [$n, $plan];
                    break;
                }
            }
            if ($elegido === null) {
                $noViables[] = $base + ['motivo' => "La cuota en {$cuotasPedidas} cuotas supera su capacidad de pago."];
                continue;
            }
            [$n, $plan] = $elegido;
            $desembolso = Tasas::desembolso($importe, Decimal::of($l['gasto_otorgamiento_pct']), $alicuotaIva);
            $viables[] = $base + [
                'monto_maximo' => $montoMaximo,
                'importe' => $importe,
                'cuotas' => $n,
                'primera_cuota' => $plan->cuotaTotalMaxima(),
                'total_a_pagar' => $plan->totalAPagar(),
                'neto' => $desembolso['neto'],
                'tna' => $tna,
                'tea' => Tasas::tea($tna),
                'cftea' => Tasas::cftea($desembolso['neto'], $plan->totales()),
                'plan' => $plan,
                'nota' => $nota,
            ];
        }

        $mayorMonto = null;
        $menorCosto = null;
        foreach ($viables as $v) {
            if ($mayorMonto === null || $v['monto_maximo']->compare($mayorMonto['monto_maximo']) > 0) {
                $mayorMonto = $v;
            }
            if ($menorCosto === null || self::compararCosto($v, $menorCosto) < 0) {
                $menorCosto = $v;
            }
        }
        usort($viables, static function (array $a, array $b) use ($criterio): int {
            if ($criterio === self::CRITERIO_COSTO) {
                return self::compararCosto($a, $b);
            }
            $porMonto = $b['monto_maximo']->compare($a['monto_maximo']);

            return $porMonto !== 0 ? $porMonto : self::compararCosto($a, $b);
        });

        return [
            'lineas' => $viables,
            'no_viables' => $noViables,
            'mayor_monto' => $mayorMonto['id'] ?? null,
            'menor_costo' => $menorCosto['id'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function compararCosto(array $a, array $b): int
    {
        $porCft = $a['cftea']->compare($b['cftea']);

        return $porCft !== 0 ? $porCft : $a['total_a_pagar']->compare($b['total_a_pagar']);
    }
}
