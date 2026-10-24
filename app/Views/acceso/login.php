<?php
/** CU-01 Iniciar Sesión (panel administrativo). @var CodeIgniter\View\View $this */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Iniciar sesión</h1>
<p class="ayuda">Ingrese con el correo y la contraseña de su usuario.</p>
<form method="post" action="<?= site_url('admin/login') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="correo" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="correo" name="correo" autocomplete="username" required autofocus
               maxlength="254" value="<?= esc(session()->getFlashdata('correo') ?? old('correo'), 'attr') ?>">
    </div>
    <div class="mb-3">
        <label for="contrasena" class="form-label">Contraseña</label>
        <div class="input-group">
            <input type="password" class="form-control" id="contrasena" name="contrasena" autocomplete="current-password" required maxlength="128">
            <button class="btn btn-outline-secondary" type="button" data-mostrar="contrasena" aria-label="Mostrar la contraseña"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Iniciar sesión</button>
</form>
<div class="text-center mt-3">
    <a href="<?= url_to('acceso.recuperar') ?>" class="small">¿Olvidó su contraseña?</a>
</div>
<?php $this->endSection() ?>
