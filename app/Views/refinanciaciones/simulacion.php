<?php
/**
 * CU-39 paso 10 y CU-40 pasos 4 a 7: simulación con la deuda consolidada, el plan preliminar y su vigencia.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 */
$plan = $s['plan'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('refinanciaciones.simular') ?>?cliente=<?= (int) $s['cliente_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Créditos del cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Simulación de refinanciación</h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc(formato_cuit($s['cuit_cuil'])) ?> · créditos N.º <?= esc(implode(', ', $s['creditos'])) ?></p>
    </div>
</div>
<div class="alert <?= $s['vigente'] ? 'alert-info' : 'alert-secondary' ?>">
    <?= $s['vigente'] ? 'Vigente hasta el ' . esc(formato_fecha_hora($s['vence'])) . '.' : 'Venció el ' . esc(formato_fecha_hora($s['vence'])) . ': genere una nueva simulación (CU-40 A1).' ?>
    La simulación no modifica la deuda, no suspende vencimientos ni punitorios y no es una propuesta (RN-36).
</div>
<?php if ($s['excluidos'] !== []): ?>
    <div class="alert alert-warning">Quedan fuera del plan los créditos en mora N.º <?= esc(implode(', ', $s['excluidos'])) ?> (A5).</div>
<?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-resumen">
    <h2 class="h5" id="titulo-resumen">Deuda consolidada al <?= esc(formato_fecha((string) $s['fecha_corte'])) ?></h2>
    <dl class="resumen-plan mb-0">
        <div><dt>Capital a refinanciar</dt><dd class="importe"><?= esc(formato_importe((string) $s['capital'])) ?></dd></div>
        <div><dt>Concepto no capitalizable</dt><dd class="importe"><?= esc(formato_importe((string) $s['no_capitalizable'])) ?></dd></div>
        <div><dt>Deuda consolidada</dt><dd class="importe fw-semibold"><?= esc(formato_importe((string) $s['deuda_consolidada'])) ?></dd></div>
        <div><dt>Condiciones</dt><dd><?= (int) $s['cantidad_cuotas'] ?> cuotas · TNA <?= esc(formato_porcentaje((string) $s['tasa'])) ?></dd></div>
        <div><dt>Total de intereses</dt><dd class="importe"><?= esc(formato_importe($plan->totalIntereses())) ?></dd></div>
        <div><dt>Total a pagar</dt><dd class="importe fw-semibold"><?= esc(formato_importe($plan->totalAPagar())) ?></dd></div>
    </dl>
</section>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-cuotas">
    <h2 class="h5" id="titulo-cuotas">Cuotas comprendidas</h2>
    <table class="table table-sm mb-0">
        <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital</th><th class="text-end">No capitalizable</th><th class="text-end">Incluye punitorios</th></tr></thead>
        <tbody>
        <?php foreach ($s['cuotas'] as $q): ?>
            <tr><td><?= (int) $q['credito'] ?></td><td><?= (int) $q['numero'] ?></td><td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                <td class="importe"><?= esc(formato_importe($q['capital_pendiente'], false)) ?></td><td class="importe"><?= esc(formato_importe($q['no_capitalizable'], false)) ?></td><td class="importe"><?= esc(formato_importe($q['punitorio'], false)) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
</section>
<section class="hoja p-4" aria-labelledby="titulo-plan">
    <h2 class="h5" id="titulo-plan">Plan preliminar</h2>
    <p class="small text-body-secondary">Calendario estimado desde la fecha de la simulación; al aplicarse, el primer vencimiento será a los 30 días corridos de la aplicación (RN-70).</p>
    <?= view('refinanciaciones/_plan', ['filas' => array_map(static fn ($q) => $q->toArray(), $plan->cuotas)]) ?>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <?php if ($s['propuesta'] !== null): ?>
            <a class="btn btn-outline-primary" href="<?= site_url('admin/refinanciaciones/' . (int) $s['propuesta']['id']) ?>">Ver la propuesta N.º <?= (int) $s['propuesta']['numero'] ?></a>
        <?php elseif ($s['vigente'] && tiene_permiso('refinanciaciones.registrar-propuesta')): ?>
            <form method="post" action="<?= site_url('admin/refinanciaciones/simulaciones/' . (int) $s['id'] . '/propuesta') ?>" data-confirmar="¿Registra la propuesta para la resolución del Gerente? Quedará vigente por 7 días corridos.">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1" aria-hidden="true"></i>Registrar como propuesta</button>
            </form>
        <?php endif ?>
    </div>
</section>
<?php $this->endSection() ?>
