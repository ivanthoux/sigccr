<?php
/**
 * CU-04 Activar o Rehabilitar Acceso del Cliente, pasos 7 y 8.
 *
 * @var CodeIgniter\View\View $this
 * @var string $codigo
 */
use App\Libraries\Seguridad\PoliticaContrasena;

$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Defina su contraseña</h1>
<p class="ayuda">Use entre <?= PoliticaContrasena::MINIMO ?> y <?= PoliticaContrasena::MAXIMO ?> caracteres.
    Una frase de varias palabras es fácil de recordar y difícil de adivinar.</p>
<form method="post" action="<?= site_url('portal/activar/' . $codigo) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="contrasena" class="form-label">Nueva contraseña</label>
        <div class="input-group">
            <input type="password" class="form-control" id="contrasena" name="contrasena" autocomplete="new-password" required
                   minlength="<?= PoliticaContrasena::MINIMO ?>" maxlength="<?= PoliticaContrasena::MAXIMO ?>" autofocus>
            <button class="btn btn-outline-secondary" type="button" data-mostrar="contrasena" aria-label="Mostrar la contraseña"><i class="bi bi-eye"></i></button>
        </div>
        <div class="form-text" data-contador="contrasena"></div>
    </div>
    <div class="mb-3">
        <label for="confirmacion" class="form-label">Repetir la contraseña</label>
        <input type="password" class="form-control" id="confirmacion" name="confirmacion" autocomplete="new-password" required maxlength="<?= PoliticaContrasena::MAXIMO ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">Activar mi acceso</button>
</form>
<?php $this->endSection() ?>
