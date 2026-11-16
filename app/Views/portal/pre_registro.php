<?php
/**
 * CU-104 Pre-registrarse en el Portal, pasos 2 y 3 (A4). @var CodeIgniter\View\View $this
 */
use App\Libraries\Validacion\Campos;

$tipo = (string) (old('tipo_documento') ?? 'DNI');
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Pre-registrarse</h1>
<p class="ayuda">Si todavía no es cliente, deje sus datos y verifique su correo. Después acérquese a la sucursal con su documento: un analista verificará su identidad, completará su alta y le enviará el enlace para activar el portal. El pre-registro no otorga crédito ni habilita el portal.</p>
<form method="post" action="<?= url_to('portal.pre-registro') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-2 mb-2">
        <div class="col-sm-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control<?= campo_invalido('nombre') ?>" id="nombre" name="nombre" maxlength="80" autocomplete="given-name" required value="<?= esc((string) old('nombre'), 'attr') ?>">
            <?= error_campo('nombre') ?>
        </div>
        <div class="col-sm-6">
            <label for="apellido" class="form-label">Apellido</label>
            <input type="text" class="form-control<?= campo_invalido('apellido') ?>" id="apellido" name="apellido" maxlength="80" autocomplete="family-name" required value="<?= esc((string) old('apellido'), 'attr') ?>">
            <?= error_campo('apellido') ?>
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-5">
            <label for="tipo_documento" class="form-label">Documento</label>
            <select class="form-select<?= campo_invalido('tipo_documento') ?>" id="tipo_documento" name="tipo_documento">
                <?php foreach (Campos::TIPOS_DOCUMENTO as $t): ?><option value="<?= $t ?>"<?= $tipo === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-7">
            <label for="nro_documento" class="form-label">Número</label>
            <input type="text" class="form-control<?= campo_invalido('nro_documento') ?>" id="nro_documento" name="nro_documento" inputmode="numeric" maxlength="15" required value="<?= esc((string) old('nro_documento'), 'attr') ?>">
            <?= error_campo('nro_documento') ?>
        </div>
    </div>
    <div class="mb-2">
        <label for="cuit_cuil" class="form-label">CUIL</label>
        <input type="text" class="form-control<?= campo_invalido('cuit_cuil') ?>" id="cuit_cuil" name="cuit_cuil" inputmode="numeric" maxlength="13" required placeholder="20-12345678-3" value="<?= esc((string) old('cuit_cuil'), 'attr') ?>">
        <?= error_campo('cuit_cuil') ?>
    </div>
    <div class="mb-2">
        <label for="correo" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control<?= campo_invalido('correo') ?>" id="correo" name="correo" maxlength="254" autocomplete="email" required value="<?= esc((string) old('correo'), 'attr') ?>">
        <?= error_campo('correo') ?>
    </div>
    <div class="mb-3">
        <label for="telefono" class="form-label">Teléfono <span class="text-body-secondary small">(opcional)</span></label>
        <input type="tel" class="form-control<?= campo_invalido('telefono') ?>" id="telefono" name="telefono" maxlength="30" autocomplete="tel" value="<?= esc((string) old('telefono'), 'attr') ?>">
        <?= error_campo('telefono') ?>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input<?= campo_invalido('acepta_politica') ?>" type="checkbox" id="acepta_politica" name="acepta_politica" value="1"<?= old('acepta_politica') !== null ? ' checked' : '' ?>>
        <label class="form-check-label small" for="acepta_politica">Acepto la política de tratamiento de datos personales: la entidad usa estos datos solo para registrarme como cliente y puedo pedir su acceso, rectificación o supresión (Ley 25.326).</label>
        <?= error_campo('acepta_politica') ?>
    </div>
    <button type="submit" class="btn btn-primary w-100">Pre-registrarme</button>
</form>
<div class="text-center mt-3"><a href="<?= url_to('portal.login') ?>" class="small">Ya soy cliente: iniciar sesión</a></div>
<?php $this->endSection() ?>
