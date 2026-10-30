<?php
/**
 * CU-03 Cerrar Sesión del Cliente, pasos 3 y 4 (el cliente no tiene jornada de caja).
 *
 * @var CodeIgniter\View\View $this
 * @var string $volver
 */
$this->extend('layouts/portal');
$this->section('contenido');
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="hoja p-4">
            <h1 class="h4">Cerrar sesión</h1>
            <p>¿Confirma que desea cerrar la sesión?</p>
            <form method="post" action="<?= url_to('portal.salir') ?>" class="d-flex gap-2 justify-content-end">
                <?= csrf_field() ?>
                <a href="<?= esc($volver, 'attr') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
<?php $this->endSection() ?>
