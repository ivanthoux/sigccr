<?php
/**
 * CU-100 Configurar Catálogo de Documentación (D-25): tipos de documento del legajo y documentación base que se
 * propone para cada categoría de ingresos al configurar una línea.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array{id: int, codigo: string, nombre: string, aplica_a: string, es_clave: int, activo: int, usos: int}> $tipos
 * @var array<string, list<string>> $sugeridos
 */
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Services\Comunes\DocumentacionService;

$abierto = (string) (session('abierto') ?? '');
$enviado = old('sugeridos') !== null;
$marcados = $enviado ? (array) old('sugeridos') : $sugeridos;
$activos = array_values(array_filter($tipos, static fn ($t) => $t['activo'] === 1));
$identificacion = array_merge(...array_values(RequisitosDocumentales::IDENTIFICACION));
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Documentación exigible</h1>
        <p>Tipos de documento del legajo y documentación que se propone para cada categoría de ingresos. Cada línea define, en su versión de condiciones, la que exige (RN-11).</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#nuevo-tipo" aria-expanded="<?= $abierto === 'nuevo' ? 'true' : 'false' ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo tipo de documento</button>
</div>

<form method="post" action="<?= site_url('admin/documentacion/tipos') ?>" id="nuevo-tipo" class="hoja p-4 mb-3 collapse<?= $abierto === 'nuevo' ? ' show' : '' ?>" novalidate>
    <?= csrf_field() ?>
    <h2 class="h5">Nuevo tipo de documento</h2>
    <div class="row g-3 align-items-end">
        <div class="col-md-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control<?= $abierto === 'nuevo' ? campo_invalido('nombre') : '' ?>" id="nombre" name="nombre" maxlength="120" placeholder="Por ejemplo: certificado de servicios del empleador" value="<?= esc($abierto === 'nuevo' ? (string) old('nombre') : '', 'attr') ?>">
            <?= $abierto === 'nuevo' ? error_campo('nombre') : '' ?>
        </div>
        <div class="col-md-3">
            <label for="aplica_a" class="form-label">Corresponde a</label>
            <select class="form-select<?= $abierto === 'nuevo' ? campo_invalido('aplica_a') : '' ?>" id="aplica_a" name="aplica_a">
                <?php foreach (DocumentacionService::APLICA_A as $k => $t): ?><option value="<?= $k ?>"<?= ($abierto === 'nuevo' ? old('aplica_a') : 'PersonaHumana') === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
            </select>
            <?= $abierto === 'nuevo' ? error_campo('aplica_a') : '' ?>
        </div>
        <div class="col-md-3">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="es_clave" name="es_clave" value="1"<?= $abierto === 'nuevo' && old('es_clave') ? ' checked' : '' ?>>
                <label class="form-check-label" for="es_clave">Documento clave</label>
            </div>
        </div>
    </div>
    <p class="small text-body-secondary mt-2 mb-3">Un documento clave invalida la evaluación crediticia vigente si se reemplaza (RN-61). El código se genera a partir del nombre.</p>
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#nuevo-tipo">Cancelar</button>
        <button type="submit" class="btn btn-primary">Registrar tipo</button>
    </div>
