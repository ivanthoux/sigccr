<?php
/**
 * Pantalla principal del panel (CU-01 paso 6): saludo, alertas pendientes y las funciones del rol agrupadas por área.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array{titulo: string, icono: string, opciones: list<array{texto: string, url: string}>}> $menu
 * @var int $alertasPendientes
 */
$cuenta = session('cuenta');
$hoy = a_zona_negocio('now');
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha = ucfirst($dias[(int) $hoy->format('w')]) . ' ' . $hoy->format('j') . ' de ' . $meses[(int) $hoy->format('n') - 1];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Hola, <?= esc(strtok($cuenta['nombre'], ' ')) ?></h1>
        <p><?= esc($fecha) ?>. Ingresó como <?= esc(mb_strtolower($cuenta['rol_nombre'])) ?>.</p>
    </div>
    <?php if ($alertasPendientes > 0): ?>
        <span class="badge rounded-pill text-bg-warning fs-6"><i class="bi bi-bell me-1" aria-hidden="true"></i><?= $alertasPendientes ?> alerta<?= $alertasPendientes === 1 ? '' : 's' ?> sin resolver</span>
    <?php endif ?>
</div>

<?php if ($menu === []): ?>
    <div class="hoja p-4">
        <h2 class="h5">Todavía no hay funciones habilitadas para su rol</h2>
        <p class="mb-0 text-body-secondary">Se agregan a medida que se construye cada iteración del sistema y aparecen en el menú de la izquierda.</p>
    </div>
<?php else: ?>
    <div class="hoja indice">
        <?php foreach ($menu as $seccion): ?>
            <section>
                <h2><i class="bi <?= esc($seccion['icono'], 'attr') ?> me-2" aria-hidden="true"></i><?= esc($seccion['titulo']) ?></h2>
                <ul>
                    <?php foreach ($seccion['opciones'] as $o): ?>
                        <li><a href="<?= esc($o['url'], 'attr') ?>"><?= esc($o['texto']) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </section>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
