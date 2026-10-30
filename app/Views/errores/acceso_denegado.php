<?php
/** Rechazo de PermisoFilter (RNF-01): mensaje genérico, sin detalles del permiso. @var CodeIgniter\View\View $this */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="text-center py-5">
    <i class="bi bi-shield-lock display-4 text-secondary"></i>
    <h1 class="h4 mt-3">Acceso no autorizado</h1>
    <p class="text-body-secondary">No tiene permiso para realizar esta operación.</p>
    <a href="<?= url_to('panel.inicio') ?>" class="btn btn-primary">Ir al inicio</a>
</div>
<?php $this->endSection() ?>
