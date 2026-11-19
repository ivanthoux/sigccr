<?php
/**
 * CU-53 Localizar Usuario Interno.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $criterios
 * @var list<array<string, mixed>> $usuarios
 * @var list<array{codigo: string, nombre: string}> $roles
 */
use App\Services\Usuarios\UsuarioService;

$this->extend('layouts/panel');
$this->section('contenido');
$c = static fn (string $k): string => $criterios[$k] ?? '';
$hayCriterios = hay_criterios($criterios);
?>
<div class="encabezado">
    <div>
        <h1>Usuarios</h1>
        <p>Personal de la entidad con acceso al panel.</p>
    </div>
    <?php if (tiene_permiso('usuarios.registrar')): ?>
        <a href="<?= url_to('usuarios.registrar') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Registrar usuario</a>
    <?php endif ?>
</div>

<?= view('partials/boton_filtros', ['id' => 'filtros-usuarios', 'que' => 'usuarios', 'abierto' => $hayCriterios]) ?>
<form method="get" action="<?= url_to('usuarios.localizar') ?>" class="hoja p-3 mb-3 collapse d-md-block<?= $hayCriterios ? ' show' : '' ?>" id="filtros-usuarios" role="search" aria-label="Buscar usuarios">
    <div class="row g-2 align-items-end">
        <div class="col-md-4 col-lg-3">
            <label for="texto" class="form-label small">Nombre o apellido</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($c('texto'), 'attr') ?>">
        </div>
        <div class="col-md-4 col-lg-2">
            <label for="documento" class="form-label small">Documento</label>
            <input type="text" class="form-control" id="documento" name="documento" inputmode="numeric" value="<?= esc($c('documento'), 'attr') ?>">
        </div>
        <div class="col-md-4 col-lg-3">
            <label for="correo" class="form-label small">Correo electrónico</label>
            <input type="text" class="form-control" id="correo" name="correo" value="<?= esc($c('correo'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="rol" class="form-label small">Rol</label>
            <select class="form-select" id="rol" name="rol">
                <option value="">Todos</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= esc($r['codigo'], 'attr') ?>"<?= $c('rol') === $r['codigo'] ? ' selected' : '' ?>><?= esc($r['nombre']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="estado" class="form-label small">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (UsuarioService::ESTADOS as $k => $t): ?>
                    <option value="<?= $k ?>"<?= $c('estado') === $k ? ' selected' : '' ?>><?= esc($t) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <?= acciones_filtro(limpiar: url_to('usuarios.localizar')) ?>
    </div>
</form>

<?php if ($usuarios === []): ?>
    <div class="hoja p-4">
        <p class="mb-0">No hay usuarios que coincidan con la búsqueda. Pruebe con menos criterios o <a href="<?= url_to('usuarios.localizar') ?>">vea todos</a>.</p>
    </div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0" data-tabla data-sin-busqueda>
            <thead>
            <tr><th>Apellido y nombre</th><th>Documento</th><th>Correo electrónico</th><th>Rol</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><a href="<?= site_url('admin/usuarios/' . $u['id']) ?>" class="fw-medium"><?= esc($u['apellido'] . ', ' . $u['nombre']) ?></a></td>
                    <td class="text-nowrap"><?= esc($u['tipo_documento'] . ' ' . $u['nro_documento']) ?></td>
                    <td><?= esc($u['correo']) ?></td>
                    <td><?= esc($u['rol_nombre']) ?></td>
                    <td><?= etiqueta_estado_cuenta($u['estado']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
