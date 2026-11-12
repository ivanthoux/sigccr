<?php
/**
 * CU-49 pasos 8 a 10 (A5, A6, A8): orden de pago con su enlace, importe, vencimiento e identificación.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $o
 */
$estados = ['PendienteConfirmacion' => 'Pendiente de pago', 'Confirmada' => 'Pagada', 'Rechazada' => 'Rechazada por la pasarela', 'EnConciliacion' => 'En conciliación con la pasarela', 'Vencida' => 'Vencida sin pago'];
$vigente = $o['estado'] === 'PendienteConfirmacion' && new DateTimeImmutable((string) $o['vence']) > new DateTimeImmutable();
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.estado-cuenta') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Estado de cuenta</a></nav>
<div class="encabezado">
    <div>
        <h1>Orden de pago N.º <?= (int) $o['numero'] ?></h1>
        <p><?= esc($estados[$o['estado']] ?? $o['estado']) ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" style="max-width: 44rem">
    <dl class="resumen-plan">
        <div><dt>Importe</dt><dd class="importe"><?= esc(formato_importe((string) $o['importe'])) ?></dd></div>
        <div><dt>Vence</dt><dd><?= esc(formato_fecha_hora((string) $o['vence'])) ?></dd></div>
        <div><dt>Identificación</dt><dd><?= esc((string) ($o['referencia_externa'] ?? $o['numero'])) ?></dd></div>
    </dl>
    <?php if ($vigente && !empty($o['url_pago'])): ?>
        <a href="<?= esc((string) $o['url_pago'], 'attr') ?>" class="btn btn-primary" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Pagar en la pasarela</a>
        <p class="small text-body-secondary mt-2 mb-0">La página de la pasarela muestra el código QR y los medios disponibles. Su cuota sigue pendiente hasta que la pasarela confirme el pago; no genere otra orden por las mismas cuotas.</p>
    <?php elseif ($o['estado'] === 'Vencida' || ($o['estado'] === 'PendienteConfirmacion' && !$vigente)): ?>
        <p class="mb-0">La orden venció sin pago: sus cuotas siguen pendientes. Puede generar una nueva desde «Pagar una cuota».</p>
    <?php elseif ($o['estado'] === 'EnConciliacion'): ?>
        <p class="mb-0">La pasarela informó un estado que estamos verificando. No genere otra orden: le avisaremos el resultado.</p>
    <?php endif ?>
</section>
<section class="hoja p-4" style="max-width: 44rem">
    <h2 class="h5">Cuotas incluidas</h2>
    <ul class="small mb-0">
        <?php foreach ($o['cuotas'] as $q): ?><li>Crédito <?= (int) $q['credito'] ?>, cuota <?= (int) $q['numero'] ?> (vence el <?= esc(formato_fecha((string) $q['vencimiento'])) ?>): <?= esc(formato_importe((string) $q['importe'])) ?></li><?php endforeach ?>
    </ul>
</section>
<?php $this->endSection() ?>
