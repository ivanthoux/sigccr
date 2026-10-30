<?php
/**
 * CU-21 pasos 3 a 6: líneas admitidas para el cliente (RN-75) y condiciones de la elegida.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $lineas
 * @var array<string, mixed>|null $linea
 */
$admitidas = array_values(array_filter($lineas, static fn ($l) => $l['motivo_no_admitida'] === null));
$noAdmitidas = array_values(array_filter($lineas, static fn ($l) => $l['motivo_no_admitida'] !== null));
$base = url_to('solicitudes.simular') . '?cliente=' . (int) $cliente['id'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('solicitudes.simular') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Simular crédito</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(\App\Libraries\Clientes\RequisitosDocumentales::CATEGORIAS[$cliente['tipo_cliente']][$cliente['categoria_ingresos']] ?? $cliente['categoria_ingresos']) ?></p>
    </div>
</div>

<?php if ($admitidas === []): ?>
    <div class="hoja p-4 mb-3"><p class="mb-0">El cliente no cumple los requisitos de ninguna línea activa (A1). Los motivos se detallan abajo.</p></div>
<?php else: ?>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-lineas">
        <h2 class="h5" id="titulo-lineas">Líneas admitidas</h2>
        <div class="list-group">
            <?php foreach ($admitidas as $l): $activa = $linea !== null && (int) $linea['id'] === (int) $l['id']; ?>
                <a href="<?= esc($base . '&linea=' . (int) $l['id'], 'attr') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center flex-wrap gap-2<?= $activa ? ' active' : '' ?>"<?= $activa ? ' aria-current="true"' : '' ?>>
                    <span class="fw-medium"><?= esc($l['nombre']) ?></span>
                    <span class="small">TNA <?= esc(formato_porcentaje($l['tna'])) ?> · <?= esc(formato_importe($l['monto_minimo'])) ?> a <?= esc(formato_importe($l['monto_maximo'])) ?> · <?= (int) $l['cuotas_minimas'] ?> a <?= (int) $l['cuotas_maximas'] ?> cuotas</span>
                </a>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<?php if ($linea !== null): ?>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-simular">
        <h2 class="h5" id="titulo-simular"><?= esc($linea['nombre']) ?> <span class="badge text-bg-light border">versión <?= (int) $linea['version'] ?></span></h2>
        <dl class="row small mb-3">
            <dt class="col-sm-4">Tasa nominal anual</dt><dd class="col-sm-8"><?= esc(formato_porcentaje($linea['tna'])) ?></dd>
            <dt class="col-sm-4">Gastos (RN-68)</dt><dd class="col-sm-8">Otorgamiento <?= esc(formato_porcentaje($linea['gasto_otorgamiento_pct'])) ?> del capital, administrativo <?= esc(formato_importe($linea['gasto_administrativo_cuota'])) ?> por cuota, más IVA</dd>
            <dt class="col-sm-4">Importe prestable</dt><dd class="col-sm-8"><?= esc(formato_importe($linea['monto_minimo'])) ?> a <?= esc(formato_importe($linea['monto_maximo'])) ?> (RN-76)</dd>
            <dt class="col-sm-4">Cuotas</dt><dd class="col-sm-8"><?= (int) $linea['cuotas_minimas'] ?> a <?= (int) $linea['cuotas_maximas'] ?></dd>
            <dt class="col-sm-4">Vigencia de la línea</dt><dd class="col-sm-8">Del <?= esc(formato_fecha($linea['vigencia_inicio'])) ?> al <?= esc(formato_fecha($linea['vigencia_fin'])) ?></dd>
        </dl>
        <form method="post" action="<?= url_to('solicitudes.simular') ?>" class="row g-3 align-items-end" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
            <input type="hidden" name="linea_id" value="<?= (int) $linea['id'] ?>">
            <div class="col-sm-6 col-lg-4">
                <label for="importe" class="form-label">Importe a financiar ($)</label>
                <input type="text" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" inputmode="decimal" value="<?= esc(old('importe'), 'attr') ?>" required autofocus>
                <?= error_campo('importe') ?>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="cantidad_cuotas" class="form-label">Cantidad de cuotas</label>
                <input type="number" class="form-control<?= campo_invalido('cantidad_cuotas') ?>" id="cantidad_cuotas" name="cantidad_cuotas" min="<?= (int) $linea['cuotas_minimas'] ?>" max="<?= (int) $linea['cuotas_maximas'] ?>" value="<?= esc(old('cantidad_cuotas'), 'attr') ?>" required>
                <?= error_campo('cantidad_cuotas') ?>
            </div>
            <div class="col-lg-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Simular</button></div>
        </form>
    </section>
<?php elseif ($admitidas !== []): ?>
    <p class="text-body-secondary">Seleccione una línea para ver sus condiciones y simular.</p>
<?php endif ?>

<?php if ($noAdmitidas !== []): ?>
    <section class="hoja p-4" aria-labelledby="titulo-no-admitidas">
        <h2 class="h6" id="titulo-no-admitidas">Líneas que no admiten al cliente</h2>
        <ul class="small mb-0">
            <?php foreach ($noAdmitidas as $l): ?><li><span class="fw-medium"><?= esc($l['nombre']) ?>:</span> <?= esc($l['motivo_no_admitida']) ?></li><?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
