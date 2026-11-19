<?php
/**
 * CU-02 Recuperar Acceso, pasos 6 y 7.
 *
 * @var CodeIgniter\View\View $this
 * @var string $codigo
 * @var string|null $base 'admin' (por omisión) o 'portal'
 */
use App\Libraries\Seguridad\PoliticaContrasena;

$base = ($base ?? 'admin') === 'portal' ? 'portal' : 'admin';
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Nueva contraseña</h1>
<p class="ayuda">Use entre <?= PoliticaContrasena::MINIMO ?> y <?= PoliticaContrasena::MAXIMO ?> caracteres.
    Una frase de varias palabras es fácil de recordar y difícil de adivinar.</p>
<form method="post" action="<?= site_url($base . '/restablecer/' . $codigo) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="contrasena" class="form-label">Nueva contraseña</label>
        <div class="input-group">
            <input type="password" class="form-control" id="contrasena" name="contrasena" autocomplete="new-password" required
                   minlength="<?= PoliticaContrasena::MINIMO ?>" maxlength="<?= PoliticaContrasena::MAXIMO ?>">
            <button class="btn btn-outline-secondary" type="button" data-mostrar="contrasena" aria-label="Mostrar la contraseña"><i class="bi bi-eye"></i></button>
        </div>
        <div class="form-text" data-contador="contrasena"></div>
    </div>
    <div class="mb-3">
        <label for="confirmacion" class="form-label">Repetir la contraseña</label>
        <input type="password" class="form-control" id="confirmacion" name="confirmacion" autocomplete="new-password" required maxlength="<?= PoliticaContrasena::MAXIMO ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
</form>
<?php $this->endSection() ?>
