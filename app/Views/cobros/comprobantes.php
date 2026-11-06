<?php
/**
 * CU-35 Emitir Comprobante de Pago, pasos 1 a 3 (A2): búsqueda de cobros por comprobante, cliente o fecha.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $f
 * @var list<array<string, mixed>>|null $cobros
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Comprobantes de pago</h1>
        <p>Consulta, impresión y reemisión de comprobantes. La reemisión no genera un nuevo cobro (RN-31).</p>
    </div>
</div>
<form method="get" action="<?= url_to('cobros.emitir-comprobante') ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar cobros">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label for="numero" class="form-label small">N.º de comprobante o de cobro</label><input type="text" class="form-control" id="numero" name="numero" inputmode="numeric" value="<?= esc((string) $f['numero'], 'attr') ?>"></div>
        <div class="col-md-4"><label for="texto" class="form-label small">Cliente o CUIT/CUIL</label><input type="search" class="form-control" id="texto" name="texto" value="<?= esc((string) $f['texto'], 'attr') ?>"></div>
        <div class="col-md-3"><label for="fecha" class="form-label small">Fecha del cobro</label><input type="text" class="form-control" id="fecha" name="fecha" placeholder="dd/mm/aaaa" value="<?= esc((string) $f['fecha_texto'], 'attr') ?>"></div>
        <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button></div>
    </div>
</form>
<?php if ($cobros !== null): ?>
    <?php if ($cobros === []): ?>
        <div class="hoja p-4"><p class="mb-0">No hay cobros que coincidan con la búsqueda (A2).</p></div>
    <?php else: ?>
        <div class="hoja p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Comprobante</th><th>Cobro</th><th>Fecha</th><th>Cliente</th><th class="text-end">Importe</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cobros as $b): ?>
                        <tr>
                            <td><?= (int) $b['comprobante'] ?></td>
                            <td><?= (int) $b['numero'] ?></td>
                            <td><?= esc(formato_fecha_hora($b['fecha_hora'])) ?></td>
                            <td><?= esc($b['denominacion']) ?></td>
                            <td class="importe"><?= esc(formato_importe($b['importe'], false)) ?></td>
                            <td><?= $b['estado'] === 'Anulado' ? '<span class="badge text-bg-danger">Anulado</span>' : '<span class="badge text-bg-success">Confirmado</span>' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/cobros/' . (int) $b['id'] . '/comprobante') ?>">Ver</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
