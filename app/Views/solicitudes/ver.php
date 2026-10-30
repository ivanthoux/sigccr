<?php
/**
 * CU-30 Consultar Solicitud de Crédito: datos, plan preliminar, legajo y requisitos, revisiones documentales e
 * historial de estados.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var list<array<string, mixed>> $tipos
 * @var array<string, mixed>|null $evaluacion la evaluación más reciente (CU-24)
 */
use App\Libraries\Evaluacion\DimensionesEvaluacion as D;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\RevisionLegajo;

$id = (int) $s['id'];
$nombres = array_column($tipos, 'nombre', 'codigo');
$docs = array_column($s['documentos'], null, 'id');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('solicitudes.localizar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitudes</a></nav>
<div class="encabezado">
    <div>
        <h1>Solicitud N.º <?= (int) $s['numero'] ?></h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?> · <?= etiqueta_estado_solicitud((string) $s['estado'], $s['tipo_rechazo']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($s['estado'] === 'PendienteVerificacion' && tiene_permiso('solicitudes.verificar-legajo')): ?>
            <a href="<?= site_url("admin/solicitudes/{$id}/verificar") ?>" class="btn btn-primary"><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Verificar legajo</a>
        <?php endif ?>
        <?php if (in_array($s['estado'], ['PendienteSeleccionPlan', 'AprobadaPendienteLiquidacion', 'PendienteResolucion'], true) && tiene_permiso('alternativas.consultar')): ?>
            <a href="<?= site_url("admin/solicitudes/{$id}/alternativas") ?>" class="btn <?= $s['estado'] === 'PendienteSeleccionPlan' ? 'btn-primary' : 'btn-outline-secondary' ?>"><i class="bi bi-list-check me-1" aria-hidden="true"></i>Alternativas de financiación</a>
        <?php endif ?>
        <?php if ($s['estado'] === 'PendienteContingencia' && tiene_permiso('evaluaciones.registrar-contingencia')): ?>
            <a href="<?= site_url("admin/solicitudes/{$id}/contingencia") ?>" class="btn btn-primary"><i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i>Registrar evaluación de contingencia</a>
        <?php endif ?>
        <?php if (in_array($s['estado'], EstadosSolicitud::CORREGIBLES, true) && tiene_permiso('solicitudes.registrar-correccion')): ?>
            <a href="<?= site_url("admin/solicitudes/{$id}/corregir") ?>" class="btn btn-primary"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Registrar corrección</a>
        <?php endif ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-datos">
            <h2 class="h5" id="titulo-datos">Datos de la solicitud</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5 fw-medium">Cliente</dt><dd class="col-sm-7"><?= tiene_permiso('clientes.consultar-perfil') ? '<a href="' . site_url('admin/clientes/' . (int) $s['cliente_id']) . '">' . esc($s['denominacion']) . '</a>' : esc($s['denominacion']) ?></dd>
                <dt class="col-sm-5 fw-medium">CUIT/CUIL</dt><dd class="col-sm-7"><?= esc(formato_cuit($s['cuit_cuil'])) ?></dd>
                <dt class="col-sm-5 fw-medium">Importe</dt><dd class="col-sm-7"><?= esc(formato_importe($s['importe'])) ?></dd>
                <dt class="col-sm-5 fw-medium">Cuotas</dt><dd class="col-sm-7"><?= (int) $s['cantidad_cuotas'] ?></dd>
                <dt class="col-sm-5 fw-medium">Registrada</dt><dd class="col-sm-7"><?= esc(formato_fecha_hora($s['fecha_registro'])) ?><?= $s['analista'] ? ' por ' . esc($s['analista']) : '' ?></dd>
                <dt class="col-sm-5 fw-medium">Último movimiento</dt><dd class="col-sm-7"><?= esc(formato_fecha_hora($s['ultimo_movimiento'])) ?></dd>
            </dl>
            <?php if (($s['aprobacion'] ?? null) !== null): $ap = $s['aprobacion']; ?>
                <div class="alert alert-success mt-3 mb-0"><strong>Aprobada<?= (int) $ap['automatica'] === 1 ? ' automáticamente' : '' ?></strong>: <?= esc(formato_importe($ap['importe_aprobado'])) ?> en <?= (int) $ap['cuotas_aprobadas'] ?> cuotas. La aprobación vence el <?= esc(formato_fecha_hora($ap['vence_aprobacion'])) ?> (72 horas hábiles, RN-49).</div>
            <?php endif ?>
            <?php if ($s['devolucion'] !== null): ?>
                <div class="alert alert-warning mt-3 mb-0"><strong>Devuelta por el Gerente</strong> para retomar <?= esc(mb_strtolower(EstadosSolicitud::etiqueta(EstadosSolicitud::ETAPAS_RETORNO[$s['devolucion']['etapa_retorno']] ?? ''))) ?>: <?= esc((string) $s['devolucion']['fundamento']) ?></div>
            <?php endif ?>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="hoja p-4 h-100" id="legajo" aria-labelledby="titulo-legajo">
            <h2 class="h5" id="titulo-legajo">Documentación exigida por la línea</h2>
            <?php if ($s['exigidos'] === []): ?>
                <p class="mb-0">La versión no exige documentación adicional a la identificación.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($s['exigidos'] as $c): $falta = in_array($c, $s['faltantes'], true); ?>
                        <li class="mb-1"><i class="bi <?= $falta ? 'bi-exclamation-circle text-warning' : 'bi-check-circle text-success' ?> me-1" aria-hidden="true"></i><?= esc($nombres[$c] ?? $c) ?><?= $falta ? ' <span class="small text-body-secondary">(falta en el legajo)</span>' : '' ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>
    <?php if ($evaluacion !== null || $s['estado'] === 'PendienteEvaluacion'): ?>
    <div class="col-12">
        <section class="hoja p-4" aria-labelledby="titulo-evaluacion">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <h2 class="h5" id="titulo-evaluacion">Evaluación crediticia</h2>
                <?php if ($evaluacion !== null && tiene_permiso('evaluaciones.consultar')): ?>
                    <a href="<?= site_url("admin/solicitudes/{$id}/evaluacion") ?>" class="btn btn-sm btn-outline-secondary">Ver detalle de la evaluación</a>
                <?php endif ?>
            </div>
            <?php if ($evaluacion === null): ?>
                <p class="mb-0">Pendiente: el proceso automático la realizará en los próximos minutos.</p>
            <?php else: ?>
                <dl class="dimensiones-evaluacion mb-0">
                    <div><dt>Procesamiento</dt><dd><?= esc(D::etiqueta(D::PROCESAMIENTO, $evaluacion['procesamiento'])) ?></dd></div>
                    <div><dt>Resultado</dt><dd><?= esc(D::etiqueta(D::RESULTADO, $evaluacion['resultado'])) ?><?= $evaluacion['categoria_riesgo'] ? ' · categoría ' . esc($evaluacion['categoria_riesgo']) : '' ?></dd></div>
                    <div><dt>Modalidad</dt><dd><?= esc(D::etiqueta(D::MODALIDAD, $evaluacion['modalidad'])) ?></dd></div>
                    <div><dt>Vigencia</dt><dd><?= esc(D::etiqueta(D::VIGENCIA, $evaluacion['vigencia_efectiva'])) ?><?= $evaluacion['vence'] && $evaluacion['vigencia_efectiva'] === 'Vigente' ? ' hasta ' . esc(formato_fecha($evaluacion['vence'])) : '' ?></dd></div>
                </dl>
                <?php if ($evaluacion['procesamiento'] === 'NoCompletada'): ?><p class="small mt-2 mb-0"><?= esc((string) $evaluacion['motivo_no_completada']) ?></p><?php endif ?>
            <?php endif ?>
        </section>
    </div>
    <?php endif ?>
    <div class="col-12">
        <section class="hoja p-4" aria-labelledby="titulo-revisiones">
            <h2 class="h5" id="titulo-revisiones">Revisiones documentales</h2>
            <?php foreach ($s['revisiones'] as $r): ?>
                <div class="border-start border-3 ps-3 mb-3 <?= $r['estado'] === 'Verificada' ? 'border-success' : ($r['estado'] === 'PendienteRevision' ? 'border-secondary' : 'border-warning') ?>">
                    <p class="mb-1 fw-medium"><?= $r['estado'] === 'PendienteRevision' ? 'Pendiente de revisión' : esc($r['estado']) ?> · <?= esc(formato_fecha_hora($r['fecha'])) ?><?= $r['responsable'] ? ' · ' . esc($r['responsable']) : '' ?></p>
                    <?php if ($r['observaciones']): ?><p class="mb-1 small">Observaciones: <?= esc($r['observaciones']) ?></p><?php endif ?>
                    <?php if ($r['motivo_rechazo']): ?><p class="mb-1 small">Motivo del rechazo documental: <?= esc($r['motivo_rechazo']) ?></p><?php endif ?>
                    <?php if ($r['verificaciones'] !== []): ?>
                        <ul class="small mb-0">
                            <?php foreach ($r['verificaciones'] as $v): $d = $docs[(int) $v['documento_legajo_id']] ?? null; ?>
                                <li><?= esc($d['tipo'] ?? 'Documento reemplazado') ?><?= $d ? ' (' . esc($d['nombre_original']) . ')' : '' ?>: <?= esc($v['resultado']) ?><?= $v['motivo_observacion'] ? ' · ' . esc(RevisionLegajo::MOTIVOS[$v['motivo_observacion']] ?? $v['motivo_observacion']) : '' ?><?= $v['observacion'] ? ' · ' . esc($v['observacion']) : '' ?></li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="hoja p-4" aria-labelledby="titulo-plan">
            <h2 class="h5" id="titulo-plan">Plan preliminar solicitado</h2>
            <?= view('partials/plan_preliminar', ['calculo' => $s['calculo'], 'importe' => (string) $s['importe'], 'tna' => (string) $s['tna']]) ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="hoja p-4" aria-labelledby="titulo-historial">
            <h2 class="h5" id="titulo-historial">Historial de estados</h2>
            <ol class="list-unstyled mb-0 historial-estados">
                <?php foreach ($s['transiciones'] as $t): ?>
                    <li>
                        <span class="fw-medium"><?= esc(EstadosSolicitud::etiqueta((string) $t['estado_destino'])) ?></span>
                        <span class="d-block small text-body-secondary"><?= esc(formato_fecha_hora($t['fecha_hora'])) ?> · <?= esc((string) ($t['responsable'] ?? $t['proceso'] ?? '')) ?></span>
                        <?php if ($t['motivo']): ?><span class="d-block small"><?= esc($t['motivo']) ?></span><?php endif ?>
                    </li>
                <?php endforeach ?>
            </ol>
        </section>
    </div>
</div>
<?php $this->endSection() ?>
