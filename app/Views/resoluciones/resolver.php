<?php
/**
 * CU-26 Resolver Solicitud de Crédito Convencional, pasos 3 a 7 (A8).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var array<string, mixed> $a antecedentes (ResolucionService::antecedentes)
 * @var array<string, mixed>|null $evaluacion evaluación más reciente
 */
use App\Libraries\Evaluacion\DimensionesEvaluacion as D;
use App\Libraries\Solicitudes\ResolucionGerencial;

$id = (int) $s['id'];
$confirmada = $a['confirmada'];
$vigentes = array_values(array_filter($a['estructuracion']['alternativas'] ?? [], static fn ($x) => (int) $x['vigente'] === 1));
$decision = (string) (old('decision') ?? '');
$plan = (string) (old('plan') ?? ($confirmada ? 'confirmada' : ($vigentes !== [] ? 'alternativa' : 'definida')));
$ultimo = end($s['transiciones']) ?: null;
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('solicitudes.resolver') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitudes para resolver</a></nav>
<div class="encabezado">
    <div>
        <h1>Resolver la solicitud N.º <?= (int) $s['numero'] ?></h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?> · pedido <?= esc(formato_importe($s['importe'])) ?> en <?= (int) $s['cantidad_cuotas'] ?> cuotas</p>
    </div>
    <a href="<?= site_url("admin/solicitudes/{$id}") ?>" class="btn btn-outline-secondary">Ver la solicitud completa</a>
</div>
<?php if ($ultimo && $ultimo['motivo']): ?><div class="alert alert-info"><strong>Motivo de la derivación:</strong> <?= esc($ultimo['motivo']) ?></div><?php endif ?>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-ev">
            <h2 class="h6" id="titulo-ev">Evaluación crediticia</h2>
            <?php if ($evaluacion === null): ?>
                <p class="mb-0">Sin evaluación.</p>
            <?php else: ?>
                <p class="mb-1"><?= esc(D::etiqueta(D::RESULTADO, $evaluacion['resultado'])) ?><?= $evaluacion['categoria_riesgo'] ? ' · categoría ' . esc($evaluacion['categoria_riesgo']) : '' ?> · <?= esc(D::etiqueta(D::MODALIDAD, $evaluacion['modalidad'])) ?></p>
                <p class="small mb-1">Vigencia: <?= esc(D::etiqueta(D::VIGENCIA, $evaluacion['vigencia_efectiva'])) ?><?= $evaluacion['vence'] ? ' hasta el ' . esc(formato_fecha($evaluacion['vence'])) : '' ?></p>
                <?php if ($evaluacion['modalidad'] === 'Contingencia'): ?><p class="small mb-1">Recomendación del Analista: <strong><?= esc((string) $evaluacion['recomendacion']) ?></strong></p><?php endif ?>
                <?php if (tiene_permiso('evaluaciones.consultar')): ?><a class="small" href="<?= site_url("admin/solicitudes/{$id}/evaluacion") ?>">Ver el detalle de la evaluación</a><?php endif ?>
            <?php endif ?>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-cap">
            <h2 class="h6" id="titulo-cap">Capacidad de pago actual</h2>
            <p class="fs-5 fw-semibold mb-1"><?= esc(formato_importe($a['capacidad'])) ?></p>
            <p class="small mb-0">Ingresos por el porcentaje de afectación menos las cuotas vigentes y comprometidas del cliente (RN-18). Documentación: <?= $a['documentacion_verificada'] ? 'verificada' : '<strong>no verificada</strong>' ?>.</p>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-fondos">
            <h2 class="h6" id="titulo-fondos">Fondos de la entidad</h2>
            <p class="fs-5 fw-semibold mb-1"><?= esc(formato_importe($a['fondos']['libre'])) ?> <span class="fs-6 fw-normal">disponibles</span></p>
            <p class="small mb-0">Fondos <?= esc(formato_importe($a['fondos']['fondos'])) ?>, reservas y compromisos <?= esc(formato_importe($a['fondos']['afectaciones'])) ?>, reserva mínima <?= esc(formato_importe($a['fondos']['reserva_minima'])) ?> (RN-71).</p>
        </section>
    </div>
</div>

