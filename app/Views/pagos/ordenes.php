<?php
/**
 * CU-38 Consultar Estado de Pago Electrónico, pasos 1 a 3 (A4, A5): órdenes por número, cliente o estado.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $filtros
 * @var list<array<string, mixed>> $ordenes
 */
$estados = ['PendienteConfirmacion' => 'Pendiente', 'Confirmada' => 'Confirmada', 'Rechazada' => 'Rechazada', 'EnConciliacion' => 'En conciliación', 'Vencida' => 'Vencida'];
$colores = ['PendienteConfirmacion' => 'text-bg-warning', 'Confirmada' => 'text-bg-success', 'Rechazada' => 'text-bg-danger', 'EnConciliacion' => 'text-bg-info', 'Vencida' => 'text-bg-secondary'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Pagos electrónicos</h1>
        <p>Órdenes de pago y su estado según las notificaciones y consultas a la pasarela (RN-30, RN-54).</p>
    </div>
    <?php if (tiene_permiso('pagos-electronicos.generar-orden')): ?>
        <a class="btn btn-primary" href="<?= url_to('pagos-electronicos.generar-orden') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva orden</a>
    <?php endif ?>
</div>
<form method="post" class="hoja p-3 mb-3 row g-2 align-items-end" role="search" aria-label="Buscar órdenes">
    <?= csrf_field() ?>
    <div class="col-md-2">
        <label for="numero" class="form-label small">N.º de orden</label>
        <input type="text" class="form-control" id="numero" name="numero" inputmode="numeric" value="<?= esc($filtros['numero'], 'attr') ?>">
    </div>
    <div class="col-md-5">
        <label for="texto" class="form-label small">Cliente (denominación o CUIT/CUIL)</label>
        <input type="text" class="form-control" id="texto" name="texto" value="<?= esc($filtros['texto'], 'attr') ?>">
    </div>
    <div class="col-md-3">
        <label for="estado" class="form-label small">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $k => $n): ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
        </select>
    </div>
    <?= acciones_filtro(limpiar: current_url()) ?>
</form>
<div class="hoja p-3">
    <?php if ($ordenes === []): ?>
        <p class="mb-0">No hay órdenes con esos criterios (A4).</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Orden</th><th>Cliente</th><th>Emisión</th><th>Vence</th><th class="text-end">Importe</th><th>Estado</th><th>Cobro</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ordenes as $o): ?>
                    <tr>
                        <td class="fw-medium"><?= (int) $o['numero'] ?></td>
                        <td><?= esc($o['denominacion']) ?><div class="small text-body-secondary"><?= esc(formato_cuit($o['cuit_cuil'])) ?></div></td>
                        <td><?= esc(formato_fecha_hora($o['emision'])) ?></td>
                        <td><?= esc(formato_fecha_hora($o['vence'])) ?></td>
                        <td class="importe"><?= esc(formato_importe($o['importe'], false)) ?></td>
                        <td><span class="badge <?= $colores[$o['estado']] ?? 'text-bg-light' ?>"><?= esc($estados[$o['estado']] ?? $o['estado']) ?></span></td>
                        <td><?= $o['cobro'] !== null ? 'N.º ' . (int) $o['cobro'] : '—' ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/pagos-electronicos/' . (int) $o['id']) ?>">Ver</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
