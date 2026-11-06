<?php
/**
 * CU-25 pasos 1 y 2: solicitudes pendientes de evaluación de contingencia.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $solicitudes
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Evaluaciones de contingencia</h1>
        <p>Solicitudes cuya evaluación automática no pudo completarse (RN-21).</p>
    </div>
</div>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay solicitudes pendientes de evaluación de contingencia.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0" data-tabla>
            <thead><tr><th>N.º</th><th>Cliente</th><th>Línea</th><th class="text-end">Importe</th><th>Último movimiento</th><th><span class="visually-hidden">Acción</span></th></tr></thead>
            <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= site_url('admin/solicitudes/' . (int) $s['id']) ?>"><?= (int) $s['numero'] ?></a></td>
                    <td><?= esc($s['denominacion']) ?></td>
                    <td><?= esc($s['linea']) ?></td>
                    <td class="text-end"><?= esc(formato_importe($s['importe'])) ?></td>
                    <td><?= esc(formato_fecha_hora($s['ultimo_movimiento'])) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= site_url('admin/solicitudes/' . (int) $s['id'] . '/contingencia') ?>">Registrar contingencia</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
