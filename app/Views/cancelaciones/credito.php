<?php
/**
 * CU-97 Registrar Cancelación Anticipada, pasos 3 y 4 (A3 a A5): importe a hoy según RN-67, reducción de lo no
 * devengado y cobro por un medio bancario (RN-77).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $d
 * @var array<string, mixed>|null $jornada
 */
use App\Libraries\Caja\MediosCobro;

$c = $d['credito'];
$k = $d['calculo'];
$r = $d['distribucion'];
$reduccion = $r['interes_no_devengado']->add($r['gastos_no_devengados'])->add($r['iva_no_devengado']);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('creditos.cancelar-anticipadamente') ?>?cliente=<?= (int) $c['cliente_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Créditos del cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Cancelación anticipada del crédito N.º <?= (int) $c['numero'] ?></h1>
        <p>Importe calculado al <?= esc(formato_fecha($d['fecha'])) ?>: <strong><?= esc(formato_importe($k['total'])) ?></strong></p>
    </div>
</div>
<?php if ($d['ordenes'] !== []): ?>
    <div class="alert alert-warning">Hay cuotas en la orden de pago electrónico N.º <?= esc(implode(', ', $d['ordenes'])) ?>, pendiente o en conciliación: no puede cancelarse hasta que se confirme o venza (A4).</div>
<?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-detalle">
    <h2 class="h5" id="titulo-detalle">Detalle (RN-67)</h2>
    <table class="table table-sm mb-0" style="max-width: 44rem">
        <tbody>
            <tr><td>Cuotas vencidas, con su total actualizado y los punitorios exigibles</td><td class="importe"><?= esc(formato_importe($k['vencidas'], false)) ?></td></tr>
            <tr><td>Capital de las cuotas no vencidas</td><td class="importe"><?= esc(formato_importe($k['capital_no_vencido'], false)) ?></td></tr>
            <?php if ($k['no_capitalizable_no_vencido']->isPositive()): ?><tr><td>Concepto no capitalizable de las cuotas no vencidas</td><td class="importe"><?= esc(formato_importe($k['no_capitalizable_no_vencido'], false)) ?></td></tr><?php endif ?>
            <tr><td>Interés de la cuota en curso<?= $k['cuota_en_curso'] !== null ? ' (cuota ' . (int) $k['cuota_en_curso'] . ', ' . (int) $k['dias_en_curso'] . ' días de 30)' : '' ?></td><td class="importe"><?= esc(formato_importe($k['interes_en_curso'], false)) ?></td></tr>
            <tr><td>IVA de ese interés</td><td class="importe"><?= esc(formato_importe($k['iva_interes_en_curso'], false)) ?></td></tr>
            <tr class="fw-semibold"><td>Total a cobrar</td><td class="importe"><?= esc(formato_importe($k['total'], false)) ?></td></tr>
            <tr class="text-success"><td>Intereses, gastos e IVA no devengados que no se cobran</td><td class="importe"><?= esc(formato_importe($reduccion, false)) ?></td></tr>
        </tbody>
    </table>
    <p class="small text-body-secondary mt-2 mb-0">Sin comisión por cancelación anticipada (art. 36, Ley 24.240). No se admite la cancelación parcial. El cálculo no genera obligaciones si el cliente desiste (A5).</p>
</section>
<form method="post" action="<?= site_url('admin/creditos/' . (int) $c['id'] . '/cancelacion-anticipada') ?>" class="hoja p-4" novalidate data-confirmar="¿Confirma la cancelación anticipada total del crédito? Todas sus cuotas quedarán pagadas.">
    <?= csrf_field() ?>
    <h2 class="h5">Cobro</h2>
    <div class="row g-3">
        <div class="col-md-3">
            <label for="importe" class="form-label">Importe</label>
            <input type="text" inputmode="decimal" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" value="<?= esc((string) (old('importe') ?? $k['total']->toFixed(2)), 'attr') ?>">
            <?= error_campo('importe') ?>
        </div>
        <div class="col-md-4">
            <label for="medio" class="form-label">Medio de pago</label>
            <select class="form-select" id="medio" name="medio">
                <?php foreach (MediosCobro::MEDIOS as $m => $n): ?><option value="<?= $m ?>"<?= (old('medio') ?? 'Transferencia') === $m ? ' selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-5">
            <label for="numero_operacion" class="form-label">N.º de operación o comprobante</label>
            <input type="text" maxlength="60" class="form-control" id="numero_operacion" name="numero_operacion" value="<?= esc((string) old('numero_operacion'), 'attr') ?>">
        </div>
    </div>
    <?php if (campo_invalido('medios') !== ''): ?><div class="text-danger small mt-2"><?= esc((string) (session('errores') ?? [])['medios']) ?></div><?php endif ?>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="<?= url_to('creditos.cancelar-anticipadamente') ?>?cliente=<?= (int) $c['cliente_id'] ?>" class="btn btn-outline-secondary">El cliente desiste</a>
        <button type="submit" class="btn btn-primary"<?= $jornada === null || $d['ordenes'] !== [] ? ' disabled' : '' ?>>Registrar la cancelación</button>
    </div>
</form>
<?php $this->endSection() ?>
