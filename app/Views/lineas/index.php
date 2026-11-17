<?php
/**
 * CU-20 Localizar Línea de Crédito.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $criterios
 * @var list<array<string, mixed>> $lineas
 * @var array<string, string> $categorias
 */
use App\Libraries\Lineas\ValidadorLinea;

$c = static fn (string $k): string => $criterios[$k] ?? '';
$filtrado = array_filter($criterios, static fn ($v) => $v !== '') !== [];
$colores = ['Activa' => 'text-bg-success', 'Suspendida' => 'text-bg-warning', 'Vencida' => 'text-bg-secondary'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Líneas de crédito</h1>
        <p>Productos crediticios con sus condiciones, límites de importe y vigencia.</p>
    </div>
    <?php if (tiene_permiso('lineas.registrar')): ?>
        <a href="<?= url_to('lineas.registrar') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Registrar línea</a>
    <?php endif ?>
</div>

<?= view('partials/boton_filtros', ['id' => 'filtros-lineas', 'que' => 'líneas', 'abierto' => $filtrado]) ?>
<form method="get" action="<?= url_to('lineas.localizar') ?>" class="hoja p-3 mb-3 collapse d-md-block<?= $filtrado ? ' show' : '' ?>" id="filtros-lineas" role="search" aria-label="Buscar líneas">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label for="texto" class="form-label small">Nombre o identificación</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($c('texto'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="estado" class="form-label small">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (['Activa', 'Suspendida', 'Vencida'] as $e): ?><option<?= $c('estado') === $e ? ' selected' : '' ?>><?= $e ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="categoria" class="form-label small">Categoría de ingresos admitida</label>
            <select class="form-select" id="categoria" name="categoria">
                <option value="">Todas</option>
                <?php foreach ($categorias as $k => $t): ?><option value="<?= $k ?>"<?= $c('categoria') === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2 justify-content-md-end">
            <a href="<?= url_to('lineas.localizar') ?>" class="btn btn-outline-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Buscar</span></button>
        </div>
    </div>
</form>

<?php if ($lineas === []): ?>
    <div class="hoja p-4"><p class="mb-0"><?= $filtrado ? 'No hay líneas que coincidan con la búsqueda. Pruebe con otros criterios.' : 'Todavía no hay líneas registradas.' ?></p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0" data-tabla data-sin-busqueda>
            <thead><tr><th>Línea</th><th>Identificación</th><th class="text-end">Importe mínimo</th><th class="text-end">Importe máximo</th><th class="text-end">TNA</th><th>Cuotas</th><th>Vigencia</th><th>Versión</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($lineas as $l): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= site_url('admin/lineas/' . $l['id']) ?>"><?= esc($l['nombre']) ?></a></td>
                    <td><code><?= esc($l['codigo']) ?></code></td>
                    <td class="importe"><?= $l['monto_minimo'] !== null ? formato_importe((string) $l['monto_minimo']) : '' ?></td>
                    <td class="importe"><?= $l['monto_maximo'] !== null ? formato_importe((string) $l['monto_maximo']) : '' ?></td>
                    <td class="text-end"><?= $l['tna'] !== null ? esc(ValidadorLinea::aPorcentaje((string) $l['tna'])) . ' %' : '' ?></td>
                    <td><?= $l['cuotas_minimas'] !== null ? (int) $l['cuotas_minimas'] . ' a ' . (int) $l['cuotas_maximas'] : '' ?></td>
                    <td><?= $l['vigencia_inicio'] !== null ? esc(formato_fecha((string) $l['vigencia_inicio']) . ' al ' . formato_fecha((string) $l['vigencia_fin'])) : '' ?></td>
                    <td><?= (int) $l['version'] ?></td>
                    <td><span class="badge rounded-pill <?= $colores[$l['estado']] ?? 'text-bg-secondary' ?>"><?= esc($l['estado']) ?></span></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
