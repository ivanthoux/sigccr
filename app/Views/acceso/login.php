<?php
/**
 * CU-01 Iniciar Sesión, pasos 1 a 3, en el panel administrativo o en el portal de autogestión.
 *
 * @var CodeIgniter\View\View $this
 * @var string|null $base 'admin' (por omisión) o 'portal'
 */
$base = ($base ?? 'admin') === 'portal' ? 'portal' : 'admin';
$correo = (string) (session()->getFlashdata('correo') ?? old('correo') ?? '');
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Iniciar sesión</h1>
<p class="ayuda"><?= $base === 'portal'
    ? 'Ingrese con el correo que registró en la entidad y la contraseña que definió al activar su acceso.'
    : 'Ingrese con el correo y la contraseña de su usuario.' ?></p>
<form method="post" action="<?= site_url($base . '/login') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="correo" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="correo" name="correo" autocomplete="username" required<?= $correo === '' ? ' autofocus' : '' ?>
               maxlength="254" value="<?= esc($correo, 'attr') ?>">
    </div>
    <div class="mb-3">
        <label for="contrasena" class="form-label">Contraseña</label>
        <div class="input-group">
            <input type="password" class="form-control" id="contrasena" name="contrasena" autocomplete="current-password" required maxlength="128"<?= $correo !== '' ? ' autofocus' : '' ?>>
            <button class="btn btn-outline-secondary" type="button" data-mostrar="contrasena" aria-label="Mostrar la contraseña"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Iniciar sesión</button>
</form>
<div class="text-center mt-3">
    <a href="<?= site_url($base . '/recuperar') ?>" class="small">¿Olvidó su contraseña?</a>
</div>
<?php if ($base === 'portal'): ?>
    <div class="border-top mt-4 pt-3 small text-body-secondary">
        ¿Es la primera vez que ingresa, o la entidad reactivó su ficha?
        <a href="<?= url_to('portal.activar') ?>">Activar o rehabilitar acceso</a>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
