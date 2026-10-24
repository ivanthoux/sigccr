<?php
// Mensajes de la operación anterior (flashdata): exito, aviso, error
$tipos = ['exito' => ['success', 'bi-check-circle'], 'aviso' => ['warning', 'bi-exclamation-triangle'], 'error' => ['danger', 'bi-x-circle']];
foreach ($tipos as $clave => [$clase, $icono]):
    $texto = session()->getFlashdata($clave);
    if ($texto === null || $texto === '') {
        continue;
    }
    ?>
    <div class="alert alert-<?= $clase ?> alert-dismissible d-flex align-items-start" role="<?= $clave === 'error' ? 'alert' : 'status' ?>">
        <i class="bi <?= $icono ?> me-2 mt-1"></i>
        <div><?= esc($texto) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach ?>
