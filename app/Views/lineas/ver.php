<?php
/**
 * CU-73 Consultar Línea de Crédito.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $l
 * @var array<string, string> $categorias
 */
use App\Libraries\Lineas\ValidadorLinea;

$id = (int) $l['id'];
$v = $l['vigente'];
$pct = static fn ($x): string => esc(ValidadorLinea::aPorcentaje((string) $x)) . ' %';
$colores = ['Activa' => 'text-bg-success', 'Suspendida' => 'text-bg-warning', 'Vencida' => 'text-bg-secondary'];
$tipos = array_filter(['Personas humanas' => (bool) $v['admite_persona_humana'], 'Personas jurídicas' => (bool) $v['admite_persona_juridica']]);
$edad = $v['edad_minima'] !== null || $v['edad_maxima'] !== null
    ? trim(($v['edad_minima'] !== null ? 'desde ' . (int) $v['edad_minima'] . ' ' : '') . ($v['edad_maxima'] !== null ? 'hasta ' . (int) $v['edad_maxima'] . ' ' : '')) . ' años' : 'Sin límite';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('lineas.localizar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Líneas de crédito</a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc($l['nombre']) ?> <span class="badge rounded-pill align-middle fs-6 <?= $colores[$l['estado']] ?? 'text-bg-secondary' ?>"><?= esc($l['estado']) ?></span></h1>
        <p><code><?= esc($l['codigo']) ?></code> · versión <?= (int) $v['numero'] ?> vigente desde el <?= esc(formato_fecha_hora((string) $v['vigente_desde'])) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($l['estado'] !== 'Vencida' && tiene_permiso('lineas.modificar')): ?>
            <a href="<?= site_url("admin/lineas/{$id}/editar") ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Modificar</a>
        <?php endif ?>
        <?php if ($l['estado'] === 'Activa' && tiene_permiso('lineas.suspender')): ?>
            <a href="<?= site_url("admin/lineas/{$id}/suspender") ?>" class="btn btn-outline-danger">Suspender</a>
        <?php elseif ($l['estado'] === 'Suspendida' && tiene_permiso('lineas.reactivar')): ?>
            <a href="<?= site_url("admin/lineas/{$id}/reactivar") ?>" class="btn btn-primary">Reactivar</a>
        <?php endif ?>
    </div>
</div>

<?php if ($l['estado'] !== 'Activa'): ?>
    <div class="alert alert-warning">
        <?= $l['estado'] === 'Suspendida'
            ? 'Línea suspendida: no admite nuevas simulaciones, ofertas ni solicitudes independientes. Las solicitudes ya registradas y las ofertas emitidas antes continúan (RN-16).'
            : 'Línea vencida: superó su período de vigencia y se conserva como antecedente (RN-13).' ?>
    </div>
<?php endif ?>
<?php if ($l['pendiente'] !== null): ?>
    <div class="alert alert-info">La versión <?= (int) $l['pendiente']['numero'] ?> regirá desde el <?= esc(formato_fecha_hora((string) $l['pendiente']['vigente_desde'])) ?>.</div>
<?php endif ?>
<?php if ((string) $l['descripcion'] !== ''): ?><p><?= esc((string) $l['descripcion']) ?></p><?php endif ?>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <section class="hoja p-4 h-100">
            <h2 class="h5">Importes y plazos prestables</h2>
            <dl class="row mb-0">
                <dt class="col-sm-6">Importe mínimo</dt><dd class="col-sm-6 importe"><?= formato_importe((string) $v['monto_minimo']) ?></dd>
                <dt class="col-sm-6">Importe máximo</dt><dd class="col-sm-6 importe"><?= formato_importe((string) $v['monto_maximo']) ?></dd>
                <dt class="col-sm-6">Cuotas</dt><dd class="col-sm-6 text-end"><?= (int) $v['cuotas_minimas'] ?> a <?= (int) $v['cuotas_maximas'] ?></dd>
            </dl>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="hoja p-4 h-100">
            <h2 class="h5">Tasa y gastos</h2>
            <dl class="row mb-0">
                <dt class="col-sm-6">TNA</dt><dd class="col-sm-6 text-end"><?= $pct($v['tna']) ?></dd>
                <dt class="col-sm-6">Gasto de otorgamiento</dt><dd class="col-sm-6 text-end"><?= $pct($v['gasto_otorgamiento_pct']) ?> del capital</dd>
                <dt class="col-sm-6">Gasto administrativo por cuota</dt><dd class="col-sm-6 importe"><?= formato_importe((string) $v['gasto_administrativo_cuota']) ?></dd>
            </dl>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="hoja p-4 h-100">
            <h2 class="h5">Elegibilidad</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Tipos de cliente</dt><dd class="col-sm-7"><?= esc(implode(' y ', array_keys($tipos))) ?></dd>
                <?php if ($v['admite_persona_humana']): ?><dt class="col-sm-5">Edad</dt><dd class="col-sm-7"><?= esc($edad) ?></dd><?php endif ?>
                <?php if ($v['admite_persona_juridica']): ?><dt class="col-sm-5">Antigüedad mínima</dt><dd class="col-sm-7"><?= $v['antiguedad_minima_meses'] !== null ? (int) $v['antiguedad_minima_meses'] . ' meses' : 'Sin mínimo' ?></dd><?php endif ?>
                <dt class="col-sm-5">Vigencia</dt><dd class="col-sm-7"><?= esc(formato_fecha((string) $v['vigencia_inicio']) . ' al ' . formato_fecha((string) $v['vigencia_fin'])) ?></dd>
            </dl>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="hoja p-4 h-100">
            <h2 class="h5">Operaciones asociadas</h2>
            <dl class="row mb-0">
                <dt class="col-sm-8">Simulaciones vigentes</dt><dd class="col-sm-4 text-end"><?= (int) $l['operaciones']['simulaciones_vigentes'] ?></dd>
                <dt class="col-sm-8">Solicitudes en curso</dt><dd class="col-sm-4 text-end"><?= (int) $l['operaciones']['solicitudes_en_curso'] ?></dd>
                <dt class="col-sm-8">Ofertas vigentes</dt><dd class="col-sm-4 text-end"><?= (int) $l['operaciones']['ofertas_vigentes'] ?></dd>
                <dt class="col-sm-8">Créditos activos o en mora</dt><dd class="col-sm-4 text-end"><?= (int) $l['operaciones']['creditos'] ?></dd>
            </dl>
        </section>
    </div>
</div>

<section class="hoja p-4 mb-3">
    <h2 class="h5">Documentación exigida por categoría de ingresos</h2>
    <div class="row g-3">
        <?php foreach ($v['categorias'] as $cat): ?>
            <div class="col-md-6 col-lg-4">
                <h3 class="h6"><?= esc($categorias[$cat] ?? $cat) ?></h3>
                <ul class="small mb-0"><?php foreach ($v['requisitos'][$cat] ?? [] as $r): ?><li><?= esc($r['nombre']) ?></li><?php endforeach ?></ul>
            </div>
        <?php endforeach ?>
    </div>
</section>

<section class="hoja p-4 mb-3">
    <h2 class="h5">Historial de versiones</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Versión</th><th>Vigente desde</th><th class="text-end">Mínimo</th><th class="text-end">Máximo</th><th class="text-end">TNA</th><th>Cuotas</th><th>Vigencia de la línea</th></tr></thead>
            <tbody>
            <?php foreach ($l['versiones'] as $x): ?>
                <tr<?= (int) $x['id'] === (int) $v['id'] ? ' class="table-active"' : '' ?>>
                    <td><?= (int) $x['numero'] ?><?= (int) $x['id'] === (int) $v['id'] ? ' <span class="badge text-bg-primary">vigente</span>' : '' ?></td>
                    <td><?= esc(formato_fecha_hora((string) $x['vigente_desde'])) ?></td>
                    <td class="importe"><?= formato_importe((string) $x['monto_minimo']) ?></td>
                    <td class="importe"><?= formato_importe((string) $x['monto_maximo']) ?></td>
                    <td class="text-end"><?= $pct($x['tna']) ?></td>
                    <td><?= (int) $x['cuotas_minimas'] ?> a <?= (int) $x['cuotas_maximas'] ?></td>
                    <td><?= esc(formato_fecha((string) $x['vigencia_inicio']) . ' al ' . formato_fecha((string) $x['vigencia_fin'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($l['cambios_estado'] !== []): ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Suspensiones y reactivaciones</h2>
        <ul class="list-unstyled mb-0 small">
            <?php foreach ($l['cambios_estado'] as $c): ?>
                <li class="mb-1"><?= esc(formato_fecha_hora((string) $c['fecha_hora'])) ?> · <?= $c['accion'] === 'lineas.suspender' ? 'Suspendida' : 'Reactivada' ?> por <?= esc((string) $c['responsable']) ?>: <?= esc((string) $c['motivo']) ?></li>
            <?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
