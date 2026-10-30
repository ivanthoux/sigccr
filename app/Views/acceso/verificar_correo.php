<?php
/**
 * CU-06 paso 7: el titular confirma su correo nuevo.
 *
 * @var CodeIgniter\View\View $this
 * @var string $codigo
 * @var bool $valido
 */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Confirmar correo</h1>
<?php if ($valido): ?>
    <p class="ayuda">Confirme que esta es su nueva dirección de correo. Después de confirmarla, inicia sesión con ella.</p>
    <form method="post" action="<?= site_url('admin/verificar-correo/' . $codigo) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary w-100">Confirmar correo</button>
    </form>
<?php else: ?>
    <p class="ayuda">El enlace expiró o ya no es válido. Pida al Administrador que vuelva a registrar el correo nuevo.</p>
    <a href="<?= url_to('acceso.login') ?>" class="btn btn-outline-secondary w-100">Ir a iniciar sesión</a>
<?php endif ?>
<?php $this->endSection() ?>
