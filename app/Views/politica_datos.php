<?php
/**
 * Política de tratamiento de datos personales (Ley 25.326, arts. 5 y 6; Res. AAIP 14/2018). Página pública.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array{titulo: string, parrafos: list<string>}> $secciones
 */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Política de datos personales</h1>
<p class="ayuda">Qué datos suyos trata la entidad, para qué, a quién los comunica y cómo puede ejercer sus derechos (Ley 25.326).</p>
<?php foreach ($secciones as $s): ?>
    <section class="mb-3">
        <h2 class="h6"><?= esc($s['titulo']) ?></h2>
        <?php foreach ($s['parrafos'] as $p): ?>
            <p class="small mb-2"><?= esc($p) ?></p>
        <?php endforeach ?>
    </section>
<?php endforeach ?>
<a href="<?= url_to('portada') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
<?php $this->endSection() ?>
