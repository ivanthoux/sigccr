<?php
/**
 * CU-27 pasos 1 y 2: solicitudes aprobadas pendientes de liquidación.
 *
 * @var CodeIgniter\View\View $this
 * @var string $texto
 * @var list<array<string, mixed>> $solicitudes
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Liquidar créditos aprobados</h1>
        <p>Solicitudes aprobadas pendientes de desembolso, de la que vence primero a la última (RN-49).</p>
    </div>
</div>
<form method="get" action="<?= url_to('creditos.liquidar') ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar solicitudes aprobadas">
    <div class="row g-2 align-items-end">
        <div class="col-md-8 col-lg-6">
            <label for="texto" class="form-label small">Número de solicitud, cliente o CUIT/CUIL</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($texto, 'attr') ?>">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button></div>
    </div>
</form>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay solicitudes aprobadas pendientes de liquidación<?= $texto !== '' ? ' que coincidan' : '' ?>.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>N.º</th><th>Cliente</th><th>CUIT/CUIL</th><th>Línea</th><th class="text-end">Aprobado</th><th>Cuotas</th><th>Vence la aprobación</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td class="fw-medium"><?= (int) $s['numero'] ?></td>
                    <td><?= esc($s['denominacion']) ?></td>
                    <td><?= esc(formato_cuit($s['cuit_cuil'])) ?></td>
                    <td><?= esc($s['linea']) ?></td>
                    <td class="importe"><?= esc(formato_importe($s['importe_aprobado'])) ?></td>
                    <td><?= (int) $s['cuotas_aprobadas'] ?></td>
                    <td><?= esc(formato_fecha_hora($s['vence_aprobacion'])) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= site_url('admin/liquidaciones/' . (int) $s['id']) ?>">Liquidar</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
