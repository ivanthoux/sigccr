<?php
/**
 * CU-28 Cancelar Solicitud de Crédito, pasos 3 a 5 (A2).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 */
$id = (int) $s['id'];
$aprobada = $s['estado'] === 'AprobadaPendienteLiquidacion';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Cancelar la solicitud N.º <?= (int) $s['numero'] ?></h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?> · <?= etiqueta_estado_solicitud((string) $s['estado']) ?></p>
    </div>
</div>
<form method="post" action="<?= site_url("admin/solicitudes/{$id}/cancelar") ?>" class="hoja p-4" style="max-width: 48rem" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
    <p>La cancelación es definitiva: la solicitud pasa a Cancelada, se liberan sus afectaciones de fondos y ningún proceso pendiente podrá resolverla ni liquidarla (RN-27).</p>
    <?php if ($aprobada): ?>
        <div class="alert alert-warning">
            La solicitud está aprobada: la cancelación deja sin efecto la aprobación y libera la reserva de fondos constituida (RN-41).
            <div class="form-check mt-2">
                <input class="form-check-input<?= campo_invalido('confirma_reserva') ?>" type="checkbox" id="confirma_reserva" name="confirma_reserva" value="1">
                <label class="form-check-label" for="confirma_reserva">Confirmo que se cancela una solicitud aprobada</label>
            </div>
            <?= error_campo('confirma_reserva') ?>
        </div>
    <?php endif ?>
    <div class="mb-3">
        <label for="motivo" class="form-label">Motivo de la cancelación</label>
        <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="3" maxlength="1000" required><?= esc((string) old('motivo')) ?></textarea>
        <?= error_campo('motivo') ?>
    </div>
    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= site_url("admin/solicitudes/{$id}") ?>" class="btn btn-outline-secondary">Volver sin cancelar</a>
        <button type="submit" class="btn btn-danger">Cancelar la solicitud</button>
    </div>
</form>
<?php $this->endSection() ?>
