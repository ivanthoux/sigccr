<?php
/**
 * CU-97 Registrar Cancelación Anticipada, paso 2 (A1, A2): créditos activos o en mora del cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $creditos
 * @var array<string, mixed>|null $jornada
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('creditos.cancelar-anticipadamente') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Cancelación anticipada</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?></p>
    </div>
</div>
<?php if ($jornada === null): ?>
    <div class="alert alert-warning">No tiene una caja abierta hoy: <a href="<?= url_to('cajas.abrir') ?>">ábrala</a> para registrar la cancelación (A1, CU-31).</div>
<?php endif ?>
<div class="hoja p-3">
    <?php if ($creditos === []): ?>
        <p class="mb-0">El cliente no tiene créditos activos ni en mora (A2).</p>
    <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Crédito</th><th>Línea</th><th>Origen</th><th class="text-end">Capital</th><th class="text-end">Cuotas pendientes</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($creditos as $c): ?>
                <tr>
                    <td class="fw-medium"><?= (int) $c['numero'] ?></td>
                    <td><?= esc($c['linea']) ?></td>
                    <td><?= $c['origen'] === 'Refinanciacion' ? 'Refinanciación' : 'Liquidación' ?> del <?= esc(formato_fecha_hora($c['fecha_liquidacion'])) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $c['capital'], false)) ?></td>
                    <td class="text-end"><?= (int) $c['cuotas_pendientes'] ?> de <?= (int) $c['cantidad_cuotas'] ?></td>
                    <td><?= $c['estado'] === 'EnMora' ? 'En mora' : 'Activo' ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/creditos/' . (int) $c['id'] . '/cancelacion-anticipada') ?>">Calcular</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
