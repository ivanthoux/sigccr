<?php
/**
 * Plantilla del portal de autogestión con sesión (RNF-02, RNF-07): barra superior de tinta con la marca, el nombre
 * del cliente y el cierre de sesión; el contenido centrado debajo. Las vistas la usan con
 * $this->extend('layouts/portal') y la sección 'contenido'.
 *
 * @var CodeIgniter\View\View $this
 * @var string|null $titulo
 */
$cuenta = session('cuenta') ?? [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-nombre" content="<?= esc(csrf_header()) ?>">
    <meta name="csrf-valor" content="<?= esc(csrf_hash()) ?>">
    <title><?= esc(($titulo ?? 'Portal') . ' | SIG-CCR') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <?= $this->include('partials/tema') ?>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= recurso('assets/sigccr/sigccr.css') ?>">
    <?php // D-57: cabecera y pie de cada hoja impresa (entidad, título, emisión, «Página N de M») ?>
    <?= estilo_impresion($titulo ?? 'SIG-CCR') ?>
</head>
<body class="portal">
<a class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-body" href="#contenido">Ir al contenido</a>
<header class="portal-barra">
    <div class="portal-ancho d-flex align-items-center gap-3">
        <a class="portal-marca me-auto" href="<?= url_to('portal.inicio') ?>">
            <strong>SIG-CCR</strong><span class="d-none d-sm-inline">Portal de clientes</span>
        </a>
        <span class="portal-cuenta d-none d-md-inline"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= esc($cuenta['nombre'] ?? '') ?></span>
        <button type="button" class="cambiar-tema portal-salir" data-cambiar-tema aria-pressed="false"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i>Tema oscuro</button>
        <a class="portal-salir" href="<?= url_to('portal.salir') ?>"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar sesión</a>
    </div>
</header>
<main id="contenido" class="portal-ancho portal-contenido" tabindex="-1">
    <?= $this->include('partials/mensajes') ?>
    <?php // D-57: cabecera formal de la primera hoja impresa; un documento con encabezado propio la omite (.documento-propio) ?>
    <?= cabecera_impresion($titulo ?? 'SIG-CCR') ?>
    <?= $this->renderSection('contenido') ?>
</main>
<footer class="portal-ancho small text-body-secondary py-3"><a href="<?= url_to('politica-datos') ?>">Política de datos personales</a></footer>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= recurso('assets/sigccr/sigccr.js') ?>"></script>
</body>
</html>
