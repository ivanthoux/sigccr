<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;
use DomainException;

/**
 * Cancelación anticipada total de un crédito (RN-67, CU-97).
 *
 * Importe = a) cuotas vencidas con su total actualizado (incluye punitorios exigibles y su IVA)
 *         + b) capital pendiente de las cuotas no vencidas
 *         + c) interés ordinario de la cuota en curso, proporcional a los días transcurridos desde el vencimiento anterior
 *              (o desde la liquidación) sobre 30 días, sin superar el interés pendiente de esa cuota
 *         + d) IVA del interés del punto c (el IVA de las cuotas vencidas ya integra su total).
 * No se cobran intereses ordinarios ni gastos de las demás cuotas no vencidas, ni comisión (art. 36, Ley 24.240).
 * Una cuota con vencimiento en la fecha de pago todavía no está vencida (3.5.1).
 */
final class CancelacionAnticipada
{
    /**
     * @param list<array{numero: int, vencimiento: DateTimeImmutable, conceptos: array<string, Decimal>}> $cuotasNoPagadas
     *        conceptos pendientes por cuota (claves de Imputador::PRELACION); para las vencidas, Punitorio e IVA incluyen
     *        los punitorios exigibles y su IVA a la fecha de pago
     *
     * @param DateTimeImmutable $fechaBasePlan fecha local de liquidación (o de aplicación, si el crédito proviene de una refinanciación)
     *
     * @return array{vencidas: Decimal, capital_no_vencido: Decimal, interes_en_curso: Decimal, iva_interes_en_curso: Decimal, dias_en_curso: int, cuota_en_curso: ?int, total: Decimal}
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
        $enCurso = null;

        foreach ($cuotasNoPagadas as $c) {
            $venc = $c['vencimiento']->setTime(0, 0);
            if ($venc < $pago) {
                $vencidas = $vencidas->add(Imputador::totalCuota($c['conceptos']));
                continue;
            }
            $capitalNoVencido = $capitalNoVencido->add($c['conceptos']['Capital'] ?? Decimal::zero());
            $enCurso ??= $c;
        }

        $interes = Decimal::zero();
        $dias = 0;
        if ($enCurso !== null) {
            // Vencimiento anterior según el calendario del plan (RN-52), aunque esa cuota ya esté pagada.
            $n = $enCurso['numero'];
            $anterior = $n > 1 ? CalendarioCuotas::vencimientos($fechaBasePlan, $n - 1)[$n - 2] : $fechaBasePlan->setTime(0, 0);
            $dias = max(0, (int) $anterior->diff($pago)->format('%r%a'));
            $interesCuota = $enCurso['conceptos']['InteresOrdinario'] ?? Decimal::zero();
            $interes = Decimal::min($interesCuota->mul($dias)->div(30)->round(2), $interesCuota);
        }
        $ivaInteres = $interes->mul($alicuotaIva)->round(2);
        $total = $vencidas->add($capitalNoVencido)->add($interes)->add($ivaInteres);

        return [
            'vencidas' => $vencidas,
            'capital_no_vencido' => $capitalNoVencido,
            'interes_en_curso' => $interes,
            'iva_interes_en_curso' => $ivaInteres,
            'dias_en_curso' => $dias,
            'cuota_en_curso' => $enCurso['numero'] ?? null,
            'total' => $total,
        ];
    }
}
