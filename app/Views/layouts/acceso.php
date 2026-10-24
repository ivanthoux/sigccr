<?php
/**
 * Plantilla de las pantallas sin sesión (inicio de sesión, recuperación de acceso): la marca sobre tinta y el
 * formulario a la derecha; en celulares, la marca arriba.
 *
 * @var CodeIgniter\View\View $this
 * @var string|null $titulo
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(($titulo ?? 'Acceso') . ' | SIG-CCR') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/sigccr/sigccr.css') ?>">
</head>
<body>
<div class="acceso">
<div class="acceso-marca">
    <div>
        <strong>SIG-CCR</strong>
        <p>Solicitudes, cartera, caja y cobranzas de la entidad, en un solo lugar.</p>
    </div>
    <small>Panel de uso interno. El acceso de clientes es por el portal de autogestión.</small>
</div>
<main class="acceso-formulario">
    <div>
        <?= $this->include('partials/mensajes') ?>
        <?= $this->renderSection('contenido') ?>
    </div>
</main>
</div>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/sigccr/sigccr.js') ?>"></script>
</body>
</html>
