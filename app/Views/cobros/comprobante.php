<?php
/**
 * CU-35 Emitir Comprobante de Pago, paso 4 (A1, A3): cobro con sus medios y las cuotas alcanzadas por concepto.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 * @var bool $imprimir abre el diálogo de impresión después de la reemisión
 */
use App\Libraries\Caja\ReglasCaja;

$medios = ReglasCaja::MEDIOS_VENTANILLA + ['Efectivo' => 'Efectivo', 'PagoElectronico' => 'Pago electrónico'];
$etiquetas = ['Punitorio' => 'Punitorios', 'InteresOrdinario' => 'Interés', 'GastosCargos' => 'Gastos', 'IVA' => 'IVA', 'NoCapitalizable' => 'No capitalizable', 'Capital' => 'Capital'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2 d-print-none"><a href="<?= url_to('cobros.emitir-comprobante') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Comprobantes de pago</a></nav>
<div class="encabezado">
    <div>
        <h1>Comprobante de pago N.º <?= (int) $c['comprobante'] ?></h1>
        <p>Cobro N.º <?= (int) $c['numero'] ?> · <?= esc($c['denominacion']) ?> (<?= esc(formato_cuit($c['cuit_cuil'])) ?>)</p>
    </div>
    <div class="d-flex gap-2 d-print-none">
        <?php if (tiene_permiso('cobros.registrar')): ?><a href="<?= url_to('cobros.registrar') ?>?cliente=<?= (int) $c['cliente_id'] ?>" class="btn btn-outline-secondary">Nuevo cobro al cliente</a><?php endif ?>
        <form method="post" action="<?= site_url('admin/cobros/' . (int) $c['id'] . '/reemitir') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-primary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button></form>
    </div>
</div>
<?php if ($c['estado'] === 'Anulado'): ?>
    <div class="alert alert-danger"><strong>Cobro anulado</strong> el <?= esc(formato_fecha_hora($c['fecha_anulacion'])) ?>: <?= esc((string) $c['motivo_anulacion']) ?> (RN-33).</div>
<?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-cobro">
    <h2 class="h5" id="titulo-cobro">Pago del <?= esc(formato_fecha_hora($c['fecha_hora'])) ?></h2>
    <dl class="resumen-plan mb-2">
        <div><dt>Importe cobrado</dt><dd class="importe fw-semibold"><?= esc(formato_importe($c['importe'])) ?></dd></div>
        <div><dt>Estado</dt><dd><?= $c['estado'] === 'Anulado' ? 'Anulado' : 'Confirmado' ?></dd></div>
        <div><dt>Canal</dt><dd><?= $c['canal'] === 'Ventanilla' ? 'Ventanilla' : 'Pago electrónico' ?></dd></div>
        <?php if ($c['cajero'] !== null): ?><div><dt>Cajero</dt><dd><?= esc($c['cajero']) ?></dd></div><?php endif ?>
    </dl>
    <table class="table table-sm mb-0" style="max-width: 40rem">
        <thead><tr><th>Medio</th><th>N.º de operación</th><th class="text-end">Importe</th></tr></thead>
        <tbody>
        <?php foreach ($c['medios'] as $m): ?>
            <tr><td><?= esc($medios[$m['medio']] ?? $m['medio']) ?></td><td><?= esc((string) $m['numero_operacion']) ?></td><td class="importe"><?= esc(formato_importe($m['importe'], false)) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
</section>
<section class="hoja p-4" aria-labelledby="titulo-cuotas">
    <h2 class="h5" id="titulo-cuotas">Cuotas alcanzadas</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><?php foreach ($etiquetas as $e): ?><th class="text-end"><?= esc($e) ?></th><?php endforeach ?><th class="text-end">Total</th><th>Situación</th></tr></thead>
            <tbody>
            <?php foreach ($c['cuotas'] as $q): ?>
                <tr>
                    <td><?= (int) $q['credito'] ?></td>
                    <td><?= (int) $q['cuota'] ?></td>
                    <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                    <?php foreach (array_keys($etiquetas) as $k): ?><td class="importe"><?= isset($q['conceptos'][$k]) ? esc(formato_importe($q['conceptos'][$k], false)) : '' ?></td><?php endforeach ?>
                    <td class="importe fw-medium"><?= esc(formato_importe($q['total'], false)) ?></td>
                    <td class="small"><?= $q['estado'] === 'Pagada' ? 'Pagada' : 'Con saldo pendiente' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="small text-body-secondary mt-2 mb-0">Emitido el <?= esc(formato_fecha_hora($c['fecha_emision'])) ?><?= (int) $c['cantidad_reemisiones'] > 0 ? ' · reemisiones: ' . (int) $c['cantidad_reemisiones'] : '' ?>. La reemisión no genera un nuevo cobro (RN-31).</p>
</section>
<?php if ($imprimir): ?>
    <script>window.addEventListener('load', () => window.print());</script>
<?php endif ?>
<?php $this->endSection() ?>
