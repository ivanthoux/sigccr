<?php
/**
 * Confirmación de CU-13 Desactivar Cliente y CU-14 Reactivar Cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 * @var string $operacion desactivar | reactivar
 */
$id = (int) $c['id'];
$desactivar = $operacion === 'desactivar';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/clientes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($c['denominacion']) ?></a></nav>
<div class="hoja p-4" style="max-width: 40rem">
    <h1 class="h4"><?= $desactivar ? 'Desactivar cliente' : 'Reactivar cliente' ?></h1>
    <p class="fw-medium mb-1"><?= esc($c['denominacion']) ?></p>
    <?php if ($desactivar): ?>
        <p>No podrá iniciar nuevas operaciones ni usar el portal: su cuenta de acceso se desactiva, sus sesiones se cierran y queda fuera de las campañas. Su historial se conserva. Solo es posible si no tiene cuotas pendientes, solicitudes o propuestas en curso, saldos a favor, órdenes de pago pendientes ni ofertas vigentes (RN-09).</p>
    <?php else: ?>
        <?php if ($c['desactivacion'] !== null): ?>
            <p class="mb-1">Desactivado el <?= esc(formato_fecha_hora($c['desactivacion']['fecha_hora'])) ?>. Motivo registrado: <?= esc((string) $c['desactivacion']['motivo']) ?></p>
        <?php endif ?>
        <p>Vuelve a quedar activo en el padrón. El acceso al portal no se habilita automáticamente: el cliente debe activarlo o rehabilitarlo (RN-10).</p>
    <?php endif ?>
    <form method="post" action="<?= site_url("admin/clientes/{$id}/{$operacion}") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $c['version_fila'] ?>">
        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo</label>
            <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="3" required minlength="5" maxlength="1000"><?= esc(old('motivo')) ?></textarea>
            <?= error_campo('motivo') ?>
            <div class="form-text">Queda registrado en la auditoría.</div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= site_url("admin/clientes/{$id}") ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn <?= $desactivar ? 'btn-danger' : 'btn-primary' ?>"><?= $desactivar ? 'Desactivar cliente' : 'Reactivar cliente' ?></button>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
