<?php
/**
 * Detalle de una evaluación crediticia (CU-24 pasos 5 y 6): dimensiones, indicadores de RN-69, datos utilizados,
 * antecedentes externos y, en la contingencia, la recomendación del Analista.
 *
 * @var array<string, mixed> $e evaluación de EvaluacionService::deSolicitud()
 */
use App\Libraries\Evaluacion\Contingencia;
use App\Libraries\Evaluacion\DimensionesEvaluacion as D;

$ind = (array) ($e['indicadores'] ?? []);
$internos = $ind['internos'] ?? null;
$bcra = $ind['bcra'] ?? null;
$periodo = static fn (?string $p): string => $p === null || strlen($p) !== 6 ? '—' : substr($p, 4, 2) . '/' . substr($p, 0, 4);
$vigencia = $e['vigencia_efectiva'];
?>
<dl class="dimensiones-evaluacion">
    <div><dt>Procesamiento</dt><dd><?= esc(D::etiqueta(D::PROCESAMIENTO, $e['procesamiento'])) ?></dd></div>
    <div><dt>Resultado</dt><dd><?= esc(D::etiqueta(D::RESULTADO, $e['resultado'])) ?></dd></div>
    <div><dt>Modalidad</dt><dd><?= esc(D::etiqueta(D::MODALIDAD, $e['modalidad'])) ?></dd></div>
    <div><dt>Vigencia</dt><dd><?= esc(D::etiqueta(D::VIGENCIA, $vigencia)) ?></dd></div>
</dl>
<dl class="row small mb-3">
    <dt class="col-sm-4 fw-medium">Inicio</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora($e['fecha_inicio'])) ?></dd>
    <?php if ($e['fecha_finalizacion']): ?><dt class="col-sm-4 fw-medium"><?= $e['procesamiento'] === 'Finalizada' ? 'Finalización' : 'Fin del intento' ?></dt><dd class="col-sm-8"><?= esc(formato_fecha_hora($e['fecha_finalizacion'])) ?></dd><?php endif ?>
    <?php if ($e['vence']): ?><dt class="col-sm-4 fw-medium">Vencimiento</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora($e['vence'])) ?></dd><?php endif ?>
    <dt class="col-sm-4 fw-medium">Parámetros</dt><dd class="col-sm-8">Versión <?= (int) $e['version_parametros'] ?> de los parámetros de evaluación</dd>
</dl>

<?php if (in_array($e['procesamiento'], ['Pendiente', 'EnProceso'], true)): ?>
    <div class="alert alert-info mb-0">La evaluación está en curso: el resultado y la vigencia se informan cuando finalice.</div>
<?php elseif ($e['procesamiento'] === 'NoCompletada'): ?>
    <div class="alert alert-warning">No se pudo completar: <?= esc((string) $e['motivo_no_completada']) ?></div>
<?php endif ?>
<?php if ($vigencia === 'Vencida'): ?>
    <div class="alert alert-secondary">La evaluación venció el <?= esc(formato_fecha($e['vence'])) ?>: para continuar, la solicitud requiere una nueva evaluación (RN-20).</div>
<?php elseif ($vigencia === 'Invalidada'): ?>
    <div class="alert alert-secondary">La evaluación quedó invalidada porque cambió un documento o dato utilizado; se conserva como antecedente (RN-61).</div>
<?php elseif ($vigencia === 'Sustituida'): ?>
    <div class="alert alert-secondary">Una evaluación posterior la sustituyó; se conserva como antecedente.</div>
<?php endif ?>

