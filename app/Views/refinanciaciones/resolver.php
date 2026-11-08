<?php
/**
 * CU-41 Resolver Propuesta de Refinanciación, pasos 4 a 8 (A3, A4, A6). La revalidación de la vigencia y de la
 * deuda se hace al confirmar (paso 5).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $p
 */
$decision = (string) (old('decision') ?? '');
$this->extend('layouts/panel');
$this->section('contenido');
echo view('refinanciaciones/_resumen', ['p' => $p, 'accion' => 'Resolver']);
?>
<?php if ($p['estado'] !== 'PendienteResolucion'): ?>
    <div class="alert alert-warning">La propuesta no está pendiente de resolución (A5).</div>
<?php else: ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Nuevo plan de cuotas</h2>
        <?= view('refinanciaciones/_plan', ['filas' => array_map(static fn ($q) => $q->toArray(), $p['plan']->cuotas)]) ?>
        <p class="mt-2 mb-0">Total a pagar estimado: <strong><?= esc(formato_importe($p['plan']->totalAPagar())) ?></strong></p>
    </section>
    <form method="post" action="<?= site_url('admin/refinanciaciones/' . (int) $p['id'] . '/resolver') ?>" class="hoja p-4" novalidate data-confirmar="¿Confirma la resolución de la propuesta?">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $p['version_fila'] ?>">
        <fieldset class="mb-3">
            <legend class="h6">Decisión</legend>
            <?php foreach (['Aprobada' => 'Aprobar', 'Rechazada' => 'Rechazar', 'Observada' => 'Observar'] as $k => $n): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input<?= campo_invalido('decision') ?>" type="radio" name="decision" id="decision-<?= $k ?>" value="<?= $k ?>"<?= $decision === $k ? ' checked' : '' ?>>
                    <label class="form-check-label" for="decision-<?= $k ?>"><?= $n ?></label>
                </div>
            <?php endforeach ?>
            <?= error_campo('decision') ?>
        </fieldset>
        <div class="mb-3">
            <label for="fundamento" class="form-label">Fundamento o motivo</label>
            <textarea class="form-control<?= campo_invalido('fundamento') ?>" id="fundamento" name="fundamento" rows="3" maxlength="1000"><?= esc((string) old('fundamento')) ?></textarea>
            <?= error_campo('fundamento') ?>
            <div class="form-text">La aprobación no extiende la vigencia y no aplica el plan: queda pendiente de la aceptación del cliente (RN-36, RN-37).</div>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <a href="<?= site_url('admin/refinanciaciones/' . (int) $p['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar la resolución</button>
        </div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
