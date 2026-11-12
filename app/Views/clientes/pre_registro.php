<?php
/**
 * Detalle de un pre-registro (CU-104 paso 7): completar el alta con CU-11 o descartarlo con su motivo.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $p
 */
$estados = ['PendienteValidacion' => 'Pendiente de validación', 'PendienteVerificacion' => 'Sin verificar el correo', 'Completado' => 'Completado', 'Descartado' => 'Descartado'];
$pendiente = in_array($p['estado'], ['PendienteValidacion', 'PendienteVerificacion'], true);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('pre-registros.consultar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Pre-registros del portal</a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc($p['apellido'] . ', ' . $p['nombre']) ?></h1>
        <p>Pre-registro N.º <?= (int) $p['id'] ?> · <?= esc($estados[$p['estado']] ?? $p['estado']) ?></p>
    </div>
    <?php if ($p['estado'] === 'PendienteValidacion' && tiene_permiso('clientes.registrar')): ?>
        <a href="<?= url_to('clientes.registrar') ?>?pre=<?= (int) $p['id'] ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Completar el alta</a>
    <?php endif ?>
</div>
<section class="hoja p-4 mb-3" style="max-width: 48rem">
    <dl class="row mb-0">
        <dt class="col-sm-4">Documento</dt><dd class="col-sm-8"><?= esc($p['tipo_documento'] . ' ' . $p['nro_documento']) ?></dd>
        <dt class="col-sm-4">CUIL</dt><dd class="col-sm-8"><?= esc(formato_cuit($p['cuit_cuil'])) ?></dd>
        <dt class="col-sm-4">Correo</dt><dd class="col-sm-8"><?= esc($p['correo']) ?> <?= $p['fecha_verificacion'] !== null ? '<span class="badge text-bg-success">Verificado</span>' : '<span class="badge text-bg-warning">Sin verificar</span>' ?></dd>
        <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8"><?= esc((string) ($p['telefono'] ?? '—')) ?></dd>
        <dt class="col-sm-4">Registrado</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora((string) $p['creado_en'])) ?></dd>
        <dt class="col-sm-4">Política de datos aceptada</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora((string) $p['fecha_politica'])) ?></dd>
        <?php if ($p['cliente_id'] !== null): ?><dt class="col-sm-4">Cliente</dt><dd class="col-sm-8"><a href="<?= site_url('admin/clientes/' . (int) $p['cliente_id']) ?>"><?= esc((string) $p['cliente']) ?></a> (alta de <?= esc((string) $p['analista']) ?>)</dd><?php endif ?>
        <?php if ($p['estado'] === 'Descartado'): ?><dt class="col-sm-4">Motivo del descarte</dt><dd class="col-sm-8"><?= esc((string) $p['motivo_descarte']) ?> (<?= esc((string) $p['analista']) ?>)</dd><?php endif ?>
    </dl>
</section>
<?php if ($p['estado'] === 'PendienteValidacion'): ?>
    <p class="small text-body-secondary" style="max-width: 48rem">Completar el alta abre el formulario de registro con estos datos. Verifique la identidad con el documento original, adjunte su copia y complete los datos laborales, económicos y el domicilio.</p>
<?php endif ?>
<?php if ($pendiente): ?>
    <form method="post" action="<?= site_url('admin/pre-registros/' . (int) $p['id'] . '/descartar') ?>" class="hoja p-4" style="max-width: 48rem" novalidate data-confirmar="¿Descarta este pre-registro? La persona deberá registrarse de nuevo.">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $p['version_fila'] ?>">
        <h2 class="h6">Descartar el pre-registro</h2>
        <label for="motivo" class="form-label small">Motivo</label>
        <textarea id="motivo" name="motivo" rows="2" maxlength="500" class="form-control<?= campo_invalido('motivo') ?>" placeholder="Por ejemplo: la persona no se presentó a verificar su identidad."><?= esc((string) old('motivo')) ?></textarea>
        <?= error_campo('motivo') ?>
        <div class="text-end mt-2"><button type="submit" class="btn btn-outline-danger btn-sm">Descartar</button></div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
