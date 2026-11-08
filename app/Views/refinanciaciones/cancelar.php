<?php
/**
 * CU-96 Cancelar Propuesta de Refinanciación, pasos 3 a 5 (A1, A2, A4): desistimiento del cliente (Analista) o causa
 * administrativa (Gerente).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $p
 */
$desistimiento = (session('cuenta')['rol'] ?? '') === 'AnalistaCredito';
$this->extend('layouts/panel');
$this->section('contenido');
echo view('refinanciaciones/_resumen', ['p' => $p, 'accion' => 'Cancelar']);
?>
<?php if (!in_array($p['estado'], ['PendienteResolucion', 'AprobadaPendienteAceptacion', 'Observada'], true)): ?>
    <div class="alert alert-warning">La propuesta ya está en un estado final: no puede cancelarse (A1).</div>
<?php else: ?>
    <form method="post" action="<?= site_url('admin/refinanciaciones/' . (int) $p['id'] . '/cancelar') ?>" class="hoja p-4" novalidate data-confirmar="¿Confirma la cancelación de la propuesta?">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $p['version_fila'] ?>">
        <h2 class="h5"><?= $desistimiento ? 'Desistimiento del cliente' : 'Cancelación por causa administrativa' ?></h2>
        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo</label>
            <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="3" maxlength="500"><?= esc((string) old('motivo')) ?></textarea>
            <?= error_campo('motivo') ?>
        </div>
        <?php if ($desistimiento): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="fecha_desistimiento" class="form-label">Fecha de la constancia</label>
                    <input type="date" class="form-control<?= campo_invalido('fecha_desistimiento') ?>" id="fecha_desistimiento" name="fecha_desistimiento" value="<?= esc((string) (old('fecha_desistimiento') ?? date('Y-m-d')), 'attr') ?>">
                    <?= error_campo('fecha_desistimiento') ?>
                </div>
                <div class="col-md-5">
                    <label for="medio_desistimiento" class="form-label">Medio</label>
                    <input type="text" class="form-control<?= campo_invalido('medio_desistimiento') ?>" id="medio_desistimiento" name="medio_desistimiento" maxlength="100" value="<?= esc((string) old('medio_desistimiento'), 'attr') ?>" placeholder="Nota firmada, correo electrónico…">
                    <?= error_campo('medio_desistimiento') ?>
                </div>
            </div>
        <?php endif ?>
        <p class="small text-body-secondary">La propuesta se conserva como antecedente y las obligaciones originales no se modifican (RN-36).</p>
        <div class="d-flex justify-content-end gap-2">
            <a href="<?= site_url('admin/refinanciaciones/' . (int) $p['id']) ?>" class="btn btn-outline-secondary">Volver</a>
            <button type="submit" class="btn btn-danger">Cancelar la propuesta</button>
        </div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
