<?php
/**
 * CU-43 Consultar Propuesta de Refinanciación, pasos 3 a 5 (A1 a A4): composición de la deuda, condiciones, plan,
 * estado, resoluciones, versiones, alertas y simulación de origen, con las operaciones habilitadas según el rol.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $p
 */
use App\Services\Cartera\RefinanciacionService;

$abierta = in_array($p['estado'], RefinanciacionService::VIGENTES, true);
$decisiones = ['Aprobada' => 'Aprobada', 'Rechazada' => 'Rechazada', 'Observada' => 'Observada'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('refinanciaciones.localizar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Propuestas</a></nav>
<div class="encabezado">
    <div>
        <h1>Propuesta N.º <?= (int) $p['numero'] ?><?= (int) $p['version'] > 1 ? ' · versión ' . (int) $p['version'] : '' ?> <?= view('refinanciaciones/_estado', ['estado' => $p['estado']]) ?></h1>
        <p><?= esc($p['denominacion']) ?> · <?= esc(formato_cuit($p['cuit_cuil'])) ?> · registrada por <?= esc($p['analista']) ?> el <?= esc(formato_fecha_hora($p['fecha_registro'])) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($p['estado'] === 'PendienteResolucion' && $p['vigente'] && tiene_permiso('refinanciaciones.resolver')): ?>
            <a class="btn btn-primary" href="<?= site_url('admin/refinanciaciones/' . (int) $p['id'] . '/resolver') ?>"><i class="bi bi-check2-square me-1" aria-hidden="true"></i>Resolver</a>
        <?php endif ?>
        <?php if ($p['estado'] === 'AprobadaPendienteAceptacion' && $p['vigente'] && tiene_permiso('refinanciaciones.aplicar')): ?>
            <a class="btn btn-primary" href="<?= site_url('admin/refinanciaciones/' . (int) $p['id'] . '/aplicar') ?>"><i class="bi bi-pen me-1" aria-hidden="true"></i>Registrar aceptación y aplicar</a>
        <?php endif ?>
        <?php if (($abierta || $p['estado'] === 'Observada') && tiene_permiso('refinanciaciones.cancelar-propuesta')): ?>
            <a class="btn btn-outline-danger" href="<?= site_url('admin/refinanciaciones/' . (int) $p['id'] . '/cancelar') ?>">Cancelar propuesta</a>
        <?php endif ?>
    </div>
</div>

<?php if ($abierta): ?>
    <div class="alert <?= $p['vigente'] ? 'alert-info' : 'alert-warning' ?>">
        <?= $p['vigente'] ? 'Vigente hasta el ' . esc(formato_fecha_hora($p['vence'])) . '.' : 'Superó su vigencia el ' . esc(formato_fecha_hora($p['vence'])) . ': quedará Vencida.' ?>
        <?= $p['estado'] === 'AprobadaPendienteAceptacion' ? 'Aprobada: el nuevo plan no se aplica hasta que el cliente acepte y se registre la aplicación (A3).' : 'Pendiente de resolución gerencial.' ?>
        La propuesta no modifica las obligaciones ni suspende vencimientos o punitorios (RN-36).
    </div>
<?php elseif ($p['estado'] === 'Vencida'): ?>
    <div class="alert alert-secondary">Venció el <?= esc(formato_fecha_hora($p['vence'])) ?> sin aplicarse; su vencimiento no modificó las obligaciones originales (A1).</div>
<?php elseif ($p['estado'] === 'Observada'): ?>
    <div class="alert alert-warning"><strong>Observada:</strong> <?= esc((string) $p['observacion']) ?> Su corrección requiere una nueva versión, con nueva simulación, nuevo plazo y nueva resolución (A2).</div>
<?php elseif ($p['estado'] === 'Aplicada'): ?>
    <div class="alert alert-success">Aplicada: las obligaciones originales quedaron sustituidas por el crédito N.º <?= (int) ($p['credito']['numero'] ?? 0) ?>, no pagadas ni eliminadas (A4, RN-37).</div>
<?php elseif ($p['estado'] === 'Cancelada'): ?>
    <div class="alert alert-secondary">Cancelada por <?= $p['tipo_cancelacion'] === 'Desistimiento' ? 'desistimiento del cliente' : 'causa administrativa' ?> el <?= esc(formato_fecha_hora((string) $p['fecha_cancelacion'])) ?> (<?= esc((string) $p['cancelada_por']) ?>): <?= esc((string) $p['motivo_cancelacion']) ?>
        <?php if ($p['tipo_cancelacion'] === 'Desistimiento'): ?> Constancia del <?= esc(formato_fecha((string) $p['fecha_desistimiento'])) ?> por <?= esc((string) $p['medio_desistimiento']) ?>.<?php endif ?></div>
<?php endif ?>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-condiciones">
    <h2 class="h5" id="titulo-condiciones">Deuda y condiciones</h2>
    <dl class="resumen-plan mb-0">
        <div><dt>Fecha de corte</dt><dd><?= esc(formato_fecha((string) $p['fecha_corte'])) ?></dd></div>
        <div><dt>Capital refinanciado</dt><dd class="importe"><?= esc(formato_importe((string) $p['capital_refinanciado'])) ?></dd></div>
        <div><dt>Concepto no capitalizable</dt><dd class="importe"><?= esc(formato_importe((string) $p['concepto_no_capitalizable'])) ?><?= $p['aceptacion'] !== null && (float) $p['aceptacion']['punitorios_incorporados'] > 0 ? ' + ' . esc(formato_importe((string) $p['aceptacion']['punitorios_incorporados'])) . ' al aplicar' : '' ?></dd></div>
        <div><dt>Condiciones</dt><dd><?= (int) $p['cantidad_cuotas'] ?> cuotas · TNA <?= esc(formato_porcentaje((string) $p['tasa'])) ?></dd></div>
        <?php if ($p['simulacion_id'] !== null): ?><div><dt>Simulación de origen</dt><dd><a href="<?= site_url('admin/refinanciaciones/simulaciones/' . (int) $p['simulacion_id']) ?>">Ver simulación</a></dd></div><?php endif ?>
    </dl>
</section>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-cuotas">
    <h2 class="h5" id="titulo-cuotas">Obligaciones comprendidas</h2>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital</th><th class="text-end">No capitalizable</th><th class="text-end">Punitorios al corte</th><th>Estado actual</th></tr></thead>
            <tbody>
            <?php foreach ($p['cuotas'] as $q): ?>
                <tr><td><?= (int) $q['credito'] ?></td><td><?= (int) $q['numero'] ?></td><td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                    <td class="importe"><?= esc(formato_importe($q['capital_pendiente'], false)) ?></td><td class="importe"><?= esc(formato_importe($q['no_capitalizable'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe($q['punitorio'], false)) ?></td><td><?= esc($q['estado_cuota']) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-plan">
    <?php if ($p['plan_aplicado'] !== null): ?>
        <h2 class="h5" id="titulo-plan">Plan del crédito N.º <?= (int) $p['credito']['numero'] ?></h2>
        <?= view('refinanciaciones/_plan', ['filas' => $p['plan_aplicado']]) ?>
    <?php else: ?>
        <h2 class="h5" id="titulo-plan">Nuevo plan de cuotas</h2>
        <p class="small text-body-secondary">Estimado como si se aplicara hoy: al aplicarse se incorporan los punitorios devengados desde el corte y el primer vencimiento queda a los 30 días corridos (RN-70).</p>
        <?= view('refinanciaciones/_plan', ['filas' => array_map(static fn ($q) => $q->toArray(), $p['plan']->cuotas)]) ?>
        <p class="mt-2 mb-0">Total de intereses: <strong><?= esc(formato_importe($p['plan']->totalIntereses())) ?></strong> · Total a pagar: <strong><?= esc(formato_importe($p['plan']->totalAPagar())) ?></strong></p>
    <?php endif ?>
</section>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-resoluciones">
            <h2 class="h5" id="titulo-resoluciones">Resoluciones</h2>
            <?php if ($p['resoluciones'] === []): ?><p class="mb-0">Sin resoluciones registradas.</p><?php endif ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($p['resoluciones'] as $r): ?>
                    <li class="mb-2"><strong><?= esc($decisiones[$r['decision']] ?? $r['decision']) ?></strong> · <?= esc($r['gerente']) ?> · <?= esc(formato_fecha_hora($r['fecha'])) ?><div class="small"><?= esc($r['fundamento']) ?></div></li>
                <?php endforeach ?>
                <?php if ($p['aceptacion'] !== null): ?>
                    <li class="mb-2"><strong>Aceptación del cliente</strong> del <?= esc(formato_fecha((string) $p['aceptacion']['fecha_constancia'])) ?> por <?= esc($p['aceptacion']['medio']) ?> · registrada por <?= esc($p['aceptacion']['responsable']) ?>
                        <?php if ($p['aceptacion']['observaciones'] !== null): ?><div class="small"><?= esc($p['aceptacion']['observaciones']) ?></div><?php endif ?></li>
                <?php endif ?>
            </ul>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-versiones">
            <h2 class="h5" id="titulo-versiones">Versiones y alertas</h2>
            <ul class="mb-2">
                <?php foreach ($p['versiones'] as $v): ?>
                    <li><?= (int) $v['id'] === (int) $p['id'] ? '<strong>' : '<a href="' . site_url('admin/refinanciaciones/' . (int) $v['id']) . '">' ?>Versión <?= (int) $v['version'] ?><?= (int) $v['id'] === (int) $p['id'] ? '</strong>' : '</a>' ?> · <?= esc(RefinanciacionService::ESTADOS[$v['estado']] ?? $v['estado']) ?> · <?= esc(formato_fecha_hora($v['fecha_registro'])) ?></li>
                <?php endforeach ?>
            </ul>
            <?php foreach ($p['alertas'] as $a): ?>
                <div class="small"><i class="bi bi-bell me-1" aria-hidden="true"></i><?= esc($a['causa']) ?> <span class="text-body-secondary">(<?= esc($a['estado']) ?>, <?= esc(formato_fecha_hora($a['fecha_generacion'])) ?>)</span></div>
            <?php endforeach ?>
        </section>
    </div>
</div>
<?php $this->endSection() ?>
