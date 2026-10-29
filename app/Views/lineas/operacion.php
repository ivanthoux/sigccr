<?php
/**
 * Confirmación de CU-18 Suspender Línea de Crédito y CU-19 Reactivar Línea de Crédito.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $l
 * @var string $operacion suspender | reactivar
 */
$id = (int) $l['id'];
$suspender = $operacion === 'suspender';
$ultimo = $l['cambios_estado'][0] ?? null;
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/lineas/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($l['nombre']) ?></a></nav>
<div class="hoja p-4" style="max-width: 40rem">
    <h1 class="h4"><?= $suspender ? 'Suspender línea' : 'Reactivar línea' ?></h1>
    <p class="fw-medium mb-1"><?= esc($l['nombre']) ?> <code><?= esc($l['codigo']) ?></code></p>
    <?php if ($suspender): ?>
        <p>Desde la confirmación no se podrán generar nuevas simulaciones, ofertas ni solicitudes independientes con esta línea. Las <?= (int) $l['operaciones']['solicitudes_en_curso'] ?> solicitud(es) en curso y las <?= (int) $l['operaciones']['ofertas_vigentes'] ?> oferta(s) vigentes continúan con su versión de condiciones (RN-16, RN-60).</p>
    <?php else: ?>
        <?php if ($ultimo !== null && $ultimo['accion'] === 'lineas.suspender'): ?>
            <p class="mb-1">Suspendida el <?= esc(formato_fecha_hora((string) $ultimo['fecha_hora'])) ?>. Motivo: <?= esc((string) $ultimo['motivo']) ?></p>
        <?php endif ?>
        <p>La línea vuelve a admitir nuevas operaciones con la versión <?= (int) $l['vigente']['numero'] ?> de condiciones, vigente hoy.</p>
        <?php if ($l['pendiente'] !== null): ?>
            <p class="small text-body-secondary">La versión <?= (int) $l['pendiente']['numero'] ?> se aplicará recién desde el <?= esc(formato_fecha_hora((string) $l['pendiente']['vigente_desde'])) ?> (CU-19 A3).</p>
        <?php endif ?>
    <?php endif ?>
    <form method="post" action="<?= site_url("admin/lineas/{$id}/{$operacion}") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $l['version_fila'] ?>">
        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo</label>
            <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="3" required minlength="5" maxlength="1000"><?= esc((string) old('motivo')) ?></textarea>
            <?= error_campo('motivo') ?>
            <div class="form-text">Queda registrado en la auditoría.</div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= site_url("admin/lineas/{$id}") ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn <?= $suspender ? 'btn-danger' : 'btn-primary' ?>"><?= $suspender ? 'Suspender línea' : 'Reactivar línea' ?></button>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
