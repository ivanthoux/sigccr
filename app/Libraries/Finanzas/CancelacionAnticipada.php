<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use DomainException;

/**
 * Cancelación anticipada total de un crédito (RN-67, CU-97).
 *
 * Importe = a) cuotas vencidas con su total actualizado (incluye punitorios exigibles y su IVA)
 *         + b) capital pendiente de las cuotas no vencidas y su concepto no capitalizable pendiente, si el crédito proviene
 *              de una refinanciación (es deuda ya devengada que solo se difirió, RN-70; D-38)
 *         + c) interés ordinario de la cuota en curso, proporcional a los días transcurridos desde el vencimiento anterior
 *              (o desde la liquidación) sobre 30 días del interés de la cuota, sin superarlo y descontado lo que el
 *              cliente ya pagó de ese interés con un pago parcial (D-12)
 *         + d) IVA del interés devengado del punto c, descontado el IVA ya pagado de esa cuota (el IVA de las cuotas
 *              vencidas ya integra su total).
 * No se cobran intereses ordinarios ni gastos de las demás cuotas no vencidas, ni comisión (art. 36, Ley 24.240).
 * Una cuota con vencimiento en la fecha de pago todavía no está vencida (3.5.1).
 */
final class CancelacionAnticipada
{
    /**
     * @param list<array{numero: int, vencimiento: DateTimeImmutable, conceptos: array<string, Decimal>, nominal?: array{InteresOrdinario: Decimal, IVA: Decimal}}> $cuotasNoPagadas
     *        conceptos pendientes por cuota (claves de Imputador::PRELACION); para las vencidas, Punitorio e IVA incluyen
     *        los punitorios exigibles y su IVA a la fecha de pago. `nominal` son el interés y el IVA originales de la
     *        cuota: permiten descontar lo ya pagado con un pago parcial; sin él, se considera que no hubo pagos
     *
     * @param DateTimeImmutable $fechaBasePlan fecha local de liquidación (o de aplicación, si el crédito proviene de una refinanciación)
     *
     * @return array{vencidas: Decimal, capital_no_vencido: Decimal, no_capitalizable_no_vencido: Decimal, interes_en_curso: Decimal, iva_interes_en_curso: Decimal, dias_en_curso: int, cuota_en_curso: ?int, total: Decimal}
     */
    public static function calcular(array $cuotasNoPagadas, DateTimeImmutable $fechaPago, DateTimeImmutable $fechaBasePlan, Decimal $alicuotaIva): array
    {
        if ($cuotasNoPagadas === []) {
            throw new DomainException('El crédito no tiene cuotas pendientes.');
        }
        usort($cuotasNoPagadas, static fn ($a, $b) => $a['numero'] <=> $b['numero']);
        $pago = $fechaPago->setTime(0, 0);
        $vencidas = Decimal::zero();
        $capitalNoVencido = Decimal::zero();
        $noCapNoVencido = Decimal::zero();
        $enCurso = null;

        foreach ($cuotasNoPagadas as $c) {
            $venc = $c['vencimiento']->setTime(0, 0);
            if ($venc < $pago) {
                $vencidas = $vencidas->add(Imputador::totalCuota($c['conceptos']));
                continue;
            }
            $capitalNoVencido = $capitalNoVencido->add($c['conceptos']['Capital'] ?? Decimal::zero());
            $noCapNoVencido = $noCapNoVencido->add($c['conceptos']['NoCapitalizable'] ?? Decimal::zero());
            $enCurso ??= $c;
        }

        $interes = Decimal::zero();
        $ivaInteres = Decimal::zero();
        $dias = 0;
        if ($enCurso !== null) {
            // Vencimiento anterior según el calendario del plan (RN-52), aunque esa cuota ya esté pagada.
            $n = $enCurso['numero'];
            $anterior = $n > 1 ? CalendarioCuotas::vencimientos($fechaBasePlan, $n - 1)[$n - 2] : $fechaBasePlan->setTime(0, 0);
            $dias = max(0, (int) $anterior->diff($pago)->format('%r%a'));
            $pendiente = $enCurso['conceptos']['InteresOrdinario'] ?? Decimal::zero();
            $interesCuota = $enCurso['nominal']['InteresOrdinario'] ?? $pendiente;
            $devengado = Decimal::min($interesCuota->mul($dias)->div(30)->round(2), $interesCuota);
            // Lo ya pagado del interés de la cuota (pago parcial, D-12) cubre primero lo devengado
            $interes = self::noNegativo($devengado->sub($interesCuota->sub($pendiente)));
            $ivaInteres = $devengado->mul($alicuotaIva)->round(2);
            if (isset($enCurso['nominal'])) {
                $ivaPendiente = $enCurso['conceptos']['IVA'] ?? Decimal::zero();
                $ivaInteres = Decimal::min(self::noNegativo($ivaInteres->sub($enCurso['nominal']['IVA']->sub($ivaPendiente))), $ivaPendiente);
            }
        }
        $total = $vencidas->add($capitalNoVencido)->add($noCapNoVencido)->add($interes)->add($ivaInteres);

        return [
            'vencidas' => $vencidas,
            'capital_no_vencido' => $capitalNoVencido,
            'no_capitalizable_no_vencido' => $noCapNoVencido,
            'interes_en_curso' => $interes,
            'iva_interes_en_curso' => $ivaInteres,
            'dias_en_curso' => $dias,
            'cuota_en_curso' => $enCurso['numero'] ?? null,
            'total' => $total,
        ];
    }

