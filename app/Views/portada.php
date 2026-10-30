<?php
/**
 * Portada pública: acceso de clientes al portal de autogestión y acceso del personal al panel.
 *
 * @var CodeIgniter\View\View $this
 * @var array{url: string, texto: string}|null $continuar
 */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<?php if ($continuar !== null): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center gap-3" role="status">
        <span>Ya tiene una sesión iniciada.</span>
        <a href="<?= esc($continuar['url'], 'attr') ?>" class="btn btn-sm btn-primary text-nowrap"><?= esc($continuar['texto']) ?></a>
    </div>
<?php endif ?>
<h1>Portal de clientes</h1>
<p class="ayuda">Consulte su situación con la entidad y realice sus trámites desde el portal de autogestión, sin acercarse a la sucursal.</p>

<a href="<?= url_to('portal.login') ?>" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Ingresar al portal</a>
<a href="<?= url_to('portal.recuperar') ?>" class="d-inline-block small mt-2">¿Olvidó su contraseña?</a>

<div class="portada-activar">
    <h2>¿Es su primera vez?</h2>
    <p>Si se registró como cliente en la sucursal, o la entidad reactivó su ficha, active su acceso con su documento y su correo.</p>
    <a href="<?= url_to('portal.activar') ?>" class="btn btn-outline-secondary w-100">Activar mi acceso</a>
</div>

<p class="portada-personal"><i class="bi bi-building me-1" aria-hidden="true"></i>¿Trabaja en la entidad? <a href="<?= url_to('acceso.login') ?>">Acceso del personal</a></p>
<?php $this->endSection() ?>
