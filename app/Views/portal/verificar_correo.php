<?php
/**
 * Verificación del correo de un cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var string $codigo
 * @var bool $valido
 * @var array{0: string, 1: string}|null $resultado
 */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Confirmar correo</h1>
<?php if ($resultado !== null): ?>
    <div class="alert alert-<?= $resultado[0] === 'exito' ? 'success' : 'danger' ?>" role="status"><?= esc($resultado[1]) ?></div>
<?php elseif ($valido): ?>
    <p class="ayuda">Confirme que esta es la dirección de correo que registró en la entidad.</p>
    <form method="post" action="<?= site_url('portal/verificar-correo/' . $codigo) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary w-100">Confirmar correo</button>
    </form>
<?php else: ?>
    <p class="ayuda">El enlace expiró o ya no es válido. Pida en la sucursal que le envíen uno nuevo.</p>
<?php endif ?>
<?php $this->endSection() ?>
