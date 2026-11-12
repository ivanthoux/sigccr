<?php
/**
 * CU-46 Consultar Estado de Solicitudes, paso 4 (A2): estado, documentación, evaluación, alternativa confirmada,
 * aprobación, liquidación y acción pendiente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 */
$verificaciones = ['Conforme' => 'Conforme', 'Observado' => 'Observado', 'Rechazado' => 'Rechazado'];
$evaluacion = $s['evaluacion'];
$resultados = ['Favorable' => 'Favorable', 'Desfavorable' => 'Desfavorable', 'RequiereAnalisisGerencial' => 'Requiere el análisis de la gerencia'];
$procesamientos = ['Pendiente' => 'Pendiente', 'EnProceso' => 'En proceso', 'Finalizada' => 'Finalizada', 'NoCompletada' => 'No completada'];
$revisiones = ['PendienteRevision' => 'pendiente de revisión', 'Observada' => 'observada', 'Verificada' => 'verificada', 'Rechazada' => 'rechazada'];
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.solicitudes') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mis solicitudes</a></nav>
<div class="encabezado">
    <div>
        <h1>Solicitud N.º <?= (int) $s['numero'] ?></h1>
        <p><?= esc($s['linea']) ?> · <?= esc(formato_importe((string) $s['importe'])) ?> en <?= (int) $s['cantidad_cuotas'] ?> cuotas · registrada el <?= esc(formato_fecha((string) $s['fecha_registro'])) ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" aria-labelledby="t-estado">
    <h2 class="h5" id="t-estado">Estado</h2>
    <p class="mb-1"><strong><?= esc($s['etiqueta']) ?></strong> <span class="text-body-secondary small">desde el <?= esc(formato_fecha_hora((string) $s['ultimo_movimiento'])) ?></span></p>
    <?php if ($s['accion'] !== null): ?><div class="alert alert-info mb-0 mt-2"><strong>Qué tiene que hacer:</strong> <?= esc($s['accion']) ?></div><?php endif ?>
    <?php if ($s['condicion_final'] !== null): ?><p class="small mb-0 mt-2"><?= esc($s['condicion_final']) ?><?= $s['motivo_cancelacion'] !== null ? ' Motivo: ' . esc((string) $s['motivo_cancelacion']) : '' ?></p><?php endif ?>
</section>
<section class="hoja p-4 mb-3" aria-labelledby="t-documentacion">
    <h2 class="h5" id="t-documentacion">Documentación</h2>
    <?php if ($s['documentos'] === []): ?>
        <p class="small mb-0">Todavía no hay documentación registrada.</p>
    <?php else: ?>
        <ul class="small mb-2">
            <?php foreach ($s['documentos'] as $d): ?><li><?= esc((string) $d['tipo']) ?> <span class="text-body-secondary">(presentado el <?= esc(formato_fecha((string) $d['fecha_incorporacion'])) ?>)</span></li><?php endforeach ?>
        </ul>
    <?php endif ?>
    <?php if ($s['faltantes'] !== []): ?><p class="small text-danger mb-1">Falta presentar: <?= esc(implode(', ', $s['faltantes_nombres'])) ?>.</p><?php endif ?>
    <?php $r = $s['revisiones'][0] ?? null; ?>
    <p class="small mb-0">Revisión documental: <?= $r === null ? 'pendiente.' : esc($revisiones[$r['estado']] ?? (string) $r['estado']) . ' el ' . esc(formato_fecha((string) $r['fecha'])) . ($r['observaciones'] ? ' — ' . esc((string) $r['observaciones']) : '') ?></p>
    <?php if ($r !== null): foreach ($r['verificaciones'] as $v): if ($v['resultado'] !== 'Conforme'): ?>
        <p class="small mb-0 text-danger">Documento <?= esc($verificaciones[$v['resultado']] ?? $v['resultado']) ?>: <?= esc((string) ($v['motivo_observacion'] ?? $v['observacion'] ?? '')) ?></p>
    <?php endif; endforeach; endif ?>
</section>
<div class="row g-3 mb-3">
    <section class="col-md-6" aria-labelledby="t-evaluacion">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-evaluacion">Evaluación crediticia</h2>
            <?php if ($evaluacion === null): ?>
                <p class="small mb-0">Todavía no se evaluó.</p>
            <?php else: ?>
                <dl class="row small mb-0">
                    <dt class="col-6">Procesamiento</dt><dd class="col-6"><?= esc($procesamientos[$evaluacion['procesamiento']] ?? (string) $evaluacion['procesamiento']) ?></dd>
                    <dt class="col-6">Resultado</dt><dd class="col-6"><?= esc($resultados[$evaluacion['resultado'] ?? ''] ?? (string) ($evaluacion['resultado'] ?? '—')) ?></dd>
                    <dt class="col-6">Modalidad</dt><dd class="col-6"><?= $evaluacion['modalidad'] === 'Automatica' ? 'Automática' : 'Contingencia' ?></dd>
                    <dt class="col-6">Finalizada</dt><dd class="col-6"><?= esc(formato_fecha_hora((string) $evaluacion['fecha_finalizacion'])) ?: '—' ?></dd>
                    <dt class="col-6">Vigente hasta</dt><dd class="col-6"><?= esc(formato_fecha((string) $evaluacion['vence'])) ?: '—' ?></dd>
                </dl>
            <?php endif ?>
        </div>
    </section>
    <section class="col-md-6" aria-labelledby="t-alternativa">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-alternativa">Alternativa y aprobación</h2>
            <?php if ($s['confirmada'] === null): ?>
                <p class="small mb-0">Todavía no confirmó una alternativa de financiación.</p>
            <?php else: $a = $s['confirmada']; ?>
                <dl class="row small mb-0">
                    <dt class="col-6">Importe</dt><dd class="col-6"><?= esc(formato_importe((string) $a['importe'])) ?></dd>
                    <dt class="col-6">Cuotas</dt><dd class="col-6"><?= (int) $a['cantidad_cuotas'] ?> de <?= esc(formato_importe((string) $a['valor_cuota'])) ?></dd>
                    <dt class="col-6">CFTEA</dt><dd class="col-6"><?= esc(formato_porcentaje((string) $a['cftea'])) ?></dd>
                    <dt class="col-6">Conformidad</dt><dd class="col-6"><?= esc(formato_fecha((string) $a['fecha_conformidad'])) ?></dd>
                    <?php if ($s['aprobacion'] !== null): ?>
                        <dt class="col-6">Aprobada el</dt><dd class="col-6"><?= esc(formato_fecha((string) $s['aprobacion']['fecha'])) ?></dd>
                        <dt class="col-6">Aprobación vigente hasta</dt><dd class="col-6"><?= esc(formato_fecha((string) $s['aprobacion']['vence_aprobacion'])) ?></dd>
                    <?php endif ?>
                </dl>
            <?php endif ?>
        </div>
    </section>
</div>
<?php if ($s['liquidacion'] !== null): ?>
    <section class="hoja p-4" aria-labelledby="t-liquidacion">
        <h2 class="h5" id="t-liquidacion">Liquidación</h2>
        <p class="small mb-2">Liquidada el <?= esc(formato_fecha((string) $s['liquidacion']['fecha'])) ?>: se transfirió un neto de <?= esc(formato_importe((string) $s['liquidacion']['importe_neto'])) ?>. Crédito N.º <?= (int) $s['liquidacion']['credito_numero'] ?>.</p>
        <?php if ($s['credito'] !== null): ?><a href="<?= site_url('portal/estado-cuenta/creditos/' . (int) $s['credito']['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver el plan de cuotas</a><?php endif ?>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
