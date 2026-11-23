<?php
/**
 * CU-42 Localizar Propuesta de Refinanciación, pasos 2 a 5 (A1, A2).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $filtros
 * @var list<array<string, mixed>> $propuestas
 * @var array<string, string> $erroresFiltro
 */
use App\Services\Cartera\RefinanciacionService;

$invalido = static fn (string $k): string => isset($erroresFiltro[$k]) ? ' is-invalid' : '';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Propuestas de refinanciación</h1>
        <p>Simulación, propuesta, resolución gerencial, aceptación y aplicación (RN-36, RN-37, RN-70).</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (tiene_permiso('refinanciaciones.simular')): ?><a class="btn btn-outline-primary" href="<?= url_to('refinanciaciones.simular') ?>"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Simular</a><?php endif ?>
        <?php if (tiene_permiso('refinanciaciones.registrar-propuesta')): ?><a class="btn btn-primary" href="<?= url_to('refinanciaciones.registrar-propuesta') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva propuesta</a><?php endif ?>
    </div>
</div>
<form method="post" class="hoja p-3 mb-3 row g-2 align-items-end" role="search" aria-label="Buscar propuestas">
    <?= csrf_field() ?>
    <div class="col-md-2">
        <label for="numero" class="form-label small">N.º de propuesta</label>
        <input type="text" class="form-control<?= $invalido('numero') ?>" id="numero" name="numero" inputmode="numeric" value="<?= esc($filtros['numero'], 'attr') ?>">
    </div>
    <div class="col-md-3">
        <label for="texto" class="form-label small">Cliente, documento o CUIT/CUIL</label>
        <input type="text" class="form-control" id="texto" name="texto" value="<?= esc($filtros['texto'], 'attr') ?>">
    </div>
    <div class="col-md-3">
        <label for="estado" class="form-label small">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (RefinanciacionService::ESTADOS as $k => $n): ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label for="desde" class="form-label small">Registrada desde</label>
        <input type="date" class="form-control<?= $invalido('desde') ?>" id="desde" name="desde" value="<?= esc($filtros['desde'], 'attr') ?>">
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label for="hasta" class="form-label small">Hasta</label>
        <input type="date" class="form-control<?= $invalido('hasta') ?>" id="hasta" name="hasta" value="<?= esc($filtros['hasta'], 'attr') ?>">
    </div>
    <?= acciones_filtro(limpiar: url_to('refinanciaciones.localizar')) ?>
    <?php if ($erroresFiltro !== []): ?><div class="col-12 text-danger small"><?= esc(implode(' ', $erroresFiltro)) ?></div><?php endif ?>
</form>
<div class="hoja p-3">
    <?php if ($propuestas === []): ?>
        <p class="mb-0">No hay propuestas con esos criterios (A1).</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Propuesta</th><th>Cliente</th><th class="text-end">Deuda consolidada</th><th>Registro</th><th>Vigencia</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($propuestas as $p): ?>
                    <tr>
                        <td class="fw-medium"><?= (int) $p['numero'] ?><?= (int) $p['version'] > 1 ? ' <span class="small text-body-secondary">v' . (int) $p['version'] . '</span>' : '' ?></td>
                        <td><?= esc($p['denominacion']) ?><div class="small text-body-secondary"><?= esc(formato_cuit($p['cuit_cuil'])) ?></div></td>
                        <td class="importe"><?= esc(formato_importe(bcadd((string) $p['capital_refinanciado'], (string) $p['concepto_no_capitalizable'], 2), false)) ?></td>
                        <td><?= esc(formato_fecha_hora($p['fecha_registro'])) ?></td>
                        <td><?= in_array($p['estado'], RefinanciacionService::VIGENTES, true) ? ($p['vigente'] ? 'Hasta ' . esc(formato_fecha_hora($p['vence'])) : '<span class="text-danger">Venció</span>') : '—' ?></td>
                        <td><?= view('refinanciaciones/_estado', ['estado' => $p['estado']]) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/refinanciaciones/' . (int) $p['id']) ?>">Ver</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
