<?php
/**
 * Campos para adjuntar documentación al legajo: uno por tipo, con el nombre documentos[<código>][] que espera
 * BaseController::archivosLegajo(). Los exigidos se marcan como obligatorios cuando faltan en el legajo.
 *
 * @var list<array{id: int, codigo: string, nombre: string, aplica_a: string}> $tipos
 * @var string $tipoCliente
 * @var list<string> $exigidos
 * @var list<string> $presentes
 * @var array<string, int> $reemplazos código => documento observado que se reemplaza
 */
$reemplazos ??= [];
?>
<div class="row g-3">
    <?php foreach ($tipos as $t): if (!in_array($t['aplica_a'], ['Ambos', $tipoCliente], true)) { continue; }
        $exigido = in_array($t['codigo'], $exigidos, true);
        $presente = in_array($t['codigo'], $presentes, true); ?>
        <div class="col-md-6">
            <label for="doc-<?= esc($t['codigo'], 'attr') ?>" class="form-label"><?= esc($t['nombre']) ?>
                <?php if ($exigido && !$presente): ?><span class="badge text-bg-warning">Obligatorio</span>
                <?php elseif ($exigido): ?><span class="badge text-bg-light border">Exigido · en el legajo</span><?php endif ?>
                <?php if (isset($reemplazos[$t['codigo']])): ?><span class="badge text-bg-warning">Reemplaza el observado</span><?php endif ?></label>
            <input type="file" class="form-control<?= campo_invalido('doc_' . $t['codigo']) ?>" id="doc-<?= esc($t['codigo'], 'attr') ?>" name="documentos[<?= esc($t['codigo'], 'attr') ?>][]" accept=".pdf,.jpg,.jpeg,.png" multiple<?= $exigido && !$presente ? ' required' : '' ?>>
            <?php if (isset($reemplazos[$t['codigo']])): ?><input type="hidden" name="reemplaza[<?= esc($t['codigo'], 'attr') ?>]" value="<?= (int) $reemplazos[$t['codigo']] ?>"><?php endif ?>
            <?= error_campo('doc_' . $t['codigo']) ?>
        </div>
    <?php endforeach ?>
</div>
<p class="form-text mb-0">PDF, JPG o PNG de hasta 5 MB cada uno (RNF-12). <?= error_campo('documentos') ?></p>
