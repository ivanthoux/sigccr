<?php
/**
 * CU-75 pasos 6 y 7 (A2): confirmación del cambio desde el correo actual.
 *
 * @var CodeIgniter\View\View $this
 * @var string $codigo
 * @var string|null $nuevo correo nuevo, si el enlace es válido
 * @var array{0: string, 1: string}|null $resultado
 */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Confirmar cambio de correo</h1>
<?php if ($resultado !== null): ?>
    <div class="alert alert-<?= $resultado[0] === 'exito' ? 'success' : 'danger' ?>" role="status"><?= esc($resultado[1]) ?></div>
<?php elseif ($nuevo !== null): ?>
    <p class="ayuda">Usted pidió reemplazar su correo por <strong><?= esc($nuevo) ?></strong>. Si fue usted, confirme para que le enviemos la verificación al correo nuevo.</p>
    <form method="post" action="<?= site_url('portal/cambio-correo/' . $codigo) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary w-100">Confirmar el cambio</button>
    </form>
    <p class="small text-body-secondary mt-3 mb-0">Si no lo pidió, ignore este enlace: su correo no cambia y le conviene cambiar su contraseña.</p>
<?php else: ?>
    <p class="ayuda">El enlace expiró o ya no es válido: el correo no se modificó. Puede volver a pedir el cambio desde el portal.</p>
<?php endif ?>
<div class="text-center mt-3"><a href="<?= url_to('portal.login') ?>" class="small">Ir al portal</a></div>
<?php $this->endSection() ?>
