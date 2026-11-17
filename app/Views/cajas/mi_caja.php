<?php
/**
 * CU-31 Registrar Apertura de Caja (A1, A2, A4) y estado de la jornada del Cajero.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed>|null $asignacion
 * @var array<string, mixed>|null $jornada
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Mi caja</h1>
        <p><?= $jornada ? 'Jornada abierta en ' . esc($jornada['codigo'] . ' · ' . $jornada['denominacion']) : ($asignacion ? 'Caja asignada: ' . esc($asignacion['codigo'] . ' · ' . $asignacion['denominacion']) : 'Sin caja asignada') ?></p>
    </div>
    <?php if ($jornada): ?>
        <div class="d-flex gap-2">
            <?php if (!$jornada['anterior'] && tiene_permiso('cobros.registrar')): ?><a href="<?= url_to('cobros.registrar') ?>" class="btn btn-primary"><i class="bi bi-cash me-1" aria-hidden="true"></i>Registrar cobro</a><?php endif ?>
            <?php if (tiene_permiso('cajas.cerrar')): ?><a href="<?= url_to('cajas.cerrar') ?>" class="btn btn-outline-secondary"><i class="bi bi-lock me-1" aria-hidden="true"></i>Cerrar caja</a><?php endif ?>
        </div>
    <?php endif ?>
</div>
<?php if ($jornada): ?>
    <?php if ($jornada['anterior']): ?>
        <div class="alert alert-warning">La jornada se abrió el <?= esc(formato_fecha($jornada['fecha_local'])) ?>: ciérrela antes de registrar nuevas operaciones (RN-28).</div>
    <?php else: ?>
        <div class="alert alert-info">Su jornada sigue abierta desde el <?= esc(formato_fecha_hora($jornada['apertura'])) ?>: no hace falta volver a abrirla (RN-28).</div>
    <?php endif ?>
    <section class="hoja p-4" aria-labelledby="titulo-jornada">
        <h2 class="h5" id="titulo-jornada">Movimientos de la jornada</h2>
        <?= view('cajas/_resumen', ['j' => $jornada]) ?>
    </section>
<?php elseif ($asignacion === null): ?>
    <div class="hoja p-4"><p class="mb-0">No tiene una caja asignada para hoy. Solicite la asignación al Gerente (CU-72).</p></div>
<?php elseif ($asignacion['estado_caja'] !== 'Habilitada'): ?>
    <div class="hoja p-4"><p class="mb-0">Su caja <?= esc($asignacion['codigo']) ?> está inhabilitada. Consulte al Gerente.</p></div>
<?php else: ?>
    <form method="post" action="<?= url_to('cajas.abrir') ?>" class="hoja p-4" style="max-width: 36rem" novalidate>
        <?= csrf_field() ?>
        <h2 class="h5">Abrir la caja <?= esc($asignacion['codigo']) ?></h2>
        <p class="small"><?= esc($asignacion['denominacion']) ?><?= $asignacion['ubicacion'] ? ' · ' . esc($asignacion['ubicacion']) : '' ?>. Asignada desde el <?= esc(formato_fecha($asignacion['desde'])) ?><?= $asignacion['hasta'] ? ' hasta el ' . esc(formato_fecha($asignacion['hasta'])) : '' ?>.</p>
        <p class="small mb-0">La caja no maneja efectivo: los cobros se reciben por transferencia, depósito o tarjeta, con su número de operación, y al cerrar se concilian con lo acreditado en las cuentas de la entidad (RN-77, RN-32).</p>
        <div class="text-end mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-unlock me-1" aria-hidden="true"></i>Abrir caja</button></div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
