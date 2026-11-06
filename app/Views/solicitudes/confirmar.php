<?php
/**
 * CU-22 pasos 5 a 8: datos de la solicitud, plan preliminar y documentación que exige la línea.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s simulación
 * @var array<string, mixed> $cliente
 * @var list<string> $exigidos
 * @var list<string> $presentes
 * @var list<array<string, mixed>> $tipos
 */
$faltan = array_diff($exigidos, $presentes);
$nombres = array_column($tipos, 'nombre', 'codigo');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('solicitudes.registrar') ?>?cliente=<?= (int) $s['cliente_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Simulaciones del cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Registrar solicitud</h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?> · <?= esc(formato_importe($s['importe'])) ?> en <?= (int) $s['cantidad_cuotas'] ?> cuotas</p>
    </div>
</div>
<form method="post" action="<?= site_url('admin/solicitudes') ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="simulacion_id" value="<?= (int) $s['id'] ?>">
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-plan">
        <h2 class="h5" id="titulo-plan">Plan preliminar</h2>
        <?= view('partials/plan_preliminar', ['calculo' => $s['calculo'], 'importe' => (string) $s['importe'], 'tna' => (string) $s['tna']]) ?>
    </section>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-docs">
        <h2 class="h5" id="titulo-docs">Documentación de la solicitud</h2>
        <?php if ($exigidos === []): ?>
            <p>La línea no exige documentación adicional a la identificación del cliente.</p>
        <?php elseif ($faltan === []): ?>
            <p><i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i>El legajo ya tiene la documentación que exige la línea: <?= esc(implode(', ', array_map(static fn ($c) => $nombres[$c] ?? $c, $exigidos))) ?>. Puede adjuntar documentación actualizada.</p>
        <?php else: ?>
            <div class="alert alert-warning">Falta en el legajo: <?= esc(implode(', ', array_map(static fn ($c) => $nombres[$c] ?? $c, $faltan))) ?>. Adjúntela para registrar la solicitud (RN-11).</div>
        <?php endif ?>
        <?= view('solicitudes/_documentos', ['tipos' => $tipos, 'tipoCliente' => (string) $cliente['tipo_cliente'], 'exigidos' => $exigidos, 'presentes' => $presentes]) ?>
    </section>
    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= url_to('solicitudes.registrar') ?>?cliente=<?= (int) $s['cliente_id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Registrar solicitud</button>
    </div>
</form>
<?php $this->endSection() ?>
