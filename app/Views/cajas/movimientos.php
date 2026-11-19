<?php
/**
 * CU-32 Consultar Movimientos de Caja, pasos 2 a 4 (A1, A3).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $f
 * @var list<array<string, mixed>> $jornadas
 * @var list<array<string, mixed>> $cajas
 * @var list<array<string, mixed>> $cajeros vacío para el Cajero, que solo ve sus jornadas
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Movimientos de caja</h1>
        <p>Jornadas con sus cobros por medio de pago y la conciliación del cierre (RN-32).<?= $cajeros === [] ? ' Se muestran solo sus jornadas.' : '' ?></p>
    </div>
</div>
<form method="get" action="<?= url_to('cajas.consultar-movimientos') ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar jornadas">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="caja" class="form-label small">Caja</label>
            <select class="form-select" id="caja" name="caja"><option value="">Todas</option>
                <?php foreach ($cajas as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $f['caja_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= esc($c['codigo'] . ' · ' . $c['denominacion']) ?></option><?php endforeach ?>
            </select>
        </div>
        <?php if ($cajeros !== []): ?>
            <div class="col-md-3">
                <label for="cajero" class="form-label small">Cajero</label>
                <select class="form-select" id="cajero" name="cajero"><option value="">Todos</option>
                    <?php foreach ($cajeros as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (int) $f['cajero_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= esc($u['nombre']) ?></option><?php endforeach ?>
                </select>
            </div>
        <?php endif ?>
        <div class="col-6 col-md-3 col-xl-2"><label for="fecha" class="form-label small">Fecha</label><input type="date" class="form-control" id="fecha" name="fecha" value="<?= esc(fecha_campo($f['fecha_texto']), 'attr') ?>"></div>
        <div class="col-md-2">
            <label for="estado" class="form-label small">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (['Abierta' => 'Abierta', 'Cerrada' => 'Cerrada'] as $k => $n): ?><option value="<?= $k ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?>
            </select>
        </div>
        <?= acciones_filtro(limpiar: current_url()) ?>
    </div>
</form>
<?php if ($jornadas === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay jornadas que coincidan con los criterios (A1).</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Caja</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th class="text-end">Cobrado</th><th>Conciliación</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($jornadas as $j): ?>
                    <tr>
                        <td><?= esc($j['codigo']) ?></td>
                        <td><?= esc($j['cajero']) ?></td>
                        <td><?= esc(formato_fecha_hora($j['apertura'])) ?></td>
                        <td><?= $j['cierre'] ? esc(formato_fecha_hora($j['cierre'])) : '<span class="badge text-bg-success">Abierta</span>' ?></td>
                        <td class="importe"><?= esc(formato_importe($j['total_cobrado'], false)) ?></td>
                        <td><?= $j['estado'] === 'Abierta' ? '—' : ((int) $j['medios_con_diferencia'] > 0 ? '<span class="badge text-bg-warning">Con diferencias</span>' : (($j['diferencia'] ?? null) !== null && (float) $j['diferencia'] !== 0.0 ? '<span class="badge text-bg-warning">Arqueo con diferencia</span>' : '<span class="badge text-bg-secondary">Sin diferencias</span>')) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/cajas/jornadas/' . (int) $j['id']) ?>">Detalle</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
