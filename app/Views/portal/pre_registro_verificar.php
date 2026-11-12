<?php
/**
 * CU-104 pasos 6 y 7 (A3).
 *
 * @var CodeIgniter\View\View $this
 * @var string $codigo
 * @var array{0: string, 1: string}|null $resultado
 */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Verificar correo</h1>
<?php if ($resultado !== null): ?>
    <div class="alert alert-<?= $resultado[0] === 'exito' ? 'success' : 'danger' ?>" role="status"><?= esc($resultado[1]) ?></div>
    <?php if ($resultado[0] !== 'exito'): ?><div class="text-center"><a href="<?= url_to('portal.pre-registro') ?>">Volver a pre-registrarme</a></div><?php endif ?>
<?php else: ?>
    <p class="ayuda">Confirme que esta dirección de correo es suya para completar su pre-registro.</p>
    <form method="post" action="<?= site_url('portal/pre-registro/verificar/' . $codigo) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary w-100">Verificar mi correo</button>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
