<?php
/**
 * CU-103 paso 1: solicitudes aprobadas pendientes de liquidación con el estado de su contrato.
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
        <h1>Contratos de préstamo</h1>
        <p>Contrato de cada solicitud aprobada para imprimir y firmar, y constancia firmada para el legajo. Sin ella no se liquida (RN-25, RN-78).</p>
    </div>
</div>
<form method="post" action="<?= url_to('contratos.registrar-firmado') ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar solicitudes aprobadas">
    <?= csrf_field() ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-8 col-lg-6">
            <label for="texto" class="form-label small">Número de solicitud, cliente o CUIT/CUIL</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($texto, 'attr') ?>">
        </div>
        <?= acciones_filtro() ?>
    </div>
</form>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay solicitudes aprobadas pendientes de liquidación<?= $texto !== '' ? ' que coincidan' : '' ?>.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>N.º</th><th>Cliente</th><th>Línea</th><th class="text-end">Aprobado</th><th>Vence la aprobación</th><th>Constancia firmada</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td class="fw-medium"><?= (int) $s['numero'] ?></td>
                        <td><?= esc($s['denominacion']) ?></td>
                        <td><?= esc($s['linea']) ?></td>
                        <td class="importe"><?= esc(formato_importe($s['importe_aprobado'])) ?></td>
                        <td><?= esc(formato_fecha_hora($s['vence_aprobacion'])) ?></td>
                        <td><?= $s['constancia'] ? '<span class="badge text-bg-success">Recibida el ' . esc(formato_fecha($s['constancia'])) . '</span>' : '<span class="badge text-bg-warning">Pendiente</span>' ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/contratos/' . (int) $s['id']) ?>">Contrato</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
