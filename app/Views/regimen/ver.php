<?php
/**
 * CU-105 paso 6: clasificación de los deudores de un período y descarga del archivo.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $r
 */
use App\Libraries\Reportes\Indicadores;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Régimen informativo <?= esc((string) $r['periodo']) ?></h1>
        <p>Versión <?= (int) $r['version'] ?> · cierre al <?= esc(formato_fecha((string) $r['fecha_cierre'])) ?> · generada el <?= esc(formato_fecha_hora((string) $r['fecha_generacion'])) ?> por <?= esc($r['usuario'] ?? 'el Temporizador') ?>.</p>
    </div>
    <span class="d-flex gap-2 d-print-none">
        <a href="<?= url_to('regimen.consultar') ?>" class="btn btn-outline-secondary">Períodos</a>
        <a href="<?= site_url('admin/regimen/' . (int) $r['id'] . '/archivo') ?>" class="btn btn-primary"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar archivo</a>
    </span>
</div>
<?php if ($r['estado'] === 'Rectificado'): ?><div class="alert alert-warning">Esta versión fue rectificada: se conserva como antecedente (A1).</div><?php endif ?>
<?php if ($r['motivo'] !== null): ?><div class="alert alert-info">Rectificación: <?= esc((string) $r['motivo']) ?></div><?php endif ?>
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <section class="hoja p-3 h-100">
            <h2 class="h6">Deudores por situación (RN-84)</h2>
            <table class="table table-sm mb-0"><thead><tr><th>Situación</th><th class="importe">Deudores</th><th class="importe">Saldo</th></tr></thead><tbody>
                <?php foreach ($r['por_situacion'] as $s => [$cant, $saldo]): ?><tr><td><?= $s ?> · <?= esc(Indicadores::SITUACIONES[$s]) ?></td><td class="importe"><?= (int) $cant ?></td><td class="importe"><?= esc(formato_importe($saldo, false)) ?></td></tr><?php endforeach ?>
                <tr class="fw-semibold"><td>Total</td><td class="importe"><?= (int) $r['cantidad_deudores'] ?></td><td class="importe"><?= esc(formato_importe((string) $r['saldo_total'], false)) ?></td></tr>
            </tbody></table>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="hoja p-3 h-100 small">
            <h2 class="h6">Archivo</h2>
            <p class="mb-1">Código de verificación SHA-256:</p>
            <p><code class="text-break"><?= esc((string) $r['hash_archivo']) ?></code></p>
            <p class="mb-1">Formato: una línea de cabecera (<code>CAB;período;versión;cierre;deudores;saldo</code>) y una por deudor (<code>DEU;CUIT/CUIL;denominación;situación;saldo;días de atraso;créditos</code>), separadas por punto y coma.</p>
            <?php if (count($r['versiones']) > 1): ?>
                <p class="mb-0">Versiones del período: <?php foreach ($r['versiones'] as $i => $ver): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= url_to('regimen.ver', (int) $ver['id']) ?>"><?= (int) $ver['version'] ?> (<?= esc(mb_strtolower((string) $ver['estado'])) ?>)</a><?php endforeach ?></p>
            <?php endif ?>
        </section>
    </div>
</div>
<section class="hoja p-0">
    <?php if ($r['deudores'] === []): ?>
        <p class="p-4 mb-0">No había financiaciones vigentes al cierre: el período se registró sin deudores (A2).</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>CUIT/CUIL</th><th>Deudor</th><th class="importe">Créditos</th><th class="importe">Días de atraso</th><th>Situación</th><th class="importe">Saldo</th></tr></thead>
                <tbody>
                <?php foreach ($r['deudores'] as $d): ?>
                    <tr><td><?= esc(formato_cuit((string) $d['cuit_cuil'])) ?></td><td><a href="<?= site_url('admin/clientes/' . (int) $d['cliente_id']) ?>"><?= esc((string) $d['denominacion']) ?></a></td><td class="importe"><?= (int) $d['creditos'] ?></td><td class="importe"><?= (int) $d['dias_atraso'] ?></td><td><?= (int) $d['situacion'] ?></td><td class="importe"><?= esc(formato_importe((string) $d['saldo'], false)) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
