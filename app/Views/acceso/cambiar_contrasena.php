<?php
/**
 * CU-107 Cambiar Contraseña, pasos 1 y 2. Panel y portal usan la misma pantalla.
 *
 * @var CodeIgniter\View\View $this
 * @var string $base 'admin' o 'portal'
 */
use App\Libraries\Seguridad\PoliticaContrasena;

$portal = ($base ?? 'admin') === 'portal';
$this->extend($portal ? 'layouts/portal' : 'layouts/panel');
$this->section('contenido');
$accion = $portal ? url_to('portal.cambiar-contrasena') : url_to('panel.cambiar-contrasena');
$volver = $portal ? url_to('portal.perfil') : url_to('panel.inicio');
?>
<div class="encabezado">
    <div>
        <h1>Cambiar contraseña</h1>
        <p>Use entre <?= PoliticaContrasena::MINIMO ?> y <?= PoliticaContrasena::MAXIMO ?> caracteres. Una frase de varias palabras es fácil de recordar y difícil de adivinar.
            Al cambiarla se cierran las demás sesiones abiertas con su cuenta.</p>
    </div>
</div>
<section class="hoja p-4" style="max-width: 36rem">
    <form method="post" action="<?= $accion ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="actual" class="form-label">Contraseña actual</label>
            <input type="password" class="form-control" id="actual" name="actual" autocomplete="current-password" required maxlength="<?= PoliticaContrasena::MAXIMO ?>">
        </div>
        <div class="mb-3">
            <label for="contrasena" class="form-label">Contraseña nueva</label>
            <div class="input-group">
                <input type="password" class="form-control" id="contrasena" name="contrasena" autocomplete="new-password" required
                       minlength="<?= PoliticaContrasena::MINIMO ?>" maxlength="<?= PoliticaContrasena::MAXIMO ?>">
                <button class="btn btn-outline-secondary" type="button" data-mostrar="contrasena" aria-label="Mostrar la contraseña"><i class="bi bi-eye"></i></button>
            </div>
            <div class="form-text" data-contador="contrasena"></div>
        </div>
        <div class="mb-3">
            <label for="confirmacion" class="form-label">Repetir la contraseña nueva</label>
            <input type="password" class="form-control" id="confirmacion" name="confirmacion" autocomplete="new-password" required maxlength="<?= PoliticaContrasena::MAXIMO ?>">
        </div>
        <div class="d-flex justify-content-end gap-2">
            <a href="<?= $volver ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Cambiar la contraseña</button>
        </div>
    </form>
</section>
<?php $this->endSection() ?>
