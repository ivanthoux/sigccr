<?php
/**
 * CU-63 pasos 4 y 5 (A2, A3, A5): advertencia y confirmación explícita de la restauración.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
use App\Libraries\Copias\PoliticaCopias;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Restaurar la copia N.º <?= (int) $c['id'] ?></h1>
        <p><?= esc(PoliticaCopias::ORIGENES[$c['origen']] ?? $c['origen']) ?> del <?= esc(formato_fecha_hora((string) $c['fecha_generacion'])) ?><?= $c['descripcion'] !== null ? ' · ' . esc((string) $c['descripcion']) : '' ?></p>
    </div>
</div>
<div class="alert alert-danger" style="max-width: 56rem">
    <div class="fw-semibold mb-1">Se reemplazará la información actual.</div>
    Las operaciones registradas después del <?= esc(formato_fecha_hora((string) $c['fecha_generacion'])) ?> se perderán, salvo en la copia del estado actual que se genera antes de restaurar. Mientras dure la restauración el panel y el portal quedan en mantenimiento, se cierran todas las sesiones (también la suya) y se detienen los procesos programados.
</div>
<form method="post" action="<?= site_url('admin/copias/' . (int) $c['id'] . '/restaurar') ?>" class="hoja p-4" style="max-width: 56rem" novalidate>
    <?= csrf_field() ?>
    <label for="confirmacion" class="form-label">Para confirmar, escriba <strong><?= PoliticaCopias::CONFIRMACION ?></strong></label>
    <input type="text" id="confirmacion" name="confirmacion" autocomplete="off" class="form-control<?= campo_invalido('confirmacion') ?>" style="max-width: 16rem">
    <?= error_campo('confirmacion') ?>
    <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="sin_respaldo" name="sin_respaldo" value="1"<?= old('sin_respaldo') === '1' ? ' checked' : '' ?>>
        <label class="form-check-label" for="sin_respaldo">Restaurar aunque no se pueda generar la copia del estado actual</label>
        <div class="form-text">Sin marcar, si la copia previa falla la restauración no se realiza y se le avisa (A3).</div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="<?= url_to('copias.descargar', (int) $c['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-danger">Restaurar la copia</button>
    </div>
</form>
<?php $this->endSection() ?>
