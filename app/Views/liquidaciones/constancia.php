<?php
/**
 * CU-27 paso 9: constancia de desembolso con el crédito activo y su plan definitivo (RN-26).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $l
 */
use App\Libraries\Finanzas\Decimal;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<span class="documento-propio" hidden></span><?php // D-57: con encabezado propio; al imprimir lleva solo la cabecera y el pie de cada hoja ?>
<nav aria-label="Ruta" class="mb-2 d-print-none"><a href="<?= url_to('creditos.liquidar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Créditos aprobados</a></nav>
<div class="encabezado">
    <div>
        <h1>Constancia de desembolso</h1>
        <p>Crédito N.º <?= (int) $l['credito_numero'] ?> · solicitud N.º <?= (int) $l['solicitud_numero'] ?> · <?= esc($l['denominacion']) ?> (<?= esc(formato_cuit($l['cuit_cuil'])) ?>)</p>
    </div>
    <button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button>
</div>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-liq">
    <h2 class="h5" id="titulo-liq">Liquidación N.º <?= (int) $l['id'] ?> del <?= esc(formato_fecha_hora($l['fecha'])) ?></h2>
    <dl class="resumen-plan mb-2">
        <div><dt>Capital</dt><dd class="importe"><?= esc(formato_importe($l['capital'])) ?></dd></div>
        <div><dt>Gasto de otorgamiento</dt><dd class="importe"><?= esc(formato_importe($l['gasto_otorgamiento'])) ?></dd></div>
        <div><dt>IVA del gasto</dt><dd class="importe"><?= esc(formato_importe($l['iva_gastos'])) ?></dd></div>
        <div><dt>Neto desembolsado</dt><dd class="importe fw-semibold"><?= esc(formato_importe($l['importe_neto'])) ?></dd></div>
        <div><dt>Medio</dt><dd><?= $l['medio_desembolso'] === 'Efectivo' ? 'Efectivo por caja' : 'Transferencia' ?></dd></div>
        <div><dt>TNA</dt><dd><?= esc(formato_porcentaje((string) $l['tna'])) ?></dd></div>
    </dl>
    <?php if ($l['medio_desembolso'] === 'Transferencia'): ?><p class="small mb-1">Cuenta de destino <?= esc(substr((string) $l['cuenta_destino'], 0, 8) . '…' . substr((string) $l['cuenta_destino'], -4)) ?> · <?= esc((string) $l['comprobante']) ?></p><?php endif ?>
    <p class="small mb-0">Registró <?= esc($l['responsable']) ?> · <?= esc($l['linea']) ?>. El crédito quedó <?= esc(mb_strtolower((string) $l['estado_credito'])) ?> con sus cuotas exigibles desde el primer vencimiento (RN-52).</p>
</section>
<section class="hoja p-4" aria-labelledby="titulo-cuotas">
    <h2 class="h5" id="titulo-cuotas">Plan definitivo de cuotas</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Gastos</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php $total = Decimal::zero(); foreach ($l['cuotas'] as $q): $t = Decimal::of((string) $q['capital'])->add((string) $q['interes'])->add((string) $q['gastos'])->add((string) $q['iva']); $total = $total->add($t); ?>
                <tr>
                    <td><?= (int) $q['numero'] ?></td>
                    <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                    <td class="importe"><?= esc(formato_importe($q['capital'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe($q['interes'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe($q['gastos'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe($q['iva'], false)) ?></td>
                    <td class="importe fw-medium"><?= esc(formato_importe($t, false)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
            <tfoot><tr><th colspan="6">Total a pagar</th><th class="importe"><?= esc(formato_importe($total, false)) ?></th></tr></tfoot>
        </table>
    </div>
</section>
<?php $this->endSection() ?>
