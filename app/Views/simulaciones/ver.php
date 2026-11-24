<?php
/**
 * CU-21 paso 10: simulación registrada con su plan preliminar.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 */
$vigente = (int) $s['vigente'] === 1;
$exceso = $s['exceso'] ?? null;
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('solicitudes.simular') ?>?cliente=<?= (int) $s['cliente_id'] ?>&amp;linea=<?= (int) $s['linea_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Nueva simulación</a></nav>
<div class="encabezado">
    <div>
        <h1>Simulación de <?= esc($s['denominacion']) ?></h1>
        <p><?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?> · generada el <?= esc(formato_fecha_hora($s['fecha_generacion'])) ?> ·
            <?= $vigente ? 'vigente hasta el ' . esc(formato_fecha_hora($s['vence'])) : '<span class="badge text-bg-secondary">Vencida</span>' ?></p>
    </div>
    <?php if ($vigente && $exceso === null && tiene_permiso('solicitudes.registrar')): ?>
        <a href="<?= site_url('admin/solicitudes/nueva/' . (int) $s['id']) ?>" class="btn btn-primary"><i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>Registrar solicitud</a>
    <?php endif ?>
</div>
<?php if ($vigente && $exceso !== null): ?>
    <div class="alert alert-warning" role="status"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc($exceso['mensaje']) ?>
        La simulación queda como referencia, pero no puede formalizarse como solicitud.</div>
<?php endif ?>
<div class="hoja p-4">
    <p class="small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Cálculo preliminar: no genera solicitud, aprobación ni obligación de pago (RN-17).</p>
    <?= view('partials/plan_preliminar', ['calculo' => $s['calculo'], 'importe' => (string) $s['importe'], 'tna' => (string) $s['tna']]) ?>
</div>
<?php $this->endSection() ?>
