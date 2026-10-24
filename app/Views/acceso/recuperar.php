<?php
/** CU-02 Recuperar Acceso, pasos 1 a 3. @var CodeIgniter\View\View $this */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Recuperar acceso</h1>
<p class="ayuda">Le enviaremos un enlace al correo de su cuenta para que defina una contraseña nueva.</p>
<form method="post" action="<?= site_url('admin/recuperar') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="correo" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="correo" name="correo" autocomplete="username" required autofocus maxlength="254" value="<?= esc(old('correo'), 'attr') ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">Enviar instrucciones</button>
</form>
<div class="text-center mt-3"><a href="<?= url_to('acceso.login') ?>" class="small">Volver a iniciar sesión</a></div>
<?php $this->endSection() ?>