</form>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-tipos">
    <h2 class="h5" id="titulo-tipos">Tipos de documento</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Nombre</th><th>Código</th><th>Corresponde a</th><th>Clave</th><th class="text-end">En legajos</th><th>Estado</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($tipos as $t): $id = $t['id']; $este = $abierto === "tipo-{$id}"; ?>
                <tr<?= $t['activo'] === 1 ? '' : ' class="text-body-secondary"' ?>>
                    <td><?= esc($t['nombre']) ?></td>
                    <td class="small"><code><?= esc($t['codigo']) ?></code></td>
                    <td class="small"><?= esc(DocumentacionService::APLICA_A[$t['aplica_a']] ?? $t['aplica_a']) ?></td>
                    <td><?= $t['es_clave'] === 1 ? 'Sí' : 'No' ?></td>
                    <td class="text-end"><?= $t['usos'] ?></td>
                    <td><?= $t['activo'] === 1 ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editar-<?= $id ?>" aria-expanded="<?= $este ? 'true' : 'false' ?>">Modificar</button></td>
                </tr>
                <tr class="collapse<?= $este ? ' show' : '' ?>" id="editar-<?= $id ?>">
                    <td colspan="7" class="bg-body-tertiary">
                        <form method="post" action="<?= site_url("admin/documentacion/tipos/{$id}") ?>" class="row g-2 align-items-end py-2" novalidate>
                            <?= csrf_field() ?>
                            <div class="col-md-5">
                                <label class="form-label small" for="nombre-<?= $id ?>">Nombre</label>
                                <input type="text" class="form-control form-control-sm<?= $este ? campo_invalido('nombre') : '' ?>" id="nombre-<?= $id ?>" name="nombre" maxlength="120" value="<?= esc($este ? (string) old('nombre') : $t['nombre'], 'attr') ?>">
                                <?= $este ? error_campo('nombre') : '' ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="aplica-<?= $id ?>">Corresponde a</label>
                                <select class="form-select form-select-sm<?= $este ? campo_invalido('aplica_a') : '' ?>" id="aplica-<?= $id ?>" name="aplica_a"<?= $t['usos'] > 0 ? ' title="Hay documentos de este tipo en legajos"' : '' ?>>
                                    <?php foreach (DocumentacionService::APLICA_A as $k => $n): ?><option value="<?= $k ?>"<?= ($este ? old('aplica_a') : $t['aplica_a']) === $k ? ' selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
                                </select>
                                <?= $este ? error_campo('aplica_a') : '' ?>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="clave-<?= $id ?>" name="es_clave" value="1"<?= ($este ? old('es_clave') : $t['es_clave']) ? ' checked' : '' ?>><label class="form-check-label small" for="clave-<?= $id ?>">Clave</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="activo-<?= $id ?>" name="activo" value="1"<?= ($este ? old('activo') : $t['activo']) ? ' checked' : '' ?><?= in_array($t['codigo'], $identificacion, true) ? ' disabled' : '' ?>><label class="form-check-label small" for="activo-<?= $id ?>">Activo</label></div>
                                <?php if (in_array($t['codigo'], $identificacion, true)): ?><input type="hidden" name="activo" value="1"><?php endif ?>
                            </div>
                            <div class="col-md-2 text-end"><button type="submit" class="btn btn-sm btn-primary">Guardar</button></div>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="small text-body-secondary mt-2 mb-0">Los tipos no se eliminan: un tipo inactivo deja de ofrecerse para nuevas exigencias y nuevos documentos, pero las líneas y los legajos que ya lo usan lo conservan. La identificación del cliente no se puede desactivar (D-14).</p>
</section>

<form method="post" action="<?= site_url('admin/documentacion/sugeridos') ?>" class="hoja p-4" novalidate>
    <?= csrf_field() ?>
    <h2 class="h5">Documentación que se propone por categoría de ingresos</h2>
    <p class="small text-body-secondary">Al registrar o modificar una línea, cada categoría que se admite trae marcada esta documentación; en la línea se puede quitar o agregar. Debe incluir la identificación del cliente.</p>
    <div class="row g-3">
        <?php foreach (RequisitosDocumentales::CATEGORIAS as $tipoCliente => $cats): ?>
            <?php foreach ($cats as $cat => $nombreCat): ?>
                <div class="col-md-6 col-xl-4">
                    <fieldset class="border rounded p-3 h-100<?= campo_invalido("sugeridos_{$cat}") !== '' ? ' border-danger' : '' ?>">
                        <legend class="h6"><?= esc($nombreCat) ?></legend>
                        <?php foreach ($activos as $t): ?>
                            <?php if (!in_array($t['aplica_a'], ['Ambos', $tipoCliente], true)) {
                                continue;
                            } ?>
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" id="sug-<?= $cat ?>-<?= $t['id'] ?>" name="sugeridos[<?= $cat ?>][]" value="<?= esc($t['codigo'], 'attr') ?>"<?= in_array($t['codigo'], (array) ($marcados[$cat] ?? []), true) ? ' checked' : '' ?>>
                                <label class="form-check-label" for="sug-<?= $cat ?>-<?= $t['id'] ?>"><?= esc($t['nombre']) ?></label>
                            </div>
                        <?php endforeach ?>
                        <?= error_campo("sugeridos_{$cat}") ?>
                    </fieldset>
                </div>
            <?php endforeach ?>
        <?php endforeach ?>
    </div>
    <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary">Guardar la documentación propuesta</button></div>
</form>
<?php $this->endSection() ?>
