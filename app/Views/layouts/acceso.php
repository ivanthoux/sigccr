<?php
/**
 * Plantilla de las pantallas sin sesión: inicio de sesión y recuperación de acceso.
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
    <title><?= esc(($titulo ?? 'Acceso') . ' · SIG-CCR') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/sigccr/sigccr.css') ?>">
</head>
<body class="acceso d-flex align-items-center min-vh-100 py-4">
<main class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
            <div class="text-center mb-4">
                <i class="bi bi-bank2 display-5 text-primary"></i>
                <h1 class="h4 mt-2 mb-0">SIG-CCR</h1>
                <p class="text-body-secondary small mb-0">Créditos, cobranzas y refinanciación</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <?= $this->include('partials/mensajes') ?>
                    <?= $this->renderSection('contenido') ?>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/sigccr/sigccr.js') ?>"></script>
</body>
</html>
