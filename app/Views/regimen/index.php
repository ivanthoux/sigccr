<?php
/**
 * CU-105 Generar Régimen Informativo de Deudores: archivos generados y generación o rectificación de un período
 * cerrado (A1).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $regimenes
 */
$mesAnterior = (new DateTimeImmutable(substr(hoy_negocio(), 0, 7) . '-01'))->modify('-1 month')->format('Y-m');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Régimen informativo de deudores</h1>
        <p>Clasificación mensual de los deudores (RN-84) y archivo para la Central de Deudores del BCRA. El Temporizador lo genera el primer día hábil de cada mes; el envío se hace fuera del sistema.</p>
    </div>
</div>
<form method="post" action="<?= url_to('regimen.consultar') ?>" class="hoja p-3 mb-3 row g-2 align-items-end" style="max-width: 56rem" novalidate data-confirmar="¿Genera el régimen informativo del período? Si ya existe, se registra una rectificación y la versión anterior queda como antecedente.">
    <?= csrf_field() ?>
    <div class="col-6 col-md-3"><label for="periodo" class="form-label small">Período (mes cerrado)</label><input type="month" id="periodo" name="periodo" max="<?= $mesAnterior ?>" class="form-control<?= campo_invalido('periodo') ?>" value="<?= esc((string) old('periodo', $mesAnterior), 'attr') ?>"><?= error_campo('periodo') ?></div>
    <div class="col-12 col-md-6"><label for="motivo" class="form-label small">Motivo <span class="text-body-secondary">(obligatorio para rectificar)</span></label><input type="text" id="motivo" name="motivo" maxlength="500" class="form-control<?= campo_invalido('motivo') ?>" value="<?= esc((string) old('motivo'), 'attr') ?>"><?= error_campo('motivo') ?></div>
    <div class="col-12 col-md-3 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Generar o rectificar</button></div>
</form>
<?php if ($regimenes === []): ?>
    <div class="hoja p-4"><p class="mb-0">Todavía no se generó ningún período.</p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Período</th><th>Versión</th><th>Generado</th><th>Responsable</th><th class="importe">Deudores</th><th class="importe">Saldo informado</th><th>Estado</th><th><span class="visually-hidden">Archivo</span></th></tr></thead>
                <tbody>
                <?php foreach ($regimenes as $r): ?>
                    <tr<?= $r['estado'] === 'Rectificado' ? ' class="text-body-secondary"' : '' ?>>
                        <td><a href="<?= url_to('regimen.ver', (int) $r['id']) ?>"><?= esc((string) $r['periodo']) ?></a></td>
                        <td><?= (int) $r['version'] ?></td>
                        <td><?= esc(formato_fecha_hora((string) $r['fecha_generacion'])) ?></td>
                        <td class="small"><?= esc($r['usuario'] ?? etiqueta_proceso($r['proceso'])) ?></td>
                        <td class="importe"><?= (int) $r['cantidad_deudores'] ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $r['saldo_total'], false)) ?></td>
                        <td><?= $r['estado'] === 'Vigente' ? '<span class="badge text-bg-success">Vigente</span>' : 'Rectificado' ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/regimen/' . (int) $r['id'] . '/archivo') ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>Archivo</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
