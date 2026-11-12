<?php
/**
 * CU-48 Obtener Comprobante de Pago, pasos 4 a 6 (A2, A4): comprobante con las cuotas alcanzadas por concepto. La
 * descarga abre la impresión del navegador, desde la que se guarda en PDF; no genera un nuevo cobro (RN-31).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 * @var bool $imprimir
 */
use App\Libraries\Caja\ReglasCaja;

$medios = ReglasCaja::MEDIOS_VENTANILLA + ['Efectivo' => 'Efectivo', 'PagoElectronico' => 'Pago electrónico', 'SaldoAFavor' => 'Saldo a favor'];
$etiquetas = ['Punitorio' => 'Punitorios', 'InteresOrdinario' => 'Interés', 'GastosCargos' => 'Gastos', 'IVA' => 'IVA', 'NoCapitalizable' => 'No capitalizable', 'Capital' => 'Capital'];
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2 d-print-none"><a href="<?= url_to('portal.comprobantes') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Comprobantes</a></nav>
<div class="encabezado">
    <div>
        <h1>Comprobante de pago N.º <?= (int) $c['comprobante'] ?></h1>
        <p><?= esc($c['denominacion']) ?><?= $c['cuit_cuil'] !== null ? ' (' . esc(formato_cuit($c['cuit_cuil'])) . ')' : '' ?></p>
    </div>
    <form method="post" action="<?= site_url('portal/comprobantes/' . (int) $c['id'] . '/descargar') ?>" class="d-print-none"><?= csrf_field() ?><button type="submit" class="btn btn-primary"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar o imprimir</button></form>
</div>
<?php if ($c['estado'] === 'Anulado'): ?>
    <div class="alert alert-danger"><strong>Cobro anulado</strong> el <?= esc(formato_fecha_hora($c['fecha_anulacion'])) ?>: <?= esc((string) $c['motivo_anulacion']) ?>. El importe quedó como saldo a favor (RN-33).</div>
<?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="t-pago">
    <h2 class="h5" id="t-pago">Pago del <?= esc(formato_fecha_hora($c['fecha_hora'])) ?></h2>
    <dl class="resumen-plan mb-2">
        <div><dt>Importe cobrado</dt><dd class="importe"><?= esc(formato_importe($c['importe'])) ?></dd></div>
        <div><dt>Estado</dt><dd><?= $c['estado'] === 'Anulado' ? 'Anulado' : 'Confirmado' ?></dd></div>
        <div><dt>Canal</dt><dd><?= $c['canal'] === 'Ventanilla' ? 'Sucursal' : 'Pago electrónico' ?></dd></div>
        <?php if ($c['cajero'] !== null): ?><div><dt>Atendido por</dt><dd><?= esc($c['cajero']) ?></dd></div><?php endif ?>
    </dl>
    <ul class="small mb-0">
        <?php foreach ($c['medios'] as $m): ?><li><?= esc($medios[$m['medio']] ?? $m['medio']) ?><?= $m['numero_operacion'] ? ', operación ' . esc((string) $m['numero_operacion']) : '' ?>: <?= esc(formato_importe($m['importe'])) ?></li><?php endforeach ?>
    </ul>
</section>
<section class="hoja p-4" aria-labelledby="t-cuotas">
    <h2 class="h5" id="t-cuotas">Cuotas alcanzadas</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><?php foreach ($etiquetas as $e): ?><th class="text-end"><?= esc($e) ?></th><?php endforeach ?><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php foreach ($c['cuotas'] as $q): ?>
                <tr>
                    <td><?= (int) $q['credito'] ?></td>
                    <td><?= (int) $q['cuota'] ?></td>
                    <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                    <?php foreach (array_keys($etiquetas) as $k): ?><td class="importe"><?= isset($q['conceptos'][$k]) ? esc(formato_importe($q['conceptos'][$k], false)) : '' ?></td><?php endforeach ?>
                    <td class="importe fw-medium"><?= esc(formato_importe($q['total'], false)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="small text-body-secondary mt-2 mb-0">Emitido el <?= esc(formato_fecha_hora($c['fecha_emision'])) ?><?= (int) $c['cantidad_reemisiones'] > 0 ? ' · reemisiones: ' . (int) $c['cantidad_reemisiones'] : '' ?>. La reemisión no genera un nuevo pago.</p>
</section>
<?php if ($imprimir): ?>
    <script>window.addEventListener('load', () => window.print());</script>
<?php endif ?>
<?php $this->endSection() ?>
