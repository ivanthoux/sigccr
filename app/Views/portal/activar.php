<?php
/** CU-04 Activar o Rehabilitar Acceso del Cliente, pasos 1 a 3. @var CodeIgniter\View\View $this */
use App\Libraries\Validacion\Campos;

$tipo = (string) (old('tipo_documento') ?? 'DNI');
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Activar o rehabilitar acceso</h1>
<p class="ayuda">Para usar el portal de autogestión por primera vez, o volver a usarlo después de que la entidad reactivó su ficha, ingrese su documento y el correo que registró en la sucursal. Le enviaremos un enlace para definir su contraseña.</p>
<form method="post" action="<?= url_to('portal.activar') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-2 mb-3">
        <div class="col-5">
            <label for="tipo_documento" class="form-label">Tipo</label>
            <select class="form-select" id="tipo_documento" name="tipo_documento">
                <?php foreach ([...Campos::TIPOS_DOCUMENTO, 'CUIT'] as $t): ?>
                    <option value="<?= $t ?>"<?= $tipo === $t ? ' selected' : '' ?>><?= $t === 'CUIT' ? 'CUIT (empresas)' : $t ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-7">
            <label for="numero" class="form-label">Número</label>
            <input type="text" class="form-control" id="numero" name="numero" inputmode="numeric" required maxlength="15" autofocus value="<?= esc((string) old('numero'), 'attr') ?>">
        </div>
    </div>
    <div class="mb-3">
        <label for="correo" class="form-label">Correo electrónico registrado</label>
        <input type="email" class="form-control" id="correo" name="correo" autocomplete="email" required maxlength="254" value="<?= esc((string) old('correo'), 'attr') ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">Enviar instrucciones</button>
</form>
<div class="text-center mt-3"><a href="<?= url_to('portal.login') ?>" class="small">Ya tengo acceso: iniciar sesión</a></div>
<p class="small text-body-secondary mt-3 mb-0">Si ya activó su acceso y no recuerda la contraseña, use «¿Olvidó su contraseña?» en el inicio de sesión del portal.</p>
<?php $this->endSection() ?>