<?php if ($e['modalidad'] === 'Automatica' && $e['procesamiento'] === 'Finalizada'): ?>
    <div class="d-flex align-items-baseline gap-3 mb-2">
        <span class="categoria-riesgo categoria-<?= esc(strtolower((string) $e['categoria_riesgo']), 'attr') ?>" aria-label="Categoría de riesgo <?= esc((string) $e['categoria_riesgo'], 'attr') ?>"><?= esc((string) $e['categoria_riesgo']) ?></span>
        <span><span class="fs-4 fw-semibold"><?= esc(str_replace('.', ',', (string) $e['puntaje'])) ?></span> puntos de 100</span>
    </div>
    <table class="table table-sm align-middle">
        <caption class="small">Indicadores del modelo de evaluación (RN-69)</caption>
        <thead><tr><th>Indicador</th><th class="text-end">Valor</th><th class="text-end">Peso</th><th class="text-end">Aporte</th></tr></thead>
        <tbody>
        <?php foreach ((array) ($ind['indicadores'] ?? []) as $k => $v): ?>
            <tr><td><?= esc(D::INDICADORES[$k] ?? $k) ?></td><td class="text-end"><?= (int) $v['valor'] ?></td><td class="text-end"><?= esc(formato_porcentaje((string) $v['peso'], 0)) ?></td><td class="text-end"><?= esc(str_replace('.', ',', (string) $v['aporte'])) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if ($e['modalidad'] === 'Contingencia'): ?>
    <h3 class="h6">Recomendación del Analista</h3>
    <p class="mb-1"><span class="badge rounded-pill <?= $e['recomendacion'] === 'Favorable' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= esc((string) $e['recomendacion']) ?></span><?= $e['analista'] ? ' · ' . esc($e['analista']) : '' ?></p>
    <p><?= nl2br(esc((string) $e['justificacion'])) ?></p>
    <h3 class="h6">Antecedentes verificables utilizados</h3>
    <ul class="small mb-2">
        <?php foreach ((array) ($ind['antecedentes_utilizados'] ?? []) as $a): ?><li><?= esc(Contingencia::ANTECEDENTES[$a] ?? $a) ?></li><?php endforeach ?>
    </ul>
    <p class="small"><?= nl2br(esc((string) ($ind['detalle_antecedentes'] ?? ''))) ?></p>
    <p class="small text-body-secondary">La recomendación no aprueba la solicitud: la resolución corresponde al Gerente (RN-21).</p>
<?php endif ?>

<?php if (is_array($internos) || is_array($bcra)): ?>
    <h3 class="h6">Datos utilizados</h3>
    <dl class="row small mb-3">
        <?php if (is_array($bcra)): ?>
            <dt class="col-sm-6 fw-medium">Peor situación BCRA de 12 meses</dt><dd class="col-sm-6"><?= $bcra['peor_12m'] === null ? 'Sin deudas informadas' : (int) $bcra['peor_12m'] ?></dd>
            <dt class="col-sm-6 fw-medium">Situación del último período</dt><dd class="col-sm-6"><?= $bcra['peor_vigente'] === null ? '—' : (int) $bcra['peor_vigente'] . ' (' . $periodo($bcra['ultimo_periodo']) . ')' ?></dd>
        <?php endif ?>
        <?php if (is_array($internos)): ?>
            <dt class="col-sm-6 fw-medium">Máximo atraso interno de 12 meses</dt><dd class="col-sm-6"><?= $internos['max_atraso'] === null ? 'Sin antecedentes internos' : (int) $internos['max_atraso'] . ' días' ?></dd>
            <dt class="col-sm-6 fw-medium">Ingresos declarados</dt><dd class="col-sm-6"><?= esc(formato_importe($internos['ingresos'])) ?></dd>
            <dt class="col-sm-6 fw-medium">Cuotas vigentes</dt><dd class="col-sm-6"><?= esc(formato_importe($internos['cuotas_vigentes'])) ?></dd>
            <dt class="col-sm-6 fw-medium">Cuotas comprometidas</dt><dd class="col-sm-6"><?= esc(formato_importe($internos['cuotas_comprometidas'])) ?><?= (int) $internos['solicitudes_en_tramite'] > 0 ? ' (' . (int) $internos['solicitudes_en_tramite'] . ' solicitud(es) en trámite)' : '' ?></dd>
            <dt class="col-sm-6 fw-medium">Antigüedad como cliente</dt><dd class="col-sm-6"><?= (int) $internos['antiguedad_meses'] ?> meses</dd>
        <?php endif ?>
    </dl>
<?php endif ?>

<?php if ($e['antecedentes'] !== []): ?>
    <table class="table table-sm align-middle mb-0">
        <caption class="small">Deudas informadas por la Central de Deudores<?= $e['modalidad'] === 'Contingencia' ? ' (consulta manual)' : '' ?></caption>
        <thead><tr><th>Período</th><th>Entidad</th><th class="text-end">Situación</th><th class="text-end">Monto</th></tr></thead>
        <tbody>
        <?php foreach ($e['antecedentes'] as $a): ?>
            <tr><td><?= esc($periodo((string) $a['periodo'])) ?></td><td><?= esc($a['entidad']) ?></td><td class="text-end"><?= (int) $a['situacion'] ?></td><td class="text-end"><?= esc(formato_importe($a['monto'])) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php elseif ($e['modalidad'] === 'Automatica' && $e['procesamiento'] === 'Finalizada'): ?>
    <p class="small mb-0">La Central de Deudores no informó deudas en los últimos 12 meses.</p>
<?php endif ?>
