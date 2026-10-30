<?php
/**
 * Confirmación de CU-07 Restablecer credenciales, CU-08 Desactivar y CU-09 Reactivar.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $u
 * @var string $operacion restablecer | desactivar | reactivar
 */
$id = (int) $u['id'];
$nombre = $u['nombre'] . ' ' . $u['apellido'];
$pendiente = $u['estado'] === 'PendienteActivacion';
[$titulo, $explicacion, $boton, $peligro] = match ($operacion) {
    'restablecer' => [
        $pendiente ? 'Reenviar activación' : 'Restablecer credenciales',
        $pendiente
            ? "Enviaremos a {$u['correo']} un enlace nuevo, válido por 24 horas, para que defina su contraseña y active la cuenta. El enlace anterior deja de valer."
            : "Enviaremos a {$u['correo']} un enlace, válido por 15 minutos, para que defina una contraseña nueva. La actual sigue valiendo hasta que defina la nueva.",
        $pendiente ? 'Reenviar activación' : 'Enviar enlace',
        false,
    ],
    'desactivar' => [
        'Desactivar usuario',
        'No podrá iniciar sesión y sus sesiones abiertas se cerrarán. Su historial se conserva y puede reactivarse más adelante.',
        'Desactivar usuario',
        true,
    ],
    default => [
        'Reactivar usuario',
        $u['desactivado_en'] !== null ? 'Desactivado el ' . formato_fecha_hora($u['desactivado_en']) . '. Vuelve a operar con el rol ' . $u['rol_nombre'] . '.' : 'Vuelve a operar con el rol ' . $u['rol_nombre'] . '.',
        'Reactivar usuario',
        false,
    ],
};
$conMotivo = $operacion !== 'restablecer';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/usuarios/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($nombre) ?></a></nav>
<div class="hoja p-4" style="max-width: 40rem">
    <h1 class="h4"><?= esc($titulo) ?></h1>
    <p class="fw-medium mb-1"><?= esc($nombre) ?> <span class="text-body-secondary fw-normal">(<?= esc($u['rol_nombre']) ?>)</span></p>
    <p><?= esc($explicacion) ?></p>
    <form method="post" action="<?= site_url("admin/usuarios/{$id}/{$operacion}") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $u['version_fila'] ?>">
        <?php if ($conMotivo): ?>
            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo</label>
                <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="3" required minlength="5" maxlength="1000"><?= esc(old('motivo')) ?></textarea>
                <?= error_campo('motivo') ?>
                <div class="form-text">Queda registrado en la auditoría.</div>
            </div>
        <?php endif ?>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= site_url("admin/usuarios/{$id}") ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn <?= $peligro ? 'btn-danger' : 'btn-primary' ?>"><?= esc($boton) ?></button>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
