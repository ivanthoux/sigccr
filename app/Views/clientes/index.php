<?php
/**
 * CU-54 Localizar Cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $criterios
 * @var bool $buscado
 * @var list<array<string, mixed>> $clientes
 */
$c = static fn (string $k): string => $criterios[$k] ?? '';
$puedeVer = tiene_permiso('clientes.consultar-perfil');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Clientes</h1>
        <p>Padrón de personas humanas y jurídicas de la entidad.</p>
    </div>
    <?php if (tiene_permiso('clientes.registrar')): ?>
        <a href="<?= url_to('clientes.registrar') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Registrar cliente</a>
    <?php endif ?>
</div>

<form method="get" action="<?= url_to('clientes.localizar') ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar clientes">
    <div class="row g-2 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label for="texto" class="form-label small">Nombre, apellido o razón social</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($c('texto'), 'attr') ?>" autofocus>
        </div>
        <div class="col-md-3 col-lg-2">
            <label for="documento" class="form-label small">Documento</label>
            <input type="text" class="form-control" id="documento" name="documento" inputmode="numeric" value="<?= esc($c('documento'), 'attr') ?>">
        </div>
        <div class="col-md-3 col-lg-2">
            <label for="cuit" class="form-label small">CUIT/CUIL</label>
            <input type="text" class="form-control" id="cuit" name="cuit" inputmode="numeric" value="<?= esc($c('cuit'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="tipo" class="form-label small">Tipo</label>
            <select class="form-select" id="tipo" name="tipo">
                <option value="">Todos</option>
                <option value="PersonaHumana"<?= $c('tipo') === 'PersonaHumana' ? ' selected' : '' ?>>Persona humana</option>
                <option value="PersonaJuridica"<?= $c('tipo') === 'PersonaJuridica' ? ' selected' : '' ?>>Persona jurídica</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="estado" class="form-label small">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <option value="Activo"<?= $c('estado') === 'Activo' ? ' selected' : '' ?>>Activo</option>
                <option value="Desactivado"<?= $c('estado') === 'Desactivado' ? ' selected' : '' ?>>Desactivado</option>
            </select>
        </div>
        <div class="col-md-6 col-lg-12 d-flex gap-2 justify-content-lg-end">
            <a href="<?= url_to('clientes.localizar') ?>" class="btn btn-outline-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
        </div>
    </div>
</form>

<?php if (!$buscado): ?>
    <div class="hoja p-4"><p class="mb-0 text-body-secondary">Ingrese al menos un criterio para buscar en el padrón.</p></div>
<?php elseif ($clientes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay clientes que coincidan con la búsqueda. Revise los datos o pruebe con menos criterios.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0" data-tabla>
            <thead><tr><th>Cliente</th><th>Tipo</th><th>Documento</th><th>CUIT/CUIL</th><th>Correo electrónico</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $cl): ?>
                <tr>
                    <td><?= $puedeVer ? '<a class="fw-medium" href="' . site_url('admin/clientes/' . $cl['id']) . '">' . esc($cl['denominacion']) . '</a>' : esc($cl['denominacion']) ?></td>
                    <td><?= $cl['tipo_cliente'] === 'PersonaJuridica' ? 'Jurídica' : 'Humana' ?></td>
                    <td><?= $cl['nro_documento'] ? esc($cl['tipo_documento'] . ' ' . $cl['nro_documento']) : '' ?></td>
                    <td><?= esc(formato_cuit($cl['cuit_cuil'])) ?></td>
                    <td><?= esc((string) $cl['correo']) ?></td>
                    <td><span class="badge rounded-pill <?= $cl['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $cl['estado'] === 'Activo' ? 'Activo' : 'Desactivado' ?></span></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
