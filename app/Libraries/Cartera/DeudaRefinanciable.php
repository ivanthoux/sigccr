<?php

declare(strict_types=1);

namespace App\Libraries\Cartera;

use App\Libraries\Finanzas\Decimal;

/**
 * Composición por cuota de la deuda que se refinancia y su revalidación (RN-36, RN-70; CU-39, CU-40, CU-41, CU-74).
 *
 * Por cuota no pagada, a la fecha de corte:
 * - capital: capital pendiente;
 * - no capitalizable: concepto no capitalizable pendiente de una refinanciación anterior y, si la cuota venció antes
 *   del corte, interés, gastos, IVA y punitorios exigibles con su IVA (Refinanciacion::composicion);
 * - punitorio: la parte de punitorios con su IVA incluida en el no capitalizable.
 *
 * La deuda varió si una cuota dejó de estar pendiente o apareció otra, si cambió el capital o el no capitalizable sin
 * punitorios, o si los punitorios bajaron (pago o condonación). El aumento de punitorios después del corte no es una
 * variación: se incorpora al aplicar (RN-70).
 */
final class DeudaRefinanciable
{
    public const IMPAGAS = ['Pendiente', 'Vencida'];

    /**
     * @param array<string, mixed> $c fila de cuotas
     * @return array{capital: Decimal, no_capitalizable: Decimal, punitorio: Decimal}
     */
    public static function porCuota(array $c, string $fechaCorte): array
    {
        $noCap = Decimal::of((string) $c['no_capitalizable_pendiente']);
        $punitorio = Decimal::zero();
        if (substr((string) $c['vencimiento'], 0, 10) < $fechaCorte) {
            $punitorio = Decimal::of((string) $c['punitorio_pendiente'])->round(2)->add(Decimal::of((string) $c['iva_punitorio_pendiente'])->round(2));
            $noCap = $noCap->add((string) $c['interes_pendiente'])->add((string) $c['gastos_pendiente'])->add((string) $c['iva_pendiente'])->add($punitorio);
        }

        return ['capital' => Decimal::of((string) $c['capital_pendiente']), 'no_capitalizable' => $noCap, 'punitorio' => $punitorio];
    }

    /**
     * @param list<array<string, mixed>> $cuotas filas de cuotas no pagadas
     * @return array{capital: Decimal, no_capitalizable: Decimal, punitorio: Decimal, cuotas: array<int, array{capital: Decimal, no_capitalizable: Decimal, punitorio: Decimal}>}
     */
    public static function composicion(array $cuotas, string $fechaCorte): array
    {
        $t = ['capital' => Decimal::zero(), 'no_capitalizable' => Decimal::zero(), 'punitorio' => Decimal::zero(), 'cuotas' => []];
        foreach ($cuotas as $c) {
            $p = self::porCuota($c, $fechaCorte);
            $t['cuotas'][(int) $c['id']] = $p;
            foreach (['capital', 'no_capitalizable', 'punitorio'] as $k) {
                $t[$k] = $t[$k]->add($p[$k]);
            }
        }

        return $t;
    }

    /**
     * Diferencias entre la composición guardada y las cuotas actuales de los mismos créditos.
     *
     * @param array<int, array{capital_pendiente: string, no_capitalizable: string, punitorio: string}> $guardadas por cuota
     * @param list<array<string, mixed>> $actuales cuotas de los créditos comprendidos (cualquier estado), con credito_numero
     * @return list<string> vacía si la deuda no varió
     */
    public static function variaciones(array $guardadas, array $actuales, string $fechaCorte): array
    {
        $v = [];
        $vistas = [];
        foreach ($actuales as $c) {
            $id = (int) $c['id'];
            $nombre = 'Crédito ' . ($c['credito_numero'] ?? $c['credito_id']) . ', cuota ' . $c['numero'];
            $impaga = in_array($c['estado'], self::IMPAGAS, true);
            if (!isset($guardadas[$id])) {
                if ($impaga) {
                    $v[] = "{$nombre}: volvió a estar pendiente (por ejemplo, por la anulación de un cobro).";
                }
                continue;
            }
            $vistas[$id] = true;
            if (!$impaga) {
                $v[] = "{$nombre}: ya no está pendiente ({$c['estado']}).";
                continue;
            }
            $g = $guardadas[$id];
            $p = self::porCuota($c, $fechaCorte);
            if ($p['capital']->compare($g['capital_pendiente']) !== 0) {
                $v[] = "{$nombre}: el capital pendiente pasó de " . Decimal::of($g['capital_pendiente'])->toFixed(2) . ' a ' . $p['capital']->toFixed(2) . '.';
            }
            $sinPunitorioGuardado = Decimal::of($g['no_capitalizable'])->sub($g['punitorio']);
            if ($p['no_capitalizable']->sub($p['punitorio'])->compare($sinPunitorioGuardado) !== 0) {
                $v[] = "{$nombre}: cambiaron los intereses, gastos o IVA comprendidos.";
            }
            if ($p['punitorio']->compare($g['punitorio']) < 0) {
                $v[] = "{$nombre}: los punitorios bajaron de " . Decimal::of($g['punitorio'])->toFixed(2) . ' a ' . $p['punitorio']->toFixed(2) . ' (pago o condonación).';
            }
        }
        foreach (array_keys($guardadas) as $id) {
            if (!isset($vistas[$id])) {
                $v[] = "La cuota {$id} comprendida ya no pertenece a los créditos de la propuesta.";
            }
        }

        return $v;
    }

    /**
     * RN-70: punitorios con IVA devengados desde la fecha de corte, que se incorporan al concepto no capitalizable al
     * aplicar. Supone que variaciones() no informó diferencias.
     *
     * @param array<int, array{capital_pendiente: string, no_capitalizable: string, punitorio: string}> $guardadas
     * @param list<array<string, mixed>> $actuales
     */
    public static function punitoriosDesdeCorte(array $guardadas, array $actuales, string $fechaCorte): Decimal
    {
        $total = Decimal::zero();
        foreach ($actuales as $c) {
            if (!isset($guardadas[(int) $c['id']]) || !in_array($c['estado'], self::IMPAGAS, true)) {
                continue;
            }
            // Los de una cuota que venció después del corte también se incorporan: su interés no entró en el corte,
            // pero sus punitorios se devengaron sobre deuda comprendida
            $actual = Decimal::of((string) $c['punitorio_pendiente'])->round(2)->add(Decimal::of((string) $c['iva_punitorio_pendiente'])->round(2));
            $diferencia = $actual->sub($guardadas[(int) $c['id']]['punitorio']);
            if ($diferencia->isPositive()) {
                $total = $total->add($diferencia);
            }
        }

        return $total;
    }
}
