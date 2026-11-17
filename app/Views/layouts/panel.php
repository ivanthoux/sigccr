<?php
/**
 * Plantilla del panel administrativo (RNF-02, RNF-07): barra lateral de tinta con la navegación según los permisos
 * del rol y la cuenta; el contenido a la derecha. Las vistas la usan con $this->extend('layouts/panel') y las
 * secciones 'contenido' y, opcionalmente, 'scripts'.
 *
 * @var CodeIgniter\View\View $this
 * @var string|null $titulo
 */
$cuenta = session('cuenta') ?? [];
$menu = menu_panel();
$actual = current_url();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-nombre" content="<?= esc(csrf_header()) ?>">
    <meta name="csrf-valor" content="<?= esc(csrf_hash()) ?>">
    <title><?= esc(($titulo ?? 'Panel') . ' | SIG-CCR') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css') ?>">
    <link rel="stylesheet" href="<?= recurso('assets/sigccr/sigccr.css') ?>">
</head>
<body class="panel d-lg-flex">
<a class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-white" href="#contenido">Ir al contenido</a>

<header class="barra-movil d-flex d-lg-none align-items-center px-2 py-2">
    <button class="btn btn-link" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral" aria-label="Abrir el menú">
        <i class="bi bi-list fs-3" aria-hidden="true"></i>
    </button>
    <span class="fw-semibold fs-5">SIG-CCR</span>
</header>

<aside class="lateral">
    <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="menuLateral" aria-label="Menú principal">
        <div class="offcanvas-body p-0">
            <a class="marca" href="<?= url_to('panel.inicio') ?>">
                <strong>SIG-CCR</strong>
                <span>Créditos, cobranzas y refinanciación</span>
            </a>
            <nav class="menu-lateral" aria-label="Funciones">
                <a class="menu-opcion<?= url_is('admin') ? ' activa' : '' ?>" href="<?= url_to('panel.inicio') ?>"<?= url_is('admin') ? ' aria-current="page"' : '' ?>>
                    <i class="bi bi-house" aria-hidden="true"></i>Inicio
                </a>
                <?php if (tiene_permiso('alertas.consultar')): $pendientes = alertas_pendientes(); $enAlertas = url_is('admin/alertas*'); ?>
                    <a class="menu-opcion<?= $enAlertas ? ' activa' : '' ?>" href="<?= url_to('alertas.consultar') ?>"<?= $enAlertas ? ' aria-current="page"' : '' ?>>
                        <i class="bi bi-bell" aria-hidden="true"></i>Alertas
                        <?php if ($pendientes > 0): ?><span class="badge rounded-pill text-bg-warning float-end mt-1"><?= $pendientes ?><span class="visually-hidden"> pendientes</span></span><?php endif ?>
                    </a>
                <?php endif ?>
                <?php foreach ($menu as $seccion): ?>
                    <div class="menu-seccion"><?= esc($seccion['titulo']) ?></div>
                    <?php foreach ($seccion['opciones'] as $o): $activa = str_starts_with($actual, $o['url']); ?>
                        <a class="menu-opcion<?= $activa ? ' activa' : '' ?>" href="<?= esc($o['url'], 'attr') ?>"<?= $activa ? ' aria-current="page"' : '' ?>>
                            <i class="bi <?= esc($o['icono'], 'attr') ?>" aria-hidden="true"></i><?= esc($o['texto']) ?>
                        </a>
                    <?php endforeach ?>
                <?php endforeach ?>
            </nav>
            <div class="cuenta">
                <div class="nombre"><?= esc($cuenta['nombre'] ?? '') ?></div>
                <div><?= esc($cuenta['rol_nombre'] ?? '') ?></div>
                <a class="d-inline-block mt-2" href="<?= url_to('acceso.salir') ?>"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar sesión</a>
            </div>
        </div>
    </div>
</aside>

<main id="contenido" class="contenido" tabindex="-1">
    <?= $this->include('partials/mensajes') ?>
    <?= $this->renderSection('contenido') ?>
</main>

<script src="<?= base_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables/dataTables.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= recurso('assets/sigccr/sigccr.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
