<?php
/**
 * CU-75 Cambiar Correo Electrónico por Autogestión, pasos 1 a 3 (A4, A5). El cambio exige confirmar desde el correo
 * actual y verificar el nuevo; hasta entonces el correo no cambia (RN-58).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.perfil') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mi perfil</a></nav>
<div class="encabezado">
    <div>
        <h1>Cambiar correo electrónico</h1>
        <p>Correo actual: <strong><?= esc((string) ($c['correo_cuenta'] ?? $c['correo'])) ?></strong></p>
    </div>
</div>
<section class="hoja p-4" style="max-width: 36rem">
    <ol class="small ps-3">
        <li>Ingrese el correo nuevo.</li>
        <li>Le enviaremos un enlace a su <strong>correo actual</strong> para confirmar el cambio (30 minutos).</li>
        <li>Después le enviaremos otro al <strong>correo nuevo</strong> para verificarlo (30 minutos). Recién entonces se usa para ingresar al portal.</li>
    </ol>
    <form method="post" action="<?= url_to('portal.cambiar-correo') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="correo_nuevo" class="form-label">Correo nuevo</label>
            <input type="email" class="form-control<?= campo_invalido('correo_nuevo') ?>" id="correo_nuevo" name="correo_nuevo" autocomplete="email" maxlength="254" required value="<?= esc((string) old('correo_nuevo'), 'attr') ?>">
            <?= error_campo('correo_nuevo') ?>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <a href="<?= url_to('portal.perfil') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Enviar la confirmación</button>
        </div>
    </form>
    <p class="small text-body-secondary mt-3 mb-0">¿Perdió el acceso a su correo actual? El cambio se hace en la sucursal: preséntese con su documento de identidad.</p>
</section>
<?php $this->endSection() ?>
