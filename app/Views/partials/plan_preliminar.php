<?php
/**
 * Plan preliminar de una simulación o solicitud (CU-21 paso 8): resumen de costos y cuotas con sus conceptos (RN-68).
 *
 * @var array<string, mixed> $calculo resultado de Simulador::calcular()
 * @var string $importe
 * @var string $tna
 */
$plan = $calculo['plan'];
?>
<dl class="resumen-plan">
    <div><dt>Importe financiado</dt><dd class="importe"><?= esc(formato_importe($importe)) ?></dd></div>
    <div><dt>A desembolsar</dt><dd class="importe"><?= esc(formato_importe($calculo['neto'])) ?></dd></div>
    <div><dt>Primera cuota</dt><dd class="importe"><?= esc(formato_importe($calculo['primera_cuota'])) ?></dd></div>
    <div><dt>Total a pagar</dt><dd class="importe"><?= esc(formato_importe($calculo['total_a_pagar'])) ?></dd></div>
    <div><dt>TNA</dt><dd><?= esc(formato_porcentaje($tna)) ?></dd></div>
    <div><dt>Tasa mensual</dt><dd><?= esc(formato_porcentaje($calculo['tasa_mensual'], 4)) ?></dd></div>
    <div><dt>TEA</dt><dd><?= esc(formato_porcentaje($calculo['tea'])) ?></dd></div>
    <div><dt>CFTEA con IVA</dt><dd class="fw-semibold"><?= esc(formato_porcentaje($calculo['cftea'])) ?></dd></div>
</dl>
<p class="small text-body-secondary">Gasto de otorgamiento <?= esc(formato_importe($calculo['gasto_otorgamiento'])) ?> más IVA <?= esc(formato_importe($calculo['iva_otorgamiento'])) ?>, descontados del desembolso. Total de intereses <?= esc(formato_importe($calculo['total_intereses'])) ?>. Alícuota de IVA <?= esc(formato_porcentaje($calculo['alicuota_iva'] ?? '0.21', 0)) ?>. Los vencimientos son preliminares: se fijan a 30 días de la liquidación (RN-52).</p>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0" data-plan-cuotas>
        <thead><tr><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Gastos</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($plan->cuotas as $q): ?>
            <tr>
                <td><?= $q->numero ?></td>
                <td><?= esc(formato_fecha($q->vencimiento->format('Y-m-d'))) ?></td>
                <td class="importe"><?= esc(formato_importe($q->capital, false)) ?></td>
                <td class="importe"><?= esc(formato_importe($q->interes, false)) ?></td>
                <td class="importe"><?= esc(formato_importe($q->gastos, false)) ?></td>
                <td class="importe"><?= esc(formato_importe($q->iva, false)) ?></td>
                <td class="importe fw-medium"><?= esc(formato_importe($q->total(), false)) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot><tr><th scope="row" colspan="2">Totales</th><td class="importe fw-semibold"><?= esc(formato_importe($plan->totalCapital(), false)) ?></td><td class="importe fw-semibold"><?= esc(formato_importe($plan->totalIntereses(), false)) ?></td><td colspan="2"></td><td class="importe fw-semibold"><?= esc(formato_importe($plan->totalAPagar(), false)) ?></td></tr></tfoot>
    </table>
</div>
