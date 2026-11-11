<?php
/**
 * CU-101 Actualizar Categorías del Monotributo (D-26).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, array{tope: string, vigente_desde: string}> $vigentes
 * @var list<array{vigente_desde: string, topes: array<string, string>}> $historial
 */
use App\Libraries\Clientes\CategoriasMonotributo;

$hoy = hoy_negocio();
$importe = static fn (string $v): string => str_replace('$ ', '', formato_importe($v));
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Categorías del monotributo</h1>
        <p>Topes de ingresos brutos anuales de cada categoría. ARCA los actualiza cada semestre: se registra la nueva escala con la fecha desde la que rige. Se usan para verificar que los ingresos declarados por un monotributista correspondan a su categoría.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-vigente">
            <h2 class="h5" id="titulo-vigente">Escala vigente</h2>
            <?php if ($vigentes === []): ?>
                <p class="mb-0">No hay una escala vigente. Registre la de ARCA.</p>
            <?php else: ?>
                <p class="small text-body-secondary">Desde el <?= esc(formato_fecha(reset($vigentes)['vigente_desde'])) ?>.</p>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Categoría</th><th class="text-end">Ingresos brutos anuales hasta</th></tr></thead>
                    <tbody>
                    <?php foreach ($vigentes as $c => $e): ?><tr><td><?= $c ?></td><td class="importe"><?= esc(formato_importe($e['tope'])) ?></td></tr><?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </section>
    </div>
    <div class="col-lg-7">
        <form method="post" action="<?= url_to('monotributo.actualizar') ?>" class="hoja p-4 h-100" novalidate>
            <?= csrf_field() ?>
            <h2 class="h5">Registrar una nueva escala</h2>
            <div class="row g-2">
                <div class="col-md-5 mb-2">
                    <label for="vigente_desde" class="form-label">Rige desde</label>
                    <input type="date" class="form-control<?= campo_invalido('vigente_desde') ?>" id="vigente_desde" name="vigente_desde" min="<?= $hoy ?>" value="<?= esc((string) old('vigente_desde'), 'attr') ?>">
                    <?= error_campo('vigente_desde') ?>
                </div>
                <div class="w-100"></div>
                <?php foreach (CategoriasMonotributo::CATEGORIAS as $c): ?>
                    <div class="col-6 col-md-4">
                        <label for="tope_<?= $c ?>" class="form-label small mb-1">Categoría <?= $c ?></label>
                        <div class="input-group input-group-sm<?= campo_invalido("tope_{$c}") !== '' ? ' has-validation' : '' ?>">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control<?= campo_invalido("tope_{$c}") ?>" id="tope_<?= $c ?>" name="topes[<?= $c ?>]" inputmode="decimal" value="<?= esc((string) (old('topes')[$c] ?? (isset($vigentes[$c]) ? $importe($vigentes[$c]['tope']) : '')), 'attr') ?>">
                            <?= error_campo("tope_{$c}") ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <p class="small text-body-secondary mt-3 mb-2">Los importes se precargan con la escala vigente: reemplácelos por los publicados por ARCA. Cada tope debe ser mayor que el de la categoría anterior.</p>
            <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Registrar escala</button></div>
        </form>
    </div>
</div>

<?php if (count($historial) > 1): ?>
    <section class="hoja p-4 mt-3" aria-labelledby="titulo-historial">
        <h2 class="h5" id="titulo-historial">Escalas registradas</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Rige desde</th><?php foreach (CategoriasMonotributo::CATEGORIAS as $c): ?><th class="text-end"><?= $c ?></th><?php endforeach ?></tr></thead>
                <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr><td class="text-nowrap"><?= esc(formato_fecha($h['vigente_desde'])) ?><?= $h['vigente_desde'] > $hoy ? ' <span class="badge text-bg-info">Próxima</span>' : '' ?></td>
                        <?php foreach (CategoriasMonotributo::CATEGORIAS as $c): ?><td class="importe small"><?= isset($h['topes'][$c]) ? esc($importe($h['topes'][$c])) : '' ?></td><?php endforeach ?></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
