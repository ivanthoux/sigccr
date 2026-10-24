<?php
/**
 * Plantilla del panel administrativo (RNF-02, RNF-07): barra superior, menú lateral según los permisos del rol,
 * mensajes y contenido. Las vistas la usan con $this->extend('layouts/panel') y la sección 'contenido'.
 *
 * @var CodeIgniter\View\View $this
 * @var string|null $titulo
 */
$cuenta = session('cuenta') ?? [];
$menu = menu_panel();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-nombre" content="<?= esc(csrf_header()) ?>">
    <meta name="csrf-valor" content="<?= esc(csrf_hash()) ?>">
    <title><?= esc(($titulo ?? 'Panel') . ' · SIG-CCR') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/sigccr/sigccr.css') ?>">
</head>
<body class="panel">
<a class="visually-hidden-focusable" href="#contenido">Ir al contenido</a>
<nav class="navbar navbar-dark barra-sigccr sticky-top shadow-sm">
    <div class="container-fluid">
        <button class="btn btn-link text-white d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral" aria-label="Abrir el menú">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a class="navbar-brand fw-semibold" href="<?= url_to('panel.inicio') ?>"><i class="bi bi-bank2 me-2"></i>SIG-CCR</a>
        <div class="dropdown ms-auto">
            <button class="btn btn-link text-white text-decoration-none dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i><span class="d-none d-sm-inline"><?= esc($cuenta['nombre'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text small text-body-secondary"><?= esc($cuenta['rol_nombre'] ?? '') ?><br><?= esc($cuenta['correo'] ?? '') ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url_to('acceso.salir') ?>"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 p-0 border-end bg-body-tertiary">
            <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="menuLateral" aria-labelledby="menuLateralTitulo">
                <div class="offcanvas-header">
                    <h2 class="offcanvas-title h5" id="menuLateralTitulo">Menú</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Cerrar"></button>
                </div>
                <div class="offcanvas-body p-0">
                    <nav class="menu-lateral w-100 py-3" aria-label="Menú principal">
                        <a class="menu-opcion<?= url_is('admin') ? ' activa' : '' ?>" href="<?= url_to('panel.inicio') ?>"><i class="bi bi-house me-2"></i>Inicio</a>
                        <?php foreach ($menu as $seccion): ?>
                            <div class="menu-seccion"><i class="bi <?= esc($seccion['icono'], 'attr') ?> me-2"></i><?= esc($seccion['titulo']) ?></div>
                            <?php foreach ($seccion['opciones'] as $o): ?>
                                <a class="menu-opcion ps-5<?= str_starts_with(current_url(), $o['url']) ? ' activa' : '' ?>" href="<?= esc($o['url'], 'attr') ?>"><?= esc($o['texto']) ?></a>
                            <?php endforeach ?>
                        <?php endforeach ?>
                    </nav>
                </div>
            </div>
        </aside>
        <main id="contenido" class="col-lg-10 px-3 px-md-4 py-4">
            <?= $this->include('partials/mensajes') ?>
            <?= $this->renderSection('contenido') ?>
        </main>
    </div>
</div>
<script src="<?= base_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables/dataTables.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= base_url('assets/sigccr/sigccr.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
