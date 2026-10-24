<?php
/**
 * CU-05 Registrar Usuario Interno y CU-06 Modificar Datos de Usuario. En la modificación no se cambia el rol (CU-10).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed>|null $u
 * @var list<array{codigo: string, nombre: string}> $roles
 */
use App\Services\Usuarios\UsuarioService;

$nuevo = $u === null;
$v = static fn (string $k): string => (string) old($k, $u[$k] ?? '');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1><?= $nuevo ? 'Registrar usuario' : 'Modificar datos' ?></h1>
        <p><?= $nuevo ? 'El usuario recibe un correo para definir su contraseña y activar la cuenta.' : esc($u['nombre'] . ' ' . $u['apellido']) ?></p>
    </div>
</div>
<form method="post" action="<?= $nuevo ? site_url('admin/usuarios') : site_url('admin/usuarios/' . $u['id'] . '/editar') ?>" class="hoja p-4" style="max-width: 46rem" novalidate>
    <?= csrf_field() ?>
    <?php if (!$nuevo): ?><input type="hidden" name="version_fila" value="<?= (int) $u['version_fila'] ?>"><?php endif ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="80" autocomplete="off" value="<?= esc($v('nombre'), 'attr') ?>">
        </div>
        <div class="col-md-6">
            <label for="apellido" class="form-label">Apellido</label>
            <input type="text" class="form-control" id="apellido" name="apellido" required maxlength="80" autocomplete="off" value="<?= esc($v('apellido'), 'attr') ?>">
        </div>
        <div class="col-md-4">
            <label for="tipo_documento" class="form-label">Tipo de documento</label>
            <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                <?php foreach (UsuarioService::TIPOS_DOCUMENTO as $t): ?>
                    <option value="<?= $t ?>"<?= $v('tipo_documento') === $t ? ' selected' : '' ?>><?= $t ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-8">
            <label for="nro_documento" class="form-label">Número de documento</label>
            <input type="text" class="form-control" id="nro_documento" name="nro_documento" required maxlength="15" inputmode="numeric" autocomplete="off" value="<?= esc($v('nro_documento'), 'attr') ?>" aria-describedby="ayudaDocumento">
            <div class="form-text" id="ayudaDocumento">Sin puntos ni espacios.</div>
        </div>
        <div class="col-12">
            <label for="correo" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" id="correo" name="correo" required maxlength="254" autocomplete="off" value="<?= esc($v('correo'), 'attr') ?>" aria-describedby="ayudaCorreo">
            <div class="form-text" id="ayudaCorreo"><?= $nuevo
                ? 'Se usa para iniciar sesión y para recuperar el acceso.'
                : 'Si lo cambia, enviamos un enlace a la dirección nueva para confirmarla; hasta entonces sigue vigente la actual.' ?></div>
        </div>
        <?php if ($nuevo): ?>
            <div class="col-12">
                <fieldset>
                    <legend class="form-label fs-6">Rol</legend>
                    <div class="row g-2">
                        <?php foreach ($roles as $r): ?>
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="rol" id="rol-<?= esc($r['codigo'], 'attr') ?>" value="<?= esc($r['codigo'], 'attr') ?>"<?= $v('rol') === $r['codigo'] ? ' checked' : '' ?> required>
                                <label class="btn btn-outline-secondary w-100 text-start" for="rol-<?= esc($r['codigo'], 'attr') ?>"><?= esc($r['nombre']) ?></label>
                            </div>
                        <?php endforeach ?>
                    </div>
                </fieldset>
            </div>
        <?php else: ?>
            <div class="col-12">
                <p class="mb-0 text-body-secondary">Rol: <?= esc($u['rol_nombre']) ?>. Se cambia desde "Asignar rol".</p>
            </div>
        <?php endif ?>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-4">
        <a href="<?= $nuevo ? url_to('usuarios.localizar') : site_url('admin/usuarios/' . $u['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $nuevo ? 'Registrar usuario' : 'Guardar cambios' ?></button>
    </div>
</form>
<?php $this->endSection() ?>
