<?php
/**
 * CU-64 paso 8 y CU-79 pasos 4 a 6: plan de la alternativa y constancia de conformidad del cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var array<string, mixed> $a
 * @var array<string, mixed>|null $evaluacion
 */
use App\Libraries\Solicitudes\Estructurador;

$id = (int) $s['id'];
$confirmable = $s['estado'] === 'PendienteSeleccionPlan' && (int) $a['vigente'] === 1 && tiene_permiso('alternativas.confirmar');
$medio = (string) old('medio_conformidad');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}/alternativas") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Alternativas de la solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc(formato_importe($a['importe'])) ?> en <?= (int) $a['cantidad_cuotas'] ?> cuotas</h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?> · Alternativa <?= esc(mb_strtolower((string) $a['estado'])) ?><?= (int) $a['vigente'] === 1 ? ', vigente hasta el ' . esc(formato_fecha_hora($a['vence'])) : '' ?></p>
    </div>
</div>
<?php if ($a['estado'] === 'Descartada'): ?><div class="alert alert-secondary">Descartada: <?= esc((string) $a['motivo_descarte']) ?></div><?php endif ?>
<?php if ($a['estado'] === 'Vigente' && (int) $a['vigente'] === 0): ?><div class="alert alert-warning">La alternativa superó su vigencia de 7 días corridos: no se puede confirmar (RN-48).</div><?php endif ?>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-plan">
    <h2 class="h5" id="titulo-plan">Plan preliminar</h2>
    <?= view('partials/plan_preliminar', ['calculo' => $a['calculo'], 'importe' => (string) $a['importe'], 'tna' => (string) $s['tna']]) ?>
</section>

<?php if ($confirmable && $evaluacion !== null): ?>
    <form method="post" action="<?= site_url("admin/solicitudes/{$id}/alternativas/" . (int) $a['id'] . '/confirmar') ?>" class="hoja p-4" novalidate aria-labelledby="titulo-conformidad">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
        <h2 class="h5" id="titulo-conformidad">Conformidad del cliente</h2>
        <p class="small">Registre la constancia solo si el cliente aceptó esta alternativa. El sistema revalida la vigencia, la capacidad de pago, las condiciones de la línea y los fondos; si todo se cumple, aprueba la solicitud automáticamente (RN-23).</p>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="fecha_conformidad" class="form-label">Fecha de la conformidad</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_conformidad') ?>" id="fecha_conformidad" name="fecha_conformidad" value="<?= esc(fecha_campo((string) (old('fecha_conformidad') ?? formato_fecha(hoy_negocio()))), 'attr') ?>" required>
                <?= error_campo('fecha_conformidad') ?>
            </div>
            <div class="col-md-8">
                <label for="medio_conformidad" class="form-label">Medio</label>
                <select class="form-select<?= campo_invalido('medio_conformidad') ?>" id="medio_conformidad" name="medio_conformidad" required>
                    <option value="">Seleccione…</option>
                    <?php foreach (Estructurador::MEDIOS_CONFORMIDAD as $k => $t): ?><option value="<?= $k ?>"<?= $medio === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
                </select>
                <?= error_campo('medio_conformidad') ?>
            </div>
            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <textarea class="form-control<?= campo_invalido('observaciones') ?>" id="observaciones" name="observaciones" rows="2" maxlength="500"><?= esc((string) old('observaciones')) ?></textarea>
                <?= error_campo('observaciones') ?>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end mt-3">
            <a href="<?= site_url("admin/solicitudes/{$id}/alternativas") ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Confirmar alternativa</button>
        </div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
