<?php
/**
 * CU-43 Consultar Propuesta de Refinanciación desde el portal (A1 a A5): composición, condiciones, plan, estado y
 * resoluciones de una propuesta propia. Solo lectura.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $p
 */
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.propuestas') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Refinanciaciones y ofertas</a></nav>
<div class="encabezado">
    <div>
        <h1>Propuesta de refinanciación N.º <?= (int) $p['numero'] ?><?= (int) $p['version'] > 1 ? ' · versión ' . (int) $p['version'] : '' ?> <?= view('refinanciaciones/_estado', ['estado' => $p['estado']]) ?></h1>
        <p>Registrada el <?= esc(formato_fecha_hora((string) $p['fecha_registro'])) ?> · vigente hasta el <?= esc(formato_fecha_hora((string) $p['vence'])) ?></p>
    </div>
</div>
<?php $mensajes = [
    'Vencida' => 'La propuesta venció el ' . formato_fecha_hora((string) $p['vence']) . ' sin aplicarse: su vencimiento no modificó sus cuotas originales (A1, RN-36).',
    'Observada' => 'La propuesta quedó observada' . ($p['observacion'] !== null ? ': ' . $p['observacion'] : '') . '. Si corresponde, la entidad registrará una nueva versión (A2).',
    'AprobadaPendienteAceptacion' => 'La gerencia aprobó la propuesta. El nuevo plan se aplica recién cuando usted la acepta en la sucursal, dentro de su vigencia (A3).',
    'Aplicada' => 'La refinanciación se aplicó: sus cuotas originales quedaron sustituidas por el nuevo crédito, sin pagarse ni eliminarse (A4, RN-37).',
    'PendienteResolucion' => 'La propuesta está pendiente de la resolución de la gerencia.',
]; ?>
<?php if (isset($mensajes[$p['estado']])): ?><div class="alert alert-info"><?= esc($mensajes[$p['estado']]) ?></div><?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="t-deuda">
    <h2 class="h5" id="t-deuda">Deuda incluida</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
            <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital pendiente</th><th class="text-end">Intereses (no capitalizable)</th><th class="text-end">Punitorios</th></tr></thead>
            <tbody>
            <?php foreach ($p['cuotas'] as $q): ?>
                <tr>
                    <td><?= (int) $q['credito'] ?></td><td><?= (int) $q['numero'] ?></td><td><?= esc(formato_fecha((string) $q['vencimiento'])) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $q['capital_pendiente'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $q['no_capitalizable'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $q['punitorio'], false)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="small mb-0">Capital refinanciado: <strong><?= esc(formato_importe((string) $p['capital_refinanciado'])) ?></strong> · concepto no capitalizable (no genera intereses nuevos): <?= esc(formato_importe((string) $p['concepto_no_capitalizable'])) ?> · TNA <?= esc(formato_porcentaje((string) $p['tasa'])) ?> en <?= (int) $p['cantidad_cuotas'] ?> cuotas. El primer vencimiento es a los 30 días de la aplicación (RN-70).</p>
</section>
<section class="hoja p-4 mb-3" aria-labelledby="t-plan">
    <h2 class="h5" id="t-plan"><?= $p['plan_aplicado'] !== null ? 'Plan aplicado' : 'Nuevo plan si se aplicara hoy' ?></h2>
    <?= $p['plan_aplicado'] !== null ? view('refinanciaciones/_plan', ['filas' => $p['plan_aplicado']]) : view('refinanciaciones/_plan', ['filas' => array_map(static fn ($q) => $q->toArray(), $p['plan']->cuotas)]) ?>
</section>
<?php if ($p['resoluciones'] !== []): ?>
    <section class="hoja p-4" aria-labelledby="t-resoluciones">
        <h2 class="h5" id="t-resoluciones">Resoluciones</h2>
        <ul class="small mb-0"><?php foreach ($p['resoluciones'] as $r): ?><li><?= esc(formato_fecha_hora((string) $r['fecha'])) ?>: <?= esc((string) $r['decision']) ?> — <?= esc((string) $r['fundamento']) ?></li><?php endforeach ?></ul>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
