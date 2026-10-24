<?php
/**
 * CU-10 Asignar Rol.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $u
 * @var list<array{codigo: string, nombre: string}> $roles
 */
$id = (int) $u['id'];
$nombre = $u['nombre'] . ' ' . $u['apellido'];
$elegido = (string) old('rol', $u['rol']);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/usuarios/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($nombre) ?></a></nav>
<div class="hoja p-4" style="max-width: 40rem">
    <h1 class="h4">Asignar rol</h1>
    <p><?= esc($nombre) ?> tiene hoy el rol <?= esc($u['rol_nombre']) ?>. Cada usuario tiene un único rol: el nuevo reemplaza al actual y, si tiene una sesión abierta, deberá ingresar nuevamente.</p>
    <form method="post" action="<?= site_url("admin/usuarios/{$id}/rol") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $u['version_fila'] ?>">
        <fieldset class="mb-3">
            <legend class="form-label fs-6">Rol nuevo</legend>
            <div class="row g-2">
                <?php foreach ($roles as $r): ?>
                    <div class="col-sm-6">
                        <input type="radio" class="btn-check" name="rol" id="rol-<?= esc($r['codigo'], 'attr') ?>" value="<?= esc($r['codigo'], 'attr') ?>"<?= $elegido === $r['codigo'] ? ' checked' : '' ?>>
                        <label class="btn btn-outline-secondary w-100 text-start" for="rol-<?= esc($r['codigo'], 'attr') ?>"><?= esc($r['nombre']) ?><?= $r['codigo'] === $u['rol'] ? ' (actual)' : '' ?></label>
                    </div>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="mb-3">
            <label for="motivo" class="form-label">Motivo</label>
            <textarea class="form-control" id="motivo" name="motivo" rows="3" required minlength="5" maxlength="1000"><?= esc(old('motivo')) ?></textarea>
            <div class="form-text">Queda registrado en la auditoría junto con el rol anterior y el nuevo.</div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= site_url("admin/usuarios/{$id}") ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Asignar rol</button>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
