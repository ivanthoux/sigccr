<?php
/**
 * CU-83 pasos 5 a 7: detalle de una alerta e «Ir a la operación».
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $a
 * @var array{texto: string, url: string}|null $operacion
 */
use App\Libraries\Alertas\OperacionAlerta;

$pendiente = $a['estado'] === 'Pendiente';
$tipos = ['Operativa' => 'Operativa', 'Vencimiento' => 'Vencimiento', 'Integracion' => 'Integración', 'Proceso' => 'Proceso automático', 'Seguridad' => 'Seguridad'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('alertas.consultar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Alertas</a></nav>
<div class="hoja p-4" style="max-width: 48rem">
    <h1 class="h4 mb-3"><?= esc((string) $a['causa']) ?></h1>
    <dl class="row mb-3">
        <dt class="col-sm-4">Estado</dt>
        <dd class="col-sm-8"><span class="badge rounded-pill <?= $pendiente ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $pendiente ? 'Pendiente' : 'Resuelta' ?></span></dd>
        <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= esc($tipos[$a['tipo']] ?? $a['tipo']) ?></dd>
        <dt class="col-sm-4">Generada</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora((string) $a['fecha_generacion'])) ?></dd>
        <?php if ($a['objeto_tipo'] !== null): ?>
            <dt class="col-sm-4">Relacionada con</dt><dd class="col-sm-8"><?= esc((OperacionAlerta::OBJETOS[$a['objeto_tipo']] ?? $a['objeto_tipo']) . ' ' . $a['objeto_id']) ?></dd>
        <?php endif ?>
        <?php if ((string) $a['accion_pendiente'] !== ''): ?>
            <dt class="col-sm-4">Acción pendiente</dt><dd class="col-sm-8"><?= esc((string) $a['accion_pendiente']) ?></dd>
        <?php endif ?>
        <?php if (!$pendiente): ?>
            <dt class="col-sm-4">Resuelta</dt>
            <dd class="col-sm-8"><?= esc(formato_fecha_hora((string) $a['fecha_resolucion'])) ?><?= $a['resuelta_por'] !== null ? ' por ' . esc((string) $a['resuelta_por']) : '' ?></dd>
        <?php endif ?>
    </dl>
    <?php if ($operacion !== null): ?>
        <a href="<?= site_url($operacion['url']) ?>" class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i><?= esc($operacion['texto']) ?></a>
    <?php elseif ($pendiente && $a['objeto_tipo'] !== null): ?>
        <p class="small text-body-secondary mb-0">La operación que resuelve esta alerta no está disponible para su rol.</p>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
