<?php
/**
 * CU-24 Consultar Evaluación Crediticia: la evaluación más reciente y el historial de intentos, sin consultar al
 * servicio externo.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var list<array<string, mixed>> $evaluaciones
 */
$id = (int) $s['id'];
$actual = $evaluaciones[0] ?? null;
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Evaluación crediticia</h1>
        <p>Solicitud N.º <?= (int) $s['numero'] ?> · <?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?> · <?= etiqueta_estado_solicitud((string) $s['estado'], $s['tipo_rechazo']) ?></p>
    </div>
    <?php if ($s['estado'] === 'PendienteContingencia' && tiene_permiso('evaluaciones.registrar-contingencia')): ?>
        <a href="<?= site_url("admin/solicitudes/{$id}/contingencia") ?>" class="btn btn-primary"><i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i>Registrar evaluación de contingencia</a>
    <?php endif ?>
</div>

<?php if ($actual === null): ?>
    <div class="hoja p-4">
        <p class="mb-0">La solicitud todavía no tiene evaluación crediticia<?= $s['estado'] === 'PendienteEvaluacion' ? ': se realizará en los próximos minutos.' : '.' ?></p>
    </div>
<?php else: ?>
    <?php if ($s['estado'] === 'PendienteContingencia'): ?>
        <div class="alert alert-warning">La evaluación automática no pudo completarse: la solicitud está pendiente de evaluación de contingencia (RN-21).</div>
    <?php endif ?>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-actual">
        <h2 class="h5" id="titulo-actual"><?= $actual['modalidad'] === 'Contingencia' ? 'Evaluación de contingencia' : 'Evaluación automática' ?> más reciente</h2>
        <?= view('evaluaciones/_detalle', ['e' => $actual]) ?>
    </section>
    <?php if (count($evaluaciones) > 1): ?>
        <section class="hoja p-4" aria-labelledby="titulo-anteriores">
            <h2 class="h5" id="titulo-anteriores">Intentos y evaluaciones anteriores</h2>
            <div class="accordion" id="anteriores">
                <?php foreach (array_slice($evaluaciones, 1) as $i => $e): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#anterior-<?= $i ?>" aria-expanded="false" aria-controls="anterior-<?= $i ?>">
                                <?= esc(formato_fecha_hora($e['fecha_finalizacion'] ?? $e['fecha_inicio'])) ?> · <?= $e['modalidad'] === 'Contingencia' ? 'Contingencia' : 'Automática' ?> ·
                                <?= esc(\App\Libraries\Evaluacion\DimensionesEvaluacion::etiqueta(\App\Libraries\Evaluacion\DimensionesEvaluacion::PROCESAMIENTO, $e['procesamiento'])) ?><?= $e['resultado'] ? ', ' . esc(mb_strtolower(\App\Libraries\Evaluacion\DimensionesEvaluacion::RESULTADO[$e['resultado']])) : '' ?>
                            </button>
                        </h3>
                        <div id="anterior-<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#anteriores">
                            <div class="accordion-body"><?= view('evaluaciones/_detalle', ['e' => $e]) ?></div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
