<?php
/**
 * CU-03 Cerrar Sesión, pasos 2 a 4.
 *
 * @var CodeIgniter\View\View $this
 * @var bool $jornadaAbierta
 * @var string $volver
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="hoja">
            <div class="p-4">
                <h1 class="h4">Cerrar sesión</h1>
                <p>¿Confirma que desea cerrar la sesión?</p>
                <?php if ($jornadaAbierta): ?>
                    <div class="alert alert-warning d-flex" role="status">
                        <i class="bi bi-safe me-2 mt-1"></i>
                        <div>Tiene una jornada de caja abierta. La caja permanecerá abierta y asignada a usted.</div>
                    </div>
                <?php endif ?>
                <form method="post" action="<?= site_url('admin/salir') ?>" class="d-flex gap-2 justify-content-end">
                    <?= csrf_field() ?>
                    <a href="<?= esc($volver, 'attr') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection() ?>
