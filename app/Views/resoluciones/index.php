<?php
/**
 * CU-26 pasos 1 y 2: solicitudes pendientes de resolución del Gerente.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $solicitudes
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Solicitudes para resolver</h1>
        <p>Derivadas al procedimiento convencional: requieren análisis gerencial, contingencia, falta de alternativas o de fondos (RN-22).</p>
    </div>
</div>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay solicitudes pendientes de resolución.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0" data-tabla>
            <thead><tr><th>N.º</th><th>Cliente</th><th>Línea</th><th class="text-end">Importe pedido</th><th>Cuotas</th><th>Desde</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= site_url('admin/solicitudes/' . (int) $s['id']) ?>"><?= (int) $s['numero'] ?></a></td>
                    <td><?= esc($s['denominacion']) ?></td>
                    <td><?= esc($s['linea']) ?></td>
                    <td class="importe"><?= esc(formato_importe($s['importe'])) ?></td>
                    <td><?= (int) $s['cantidad_cuotas'] ?></td>
                    <td><?= esc(formato_fecha_hora($s['ultimo_movimiento'])) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= site_url('admin/solicitudes/' . (int) $s['id'] . '/resolver') ?>">Resolver</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
