<?php
/**
 * CU-74 Registrar Aceptación y Aplicar Refinanciación, pasos 3 a 6 (A1 a A3, A5): plan definitivo con los punitorios
 * devengados desde la fecha de corte y constancia de la aceptación del cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $p
 * @var array<string, mixed> $definitivo
 */
$d = $definitivo;
$this->extend('layouts/panel');
$this->section('contenido');
echo view('refinanciaciones/_resumen', ['p' => $p, 'accion' => 'Aplicar']);
?>
<?php if ($p['estado'] !== 'AprobadaPendienteAceptacion'): ?>
    <div class="alert alert-warning">Solo se aplica una propuesta aprobada y pendiente de la aceptación del cliente.</div>
<?php elseif ($d['vencida']): ?>
    <div class="alert alert-warning">La propuesta superó su vigencia: al confirmar quedará Vencida y las obligaciones originales seguirán vigentes (A1).</div>
<?php elseif ($d['variaciones'] !== []): ?>
    <div class="alert alert-danger"><strong>La deuda cambió desde la aprobación (A2):</strong>
        <ul class="mb-0"><?php foreach ($d['variaciones'] as $v): ?><li><?= esc($v) ?></li><?php endforeach ?></ul>
        Al confirmar, la propuesta quedará Observada y no se aplicará.</div>
<?php else: ?>
    <?php if ($d['ordenes'] !== []): ?>
        <div class="alert alert-warning">Hay cuotas en la orden de pago electrónico N.º <?= esc(implode(', ', $d['ordenes'])) ?>, todavía vigente: espere su confirmación o vencimiento.</div>
    <?php endif ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Plan definitivo</h2>
        <p>Concepto no capitalizable: <?= esc(formato_importe((string) $p['concepto_no_capitalizable'])) ?> al corte + <strong><?= esc(formato_importe($d['punitorios'])) ?></strong> de punitorios devengados desde entonces = <strong><?= esc(formato_importe($d['no_capitalizable'])) ?></strong> (RN-70). El capital, la tasa y la cantidad de cuotas no cambian.</p>
        <?= view('refinanciaciones/_plan', ['filas' => array_map(static fn ($q) => $q->toArray(), $d['plan']->cuotas)]) ?>
        <p class="mt-2 mb-0">Total a pagar: <strong><?= esc(formato_importe($d['plan']->totalAPagar())) ?></strong> · primer vencimiento a los 30 días corridos de hoy.</p>
    </section>
<?php endif ?>
<?php if ($p['estado'] === 'AprobadaPendienteAceptacion'): ?>
    <form method="post" action="<?= site_url('admin/refinanciaciones/' . (int) $p['id'] . '/aplicar') ?>" class="hoja p-4" novalidate data-confirmar="¿Confirma la aceptación del cliente y la aplicación? Las cuotas originales quedarán sustituidas por el nuevo plan.">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $p['version_fila'] ?>">
        <h2 class="h5">Constancia de aceptación del cliente</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="fecha_constancia" class="form-label">Fecha</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_constancia') ?>" id="fecha_constancia" name="fecha_constancia" value="<?= esc((string) (old('fecha_constancia') ?? hoy_negocio()), 'attr') ?>">
                <?= error_campo('fecha_constancia') ?>
            </div>
            <div class="col-md-4">
                <label for="medio" class="form-label">Medio de aceptación</label>
                <input type="text" class="form-control<?= campo_invalido('medio') ?>" id="medio" name="medio" maxlength="100" value="<?= esc((string) (old('medio') ?? 'Firma presencial'), 'attr') ?>">
                <?= error_campo('medio') ?>
            </div>
            <div class="col-md-5">
                <label for="observaciones" class="form-label">Observaciones (opcional)</label>
                <input type="text" class="form-control<?= campo_invalido('observaciones') ?>" id="observaciones" name="observaciones" maxlength="500" value="<?= esc((string) old('observaciones'), 'attr') ?>">
                <?= error_campo('observaciones') ?>
            </div>
        </div>
        <p class="small text-body-secondary mt-2">Si el cliente no acepta, no registre la constancia: la propuesta sigue aprobada mientras esté vigente (A3).</p>
        <div class="d-flex justify-content-end gap-2">
            <a href="<?= site_url('admin/refinanciaciones/' . (int) $p['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"<?= $d['ordenes'] !== [] ? ' disabled' : '' ?>>Registrar la aceptación y aplicar</button>
        </div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
