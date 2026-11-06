<?php
/**
 * CU-36 Registrar Cierre de Caja, pasos 3 a 8 (A1, A2, A5).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $j jornada abierta con su resumen
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('cajas.abrir') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mi caja</a></nav>
<div class="encabezado">
    <div>
        <h1>Cerrar caja</h1>
        <p><?= esc($j['codigo'] . ' · ' . $j['denominacion']) ?> · abierta el <?= esc(formato_fecha_hora($j['apertura'])) ?></p>
    </div>
</div>
<?php if ($j['anterior']): ?><div class="alert alert-warning">La jornada es de una fecha anterior: ciérrela para volver a operar (RN-28).</div><?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-arqueo">
    <h2 class="h5" id="titulo-arqueo">Resumen de la jornada</h2>
    <?= view('cajas/_resumen', ['j' => $j]) ?>
    <p class="small text-body-secondary mt-2 mb-0">Las órdenes de pago electrónico pendientes no se computan como cobros confirmados (RN-32); se incorporan con los pagos electrónicos (iteración 3).</p>
</section>
<form method="post" action="<?= url_to('cajas.cerrar') ?>" class="hoja p-4" style="max-width: 40rem" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $j['version_fila'] ?>">
    <h2 class="h5">Arqueo</h2>
    <label for="efectivo_declarado" class="form-label">Efectivo contado al cierre</label>
    <input type="text" class="form-control<?= campo_invalido('efectivo_declarado') ?>" id="efectivo_declarado" name="efectivo_declarado" inputmode="decimal" value="<?= esc((string) old('efectivo_declarado'), 'attr') ?>" required data-esperado="<?= esc((string) $j['resumen']['esperado'], 'attr') ?>">
    <?= error_campo('efectivo_declarado') ?>
    <div class="mt-3">
        <label for="explicacion" class="form-label">Explicación de la diferencia <span class="text-body-secondary fw-normal">(obligatoria si el contado no coincide con el esperado)</span></label>
        <textarea class="form-control<?= campo_invalido('explicacion') ?>" id="explicacion" name="explicacion" rows="2" maxlength="500"><?= esc((string) old('explicacion')) ?></textarea>
        <?= error_campo('explicacion') ?>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="<?= url_to('cajas.abrir') ?>" class="btn btn-outline-secondary">Mantener abierta</a>
        <button type="submit" class="btn btn-primary">Cerrar la caja</button>
    </div>
</form>
<?php $this->endSection() ?>
