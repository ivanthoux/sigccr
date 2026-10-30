<?php
/**
 * CU-21 y CU-22 pasos 1 y 2: búsqueda del cliente (CU-54) para simular o registrar una solicitud.
 *
 * @var CodeIgniter\View\View $this
 * @var string $texto
 * @var list<array<string, mixed>> $clientes
 * @var string $destino nombre de la ruta a la que continúa
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1><?= esc($titulo) ?></h1>
        <p><?= $destino === 'solicitudes.simular' ? 'Plan preliminar con las condiciones vigentes de una línea. No genera solicitud ni deuda (RN-17).' : 'Formalice una simulación vigente del cliente.' ?></p>
    </div>
</div>
<form method="get" action="<?= url_to($destino) ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar cliente">
    <div class="row g-2 align-items-end">
        <div class="col-md-8 col-lg-6">
            <label for="texto" class="form-label small">Cliente: nombre, apellido o razón social</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($texto, 'attr') ?>" autofocus required minlength="2">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button></div>
    </div>
</form>
<?php if ($texto !== ''): ?>
    <?php if ($clientes === []): ?>
        <div class="hoja p-4"><p class="mb-0">No hay clientes activos que coincidan. Revise el nombre o búsquelo en <a href="<?= url_to('clientes.localizar') ?>">Clientes</a>.</p></div>
    <?php else: ?>
        <div class="hoja p-3">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Cliente</th><th>Documento</th><th>CUIT/CUIL</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($clientes as $cl): ?>
                    <tr>
                        <td class="fw-medium"><?= esc($cl['denominacion']) ?></td>
                        <td><?= $cl['nro_documento'] ? esc($cl['tipo_documento'] . ' ' . $cl['nro_documento']) : '' ?></td>
                        <td><?= esc(formato_cuit($cl['cuit_cuil'])) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url_to($destino) ?>?cliente=<?= (int) $cl['id'] ?>">Seleccionar</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
