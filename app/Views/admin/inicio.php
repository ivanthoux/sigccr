<?php
/**
 * Pantalla principal del panel (CU-01 paso 6).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array{titulo: string, icono: string, opciones: list<array{texto: string, url: string}>}> $menu
 * @var int $alertasPendientes
 */
$cuenta = session('cuenta');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="d-flex flex-wrap align-items-baseline justify-content-between mb-4">
    <div>
        <h1 class="h4 mb-1">Hola, <?= esc($cuenta['nombre']) ?></h1>
        <p class="text-body-secondary mb-0"><?= esc($cuenta['rol_nombre']) ?> · <?= esc(formato_fecha(date('Y-m-d H:i:s'))) ?></p>
    </div>
    <?php if ($alertasPendientes > 0): ?>
        <span class="badge text-bg-warning fs-6 mt-2"><i class="bi bi-bell me-1"></i><?= $alertasPendientes ?> alerta<?= $alertasPendientes === 1 ? '' : 's' ?> pendiente<?= $alertasPendientes === 1 ? '' : 's' ?></span>
    <?php endif ?>
</div>
<?php if ($menu === []): ?>
    <div class="alert alert-info" role="status">
        <i class="bi bi-info-circle me-2"></i>Las funciones de su rol se habilitan a medida que se construye cada iteración del sistema.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($menu as $seccion): ?>
            <div class="col-sm-6 col-xl-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 card-title"><i class="bi <?= esc($seccion['icono'], 'attr') ?> me-2 text-primary"></i><?= esc($seccion['titulo']) ?></h2>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($seccion['opciones'] as $o): ?>
                                <li><a href="<?= esc($o['url'], 'attr') ?>"><?= esc($o['texto']) ?></a></li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