<form method="post" action="<?= site_url("admin/solicitudes/{$id}/resolver") ?>" class="hoja p-4" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
    <fieldset class="mb-3">
        <legend class="h5">Decisión</legend>
        <div class="row g-2">
            <?php foreach (ResolucionGerencial::DECISIONES as $k => $t): ?>
                <div class="col-md-4">
                    <input type="radio" class="btn-check" name="decision" id="decision-<?= $k ?>" value="<?= $k ?>"<?= $decision === $k ? ' checked' : '' ?> required>
                    <label class="btn btn-outline-secondary w-100 opcion-resultado" for="decision-<?= $k ?>"><?= $t ?></label>
                </div>
            <?php endforeach ?>
        </div>
        <?= error_campo('decision') ?>
    </fieldset>

    <fieldset class="mb-3" data-si-decision="Aprobada">
        <legend class="h6">Plan que se aprueba</legend>
        <?php if ($confirmada): ?>
            <input type="hidden" name="plan" value="confirmada">
            <p class="mb-0">Alternativa confirmada por el cliente: <strong><?= esc(formato_importe($confirmada['importe'])) ?> en <?= (int) $confirmada['cantidad_cuotas'] ?> cuotas</strong>, primera cuota <?= esc(formato_importe($confirmada['valor_cuota'])) ?>, CFTEA <?= esc(formato_porcentaje((string) $confirmada['cftea'])) ?> (<?= esc((string) $confirmada['medio_conformidad']) ?>).</p>
        <?php else: ?>
            <p class="small">La solicitud no tiene una alternativa confirmada (A8): elija una alternativa vigente o defina el importe y las cuotas dentro de los límites de la línea y de la capacidad de pago. La conformidad del cliente se registra al liquidar (RN-22).</p>
            <?php foreach ($vigentes as $x): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="alternativa_id" id="alt-<?= (int) $x['id'] ?>" value="<?= (int) $x['id'] ?>" data-plan="alternativa"<?= (string) old('alternativa_id') === (string) $x['id'] ? ' checked' : '' ?>>
                    <label class="form-check-label" for="alt-<?= (int) $x['id'] ?>"><?= esc(formato_importe($x['importe'])) ?> en <?= (int) $x['cantidad_cuotas'] ?> cuotas · primera cuota <?= esc(formato_importe($x['valor_cuota'])) ?> · CFTEA <?= esc(formato_porcentaje((string) $x['cftea'])) ?></label>
                </div>
            <?php endforeach ?>
            <?= error_campo('alternativa_id') ?>
            <input type="hidden" name="plan" id="plan" value="<?= esc($plan, 'attr') ?>">
            <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="alternativa_id" id="alt-definida" value="" data-plan="definida"<?= $plan === 'definida' ? ' checked' : '' ?>>
                <label class="form-check-label" for="alt-definida">Definir el importe y las cuotas</label>
            </div>
            <div class="row g-2 mt-1 ms-3" style="max-width: 32rem">
                <div class="col-sm-7">
                    <label for="importe" class="form-label small">Importe</label>
                    <input type="text" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" inputmode="decimal" value="<?= esc((string) (old('importe') ?? str_replace('$ ', '', formato_importe($s['importe']))), 'attr') ?>">
                    <?= error_campo('importe') ?>
                </div>
                <div class="col-sm-5">
                    <label for="cantidad_cuotas" class="form-label small">Cuotas (<?= (int) $s['cuotas_minimas'] ?> a <?= (int) $s['cuotas_maximas'] ?>)</label>
                    <input type="text" class="form-control<?= campo_invalido('cantidad_cuotas') ?>" id="cantidad_cuotas" name="cantidad_cuotas" inputmode="numeric" value="<?= esc((string) (old('cantidad_cuotas') ?? $s['cantidad_cuotas']), 'attr') ?>">
                    <?= error_campo('cantidad_cuotas') ?>
                </div>
            </div>
        <?php endif ?>
        <?= error_campo('plan') ?>
    </fieldset>

    <div class="mb-3" data-si-decision="DevueltaRevision">
        <label for="etapa_retorno" class="form-label">Etapa que debe retomarse</label>
        <select class="form-select<?= campo_invalido('etapa_retorno') ?>" id="etapa_retorno" name="etapa_retorno" style="max-width: 24rem">
            <option value="">Seleccione…</option>
            <?php foreach (ResolucionGerencial::ETAPAS as $k => $t): ?><option value="<?= $k ?>"<?= old('etapa_retorno') === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
        </select>
        <?= error_campo('etapa_retorno') ?>
    </div>
    <div class="mb-3">
        <label for="fundamento" class="form-label">Fundamento</label>
        <textarea class="form-control<?= campo_invalido('fundamento') ?>" id="fundamento" name="fundamento" rows="3" maxlength="2000" required><?= esc((string) old('fundamento')) ?></textarea>
        <?= error_campo('fundamento') ?>
    </div>
    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= url_to('solicitudes.resolver') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Registrar la resolución</button>
    </div>
</form>
<script src="<?= base_url('assets/sigccr/solicitudes.js') ?>"></script>
<?php $this->endSection() ?>
