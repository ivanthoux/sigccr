<?php
/**
 * CU-80 Registrar Caja, CU-72 Asignar Caja a Cajero (A5) y CU-81 Cambiar Estado de Caja.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $cajas
 * @var list<array{id: int, nombre: string, asignada: ?string}> $cajeros
 */
$abierto = (string) session('formulario');
$libres = array_values(array_filter($cajeros, static fn ($c) => $c['asignada'] === null));
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Cajas</h1>
        <p>Unidades operativas de cobro y desembolso, su asignación a los Cajeros y su estado.</p>
    </div>
</div>

<details class="hoja p-4 mb-3"<?= $abierto === 'alta' || $cajas === [] ? ' open' : '' ?>>
    <summary class="h5 mb-0">Registrar una caja</summary>
    <form method="post" action="<?= url_to('cajas.registrar') ?>" class="mt-3" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="codigo" class="form-label">Código</label>
                <input type="text" class="form-control<?= $abierto === 'alta' ? campo_invalido('codigo') : '' ?>" id="codigo" name="codigo" maxlength="10" placeholder="CAJA-01" value="<?= esc($abierto === 'alta' ? (string) old('codigo') : '', 'attr') ?>" required>
                <?= $abierto === 'alta' ? error_campo('codigo') : '' ?>
            </div>
            <div class="col-md-4">
                <label for="denominacion" class="form-label">Denominación</label>
                <input type="text" class="form-control<?= $abierto === 'alta' ? campo_invalido('denominacion') : '' ?>" id="denominacion" name="denominacion" maxlength="60" placeholder="Caja 1 – Casa central" value="<?= esc($abierto === 'alta' ? (string) old('denominacion') : '', 'attr') ?>" required>
                <?= $abierto === 'alta' ? error_campo('denominacion') : '' ?>
            </div>
            <div class="col-md-5">
                <label for="ubicacion" class="form-label">Ubicación o puesto <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <input type="text" class="form-control" id="ubicacion" name="ubicacion" maxlength="100" value="<?= esc($abierto === 'alta' ? (string) old('ubicacion') : '', 'attr') ?>">
            </div>
            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <input type="text" class="form-control" id="observaciones" name="observaciones" maxlength="300" value="<?= esc($abierto === 'alta' ? (string) old('observaciones') : '', 'attr') ?>">
            </div>
        </div>
        <div class="text-end mt-3"><button type="submit" class="btn btn-primary">Registrar caja</button></div>
    </form>
</details>

<?php if ($cajas === []): ?>
    <div class="hoja p-4"><p class="mb-0">Todavía no hay cajas registradas.</p></div>
