<?php
/**
 * CU-37 Anular Cobro, pasos 3 a 7 (A1, A2, A4): detalle del cobro, operaciones posteriores dependientes y motivo.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c comprobante del cobro
 * @var list<array<string, mixed>> $dependientes cobros posteriores sobre las mismas cuotas
 */
use App\Libraries\Caja\ReglasCaja;

$medios = ReglasCaja::MEDIOS_VENTANILLA + ['Efectivo' => 'Efectivo', 'PagoElectronico' => 'Pago electrónico'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('cobros.anular') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Anular cobros</a></nav>
<div class="encabezado">
    <div>
        <h1>Cobro N.º <?= (int) $c['numero'] ?></h1>
        <p><?= esc($c['denominacion']) ?> (<?= esc(formato_cuit($c['cuit_cuil'])) ?>) · <?= esc(formato_fecha_hora($c['fecha_hora'])) ?> · comprobante N.º <?= (int) $c['comprobante'] ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-detalle">
    <h2 class="h5" id="titulo-detalle">Detalle del cobro</h2>
    <dl class="resumen-plan mb-2">
        <div><dt>Importe</dt><dd class="importe fw-semibold"><?= esc(formato_importe($c['importe'])) ?></dd></div>
        <div><dt>Canal</dt><dd><?= $c['canal'] === 'Ventanilla' ? 'Ventanilla' : 'Pago electrónico' ?></dd></div>
        <div><dt>Cajero</dt><dd><?= esc((string) ($c['cajero'] ?? '—')) ?></dd></div>
        <div><dt>Medios</dt><dd><?= esc(implode(' · ', array_map(static fn ($m) => ($medios[$m['medio']] ?? $m['medio']) . ' ' . formato_importe($m['importe']), $c['medios']))) ?></dd></div>
    </dl>
    <table class="table table-sm mb-0" style="max-width: 44rem">
        <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Imputado</th><th>Situación actual</th></tr></thead>
        <tbody>
        <?php foreach ($c['cuotas'] as $q): ?>
            <tr><td><?= (int) $q['credito'] ?></td><td><?= (int) $q['cuota'] ?></td><td><?= esc(formato_fecha($q['vencimiento'])) ?></td><td class="importe"><?= esc(formato_importe($q['total'], false)) ?></td><td class="small"><?= esc($q['estado']) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
</section>
<?php if ($c['estado'] === 'Anulado'): ?>
    <div class="alert alert-secondary">El cobro fue anulado el <?= esc(formato_fecha_hora($c['fecha_anulacion'])) ?>: <?= esc((string) $c['motivo_anulacion']) ?>. No se generan nuevos ajustes (A1).</div>
<?php else: ?>
    <form method="post" action="<?= site_url('admin/cobros/' . (int) $c['id'] . '/anular') ?>" class="hoja p-4" style="max-width: 48rem" novalidate data-confirmar="¿Confirma la anulación del cobro? Las cuotas recuperan su saldo y el importe queda como saldo a favor del cliente.">
        <?= csrf_field() ?>
        <h2 class="h5">Anulación</h2>
        <?php if ($dependientes !== []): ?>
            <div class="alert alert-warning<?= campo_invalido('dependientes') !== '' ? ' border-danger' : '' ?>">
                <p class="mb-2"><strong>Operaciones posteriores dependientes (A2):</strong> estos cobros alcanzaron las mismas cuotas después de este y deben revisarse.</p>
                <ul class="mb-2"><?php foreach ($dependientes as $d): ?><li>Cobro N.º <?= (int) $d['numero'] ?> del <?= esc(formato_fecha_hora($d['fecha_hora'])) ?> por <?= esc(formato_importe($d['importe'])) ?></li><?php endforeach ?></ul>
                <div class="form-check">
                    <input class="form-check-input<?= campo_invalido('dependientes') ?>" type="checkbox" id="continuar" name="continuar" value="1"<?= old('continuar') ? ' checked' : '' ?>>
                    <label class="form-check-label" for="continuar">Continúo con la anulación y dejo esos cobros identificados para su revisión (RN-33).</label>
                    <?= error_campo('dependientes') ?>
                </div>
            </div>
        <?php endif ?>
        <p class="small">La anulación conserva el cobro como antecedente, devuelve a las cuotas lo imputado y registra el importe recibido como saldo a favor del cliente; no entrega dinero (RN-33, RN-57).</p>
        <label for="motivo" class="form-label">Motivo de la anulación</label>
        <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="3" maxlength="500" required><?= esc((string) old('motivo')) ?></textarea>
        <?= error_campo('motivo') ?>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="<?= url_to('cobros.anular') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-danger">Anular el cobro</button>
        </div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
