<?php
/**
 * Pantalla principal del portal (CU-01 paso 6 para el Cliente): saludo, último acceso y las funciones de autogestión.
 * Cada función es un enlace cuando su caso de uso está construido (Config\Menu::$portal); si no, se muestra como próxima.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array{icono: string, titulo: string, texto: string, url: ?string}> $funciones
 * @var string|null $ultimoAcceso fecha y hora del acceso anterior, ya en la zona de la entidad
 */
$cuenta = session('cuenta');
$this->extend('layouts/portal');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Hola, <?= esc($cuenta['nombre']) ?></h1>
        <p><?= $ultimoAcceso === null ? 'Es su primer ingreso al portal.' : 'Su ingreso anterior fue el ' . esc($ultimoAcceso) . '. Si no lo reconoce, cambie su contraseña desde «¿Olvidó su contraseña?».' ?></p>
    </div>
</div>
<h2 class="h5 mb-3">Autogestión</h2>
<ul class="funciones-portal">
    <?php foreach ($funciones as $f): ?>
        <li>
            <i class="bi <?= esc($f['icono'], 'attr') ?>" aria-hidden="true"></i>
            <h3><?php if ($f['url'] !== null): ?><a href="<?= esc($f['url'], 'attr') ?>"><?= esc($f['titulo']) ?></a><?php else: ?><?= esc($f['titulo']) ?><?php endif ?></h3>
            <p><?= esc($f['texto']) ?></p>
            <?php if ($f['url'] === null): ?><span class="badge text-bg-light border mt-2">Próximamente</span><?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
<p class="small text-body-secondary mt-4 mb-0">Por seguridad, la sesión se cierra sola después de <?= (int) (config('Sigccr')->inactividadPortal / 60) ?> minutos sin actividad.</p>
<?php $this->endSection() ?>