    private static function noNegativo(Decimal $v): Decimal
    {
        return $v->isPositive() ? $v : Decimal::zero();
    }

    /**
     * Imputaciones del cobro de la cancelación y reducción de lo no devengado (CU-97 paso 6, D-38). Las cuotas vencidas
     * se cancelan por todos sus conceptos; la cuota en curso, por su capital, su no capitalizable, el interés
     * proporcional y su IVA; las demás no vencidas, por su capital y su no capitalizable. El resto (interés, gastos e
     * IVA no devengados) se reduce sin cobrarse.
     *
     * @param list<array{id: int, numero: int, vencimiento: DateTimeImmutable, conceptos: array<string, Decimal>}> $cuotasNoPagadas
     * @param array{interes_en_curso: Decimal, iva_interes_en_curso: Decimal, cuota_en_curso: ?int} $calculo de calcular()
     * @return array{imputaciones: list<array{cuota: int, concepto: string, importe: Decimal, parcial: bool}>, interes_no_devengado: Decimal, gastos_no_devengados: Decimal, iva_no_devengado: Decimal}
     */
    public static function distribuir(array $cuotasNoPagadas, DateTimeImmutable $fechaPago, array $calculo): array
    {
        usort($cuotasNoPagadas, static fn ($a, $b) => $a['numero'] <=> $b['numero']);
        $pago = $fechaPago->setTime(0, 0);
        $imputaciones = [];
        $interes = Decimal::zero();
        $gastos = Decimal::zero();
        $iva = Decimal::zero();
        foreach ($cuotasNoPagadas as $c) {
            $k = $c['conceptos'];
            $vencida = $c['vencimiento']->setTime(0, 0) < $pago;
            $enCurso = !$vencida && $c['numero'] === $calculo['cuota_en_curso'];
            foreach (Imputador::PRELACION as $concepto) {
                $pendiente = $k[$concepto] ?? Decimal::zero();
                $importe = match (true) {
                    $vencida, in_array($concepto, ['Capital', 'NoCapitalizable'], true) => $pendiente,
                    $enCurso && $concepto === 'InteresOrdinario' => Decimal::min($pendiente, $calculo['interes_en_curso']),
                    $enCurso && $concepto === 'IVA' => Decimal::min($pendiente, $calculo['iva_interes_en_curso']),
                    default => Decimal::zero(),
                };
                if ($importe->isPositive()) {
                    $imputaciones[] = ['cuota' => $c['id'], 'concepto' => $concepto, 'importe' => $importe, 'parcial' => false];
                }
                if ($vencida) {
                    continue;
                }
                $resto = $pendiente->sub($importe);
                if ($concepto === 'InteresOrdinario') {
                    $interes = $interes->add($resto);
                } elseif ($concepto === 'GastosCargos') {
                    $gastos = $gastos->add($resto);
                } elseif ($concepto === 'IVA') {
                    $iva = $iva->add($resto);
                }
            }
        }

        return ['imputaciones' => $imputaciones, 'interes_no_devengado' => $interes, 'gastos_no_devengados' => $gastos, 'iva_no_devengado' => $iva];
    }
}