<?php endif ?>
<?php foreach ($cajas as $c): $id = (int) $c['id']; $habilitada = $c['estado'] === 'Habilitada'; ?>
    <section class="hoja p-4 mb-3" aria-labelledby="caja-<?= $id ?>">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h5 mb-1" id="caja-<?= $id ?>"><?= esc($c['codigo']) ?> · <?= esc($c['denominacion']) ?></h2>
                <p class="small mb-0"><?= $c['ubicacion'] ? esc($c['ubicacion']) . ' · ' : '' ?><span class="badge rounded-pill <?= $habilitada ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= esc($c['estado']) ?></span>
                    <?php if ($c['jornada_id']): ?> <span class="badge rounded-pill text-bg-warning">Jornada abierta desde <?= esc(formato_fecha_hora($c['apertura'])) ?></span><?php endif ?></p>
                <p class="mb-0 mt-2"><?php if ($c['asignacion_id']): ?>Asignada a <strong><?= esc($c['cajero']) ?></strong> desde el <?= esc(formato_fecha($c['desde'])) ?><?= $c['hasta'] ? ' hasta el ' . esc(formato_fecha($c['hasta'])) : ', sin fecha de fin' ?>.<?php else: ?>Sin asignación vigente.<?php endif ?></p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-3">
            <?php if ($habilitada && !$c['asignacion_id'] && tiene_permiso('cajas.asignar-cajero')): ?>
                <details<?= $abierto === "asignar-{$id}" ? ' open' : '' ?>>
                    <summary class="btn btn-sm btn-primary">Asignar a un Cajero</summary>
                    <form method="post" action="<?= site_url("admin/cajas/{$id}/asignar") ?>" class="row g-2 mt-2" style="max-width: 40rem" novalidate>
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label class="form-label small" for="cajero-<?= $id ?>">Cajero</label>
                            <select class="form-select form-select-sm<?= $abierto === "asignar-{$id}" ? campo_invalido('cajero_id') : '' ?>" id="cajero-<?= $id ?>" name="cajero_id" required>
                                <option value="">Seleccione…</option>
                                <?php foreach ($libres as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['nombre']) ?></option><?php endforeach ?>
                            </select>
                            <?= $abierto === "asignar-{$id}" ? error_campo('cajero_id') : '' ?>
                            <?php if ($libres === []): ?><div class="form-text">No hay Cajeros sin caja asignada.</div><?php endif ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="desde-<?= $id ?>">Desde</label>
                            <input type="text" class="form-control form-control-sm<?= $abierto === "asignar-{$id}" ? campo_invalido('desde') : '' ?>" id="desde-<?= $id ?>" name="desde" placeholder="dd/mm/aaaa" value="<?= esc(formato_fecha(date('Y-m-d')), 'attr') ?>">
                            <?= $abierto === "asignar-{$id}" ? error_campo('desde') : '' ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="hasta-<?= $id ?>">Hasta <span class="text-body-secondary">(opcional)</span></label>
                            <input type="text" class="form-control form-control-sm<?= $abierto === "asignar-{$id}" ? campo_invalido('hasta') : '' ?>" id="hasta-<?= $id ?>" name="hasta" placeholder="dd/mm/aaaa">
                            <?= $abierto === "asignar-{$id}" ? error_campo('hasta') : '' ?>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary">Confirmar asignación</button></div>
                    </form>
                </details>
            <?php endif ?>
            <?php if ($c['asignacion_id'] && tiene_permiso('cajas.asignar-cajero')): ?>
                <details<?= $abierto === "finalizar-{$id}" ? ' open' : '' ?>>
                    <summary class="btn btn-sm btn-outline-secondary">Finalizar la asignación</summary>
                    <form method="post" action="<?= site_url("admin/cajas/{$id}/finalizar-asignacion") ?>" class="mt-2" style="max-width: 32rem" novalidate>
                        <?= csrf_field() ?>
                        <label class="form-label small" for="motivo-fin-<?= $id ?>">Motivo</label>
                        <input type="text" class="form-control form-control-sm<?= $abierto === "finalizar-{$id}" ? campo_invalido('motivo_fin') : '' ?>" id="motivo-fin-<?= $id ?>" name="motivo_fin" maxlength="300" required>
                        <?= $abierto === "finalizar-{$id}" ? error_campo('motivo_fin') : '' ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary mt-2">Finalizar</button>
                    </form>
                </details>
            <?php endif ?>
            <?php if (tiene_permiso('cajas.cambiar-estado')): ?>
                <details<?= $abierto === "estado-{$id}" ? ' open' : '' ?>>
                    <summary class="btn btn-sm <?= $habilitada ? 'btn-outline-danger' : 'btn-outline-secondary' ?>"><?= $habilitada ? 'Inhabilitar' : 'Rehabilitar' ?></summary>
                    <form method="post" action="<?= site_url("admin/cajas/{$id}/estado") ?>" class="mt-2" style="max-width: 32rem" novalidate data-confirmar="<?= $habilitada ? '¿Inhabilita la caja? Se finalizará su asignación vigente.' : '¿Rehabilita la caja?' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="<?= $habilitada ? 'inhabilitar' : 'rehabilitar' ?>">
                        <label class="form-label small" for="motivo-estado-<?= $id ?>">Motivo</label>
                        <input type="text" class="form-control form-control-sm<?= $abierto === "estado-{$id}" ? campo_invalido('motivo') : '' ?>" id="motivo-estado-<?= $id ?>" name="motivo" maxlength="300" required>
                        <?= $abierto === "estado-{$id}" ? error_campo('motivo') : '' ?>
                        <?php if ($habilitada): ?><div class="form-text">La asignación vigente se finaliza y la caja no podrá abrirse. Sus jornadas anteriores se conservan.</div><?php endif ?>
                        <button type="submit" class="btn btn-sm <?= $habilitada ? 'btn-danger' : 'btn-primary' ?> mt-2"><?= $habilitada ? 'Inhabilitar caja' : 'Rehabilitar caja' ?></button>
                    </form>
                </details>
            <?php endif ?>
        </div>
    </section>
<?php endforeach ?>
<?php $this->endSection() ?>
