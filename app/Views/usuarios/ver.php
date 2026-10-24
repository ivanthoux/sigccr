<?php
/**
 * CU-53 paso 6: detalle del usuario y operaciones disponibles según su estado y los permisos del rol.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $u
 */
$id = (int) $u['id'];
$desactivado = $u['estado'] === 'Desactivada';
$propio = $id === (int) session('cuenta')['id'];
$operaciones = array_filter([
    !$desactivado && tiene_permiso('usuarios.modificar') ? ['editar', 'Modificar datos', 'bi-pencil'] : null,
    !$desactivado && tiene_permiso('usuarios.asignar-rol') ? ['rol', 'Asignar rol', 'bi-person-badge'] : null,
    !$desactivado && tiene_permiso('usuarios.restablecer-credenciales') ? ['restablecer', $u['estado'] === 'PendienteActivacion' ? 'Reenviar activación' : 'Restablecer credenciales', 'bi-key'] : null,
    !$desactivado && !$propio && tiene_permiso('usuarios.desactivar') ? ['desactivar', 'Desactivar', 'bi-person-x'] : null,
    $desactivado && tiene_permiso('usuarios.reactivar') ? ['reactivar', 'Reactivar', 'bi-person-check'] : null,
]);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('usuarios.localizar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Usuarios</a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc($u['nombre'] . ' ' . $u['apellido']) ?></h1>
        <p><?= esc($u['rol_nombre']) ?> <?= etiqueta_estado_cuenta($u['estado']) ?></p>
    </div>
    <?php if ($operaciones !== []): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($operaciones as [$ruta, $texto, $icono]): ?>
                <a href="<?= site_url("admin/usuarios/{$id}/{$ruta}") ?>" class="btn <?= $ruta === 'desactivar' ? 'btn-outline-danger' : 'btn-outline-secondary' ?>"><i class="bi <?= $icono ?> me-1" aria-hidden="true"></i><?= esc($texto) ?></a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<?php if ($u['estado'] === 'PendienteActivacion'): ?>
    <div class="alert alert-warning" role="status">El usuario todavía no definió su contraseña. Si el enlace venció, use "Reenviar activación".</div>
<?php endif ?>

<div class="hoja p-4" style="max-width: 46rem">
    <dl class="row mb-0">
        <dt class="col-sm-4 fw-medium">Documento</dt>
        <dd class="col-sm-8"><?= esc($u['tipo_documento'] . ' ' . $u['nro_documento']) ?></dd>
        <dt class="col-sm-4 fw-medium">Correo electrónico</dt>
        <dd class="col-sm-8">
            <?= esc($u['correo']) ?>
            <?php if ((int) $u['correo_verificado'] !== 1): ?><span class="badge rounded-pill text-bg-light border ms-1">Sin verificar</span><?php endif ?>
            <?php if ($u['correo_pendiente'] !== null): ?>
                <div class="small text-body-secondary mt-1">Correo pendiente de verificación: <?= esc($u['correo_pendiente']) ?></div>
            <?php endif ?>
        </dd>
        <dt class="col-sm-4 fw-medium">Rol</dt>
        <dd class="col-sm-8"><?= esc($u['rol_nombre']) ?></dd>
        <dt class="col-sm-4 fw-medium">Último acceso</dt>
        <dd class="col-sm-8"><?= $u['ultimo_acceso'] ? esc(formato_fecha_hora($u['ultimo_acceso'])) : 'Nunca ingresó' ?></dd>
        <dt class="col-sm-4 fw-medium">Alta</dt>
        <dd class="col-sm-8"><?= esc(formato_fecha_hora($u['creado_en'])) ?></dd>
        <?php if ($u['desactivado_en'] !== null): ?>
            <dt class="col-sm-4 fw-medium">Desactivado el</dt>
            <dd class="col-sm-8 mb-0"><?= esc(formato_fecha_hora($u['desactivado_en'])) ?></dd>
        <?php endif ?>
    </dl>
</div>
<?php $this->endSection() ?>
