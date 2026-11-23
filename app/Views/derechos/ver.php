<?php
/**
 * CU-108 Atender Solicitud de Derechos del Titular, pasos 5 a 8: detalle de la solicitud y su respuesta.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var bool $puedeRevisar
 */
use App\Libraries\Clientes\DerechosTitular;

$pendiente = $s['estado'] === 'Pendiente';
$revision = $s['tipo'] === 'RevisionAutomatizada';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('derechos.atender') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Derechos del titular</a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc(DerechosTitular::TIPOS[$s['tipo']] ?? $s['tipo']) ?></h1>
        <p>Solicitud N.º <?= (int) $s['id'] ?> · <?= esc(DerechosTitular::ESTADOS[$s['estado']] ?? $s['estado']) ?><?= $pendiente && $s['situacion'] === 'Vencido' ? ' · plazo vencido' : '' ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" style="max-width: 48rem">
    <dl class="row mb-0">
        <dt class="col-sm-4">Cliente</dt><dd class="col-sm-8"><a href="<?= site_url('admin/clientes/' . (int) $s['cliente_id']) ?>"><?= esc($s['cliente']) ?></a></dd>
        <dt class="col-sm-4">Canal</dt><dd class="col-sm-8"><?= esc($s['canal']) ?></dd>
        <dt class="col-sm-4">Recibida</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora((string) $s['creado_en'])) ?></dd>
        <dt class="col-sm-4">Fecha límite</dt><dd class="col-sm-8"><?= esc(formato_fecha((string) $s['vence'])) ?></dd>
        <?php if ($s['solicitud_numero'] !== null): ?><dt class="col-sm-4">Solicitud de crédito</dt><dd class="col-sm-8"><a href="<?= site_url('admin/solicitudes/' . (int) $s['solicitud_id']) ?>">N.º <?= (int) $s['solicitud_numero'] ?></a></dd><?php endif ?>
        <dt class="col-sm-4">Detalle</dt><dd class="col-sm-8"><?= nl2br(esc((string) $s['detalle'])) ?></dd>
        <?php if (!$pendiente): ?>
            <dt class="col-sm-4">Respuesta</dt><dd class="col-sm-8"><?= nl2br(esc((string) $s['respuesta'])) ?></dd>
            <dt class="col-sm-4">Respondió</dt><dd class="col-sm-8"><?= esc((string) $s['responsable']) ?> · <?= esc(formato_fecha_hora((string) $s['fecha_resolucion'])) ?></dd>
        <?php endif ?>
    </dl>
</section>
<?php if ($pendiente && $revision && !$puedeRevisar): ?>
    <div class="alert alert-info" style="max-width: 48rem">La revisión de una decisión automatizada la resuelve el Gerente (art. 20 de la Ley 25.326).</div>
<?php elseif ($pendiente): ?>
    <form method="post" action="<?= site_url('admin/derechos/' . (int) $s['id'] . '/resolver') ?>" class="hoja p-4" style="max-width: 48rem" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
        <h2 class="h6">Responder</h2>
        <?php if ($revision): ?><p class="small text-body-secondary">Revise la solicitud de crédito, sus antecedentes y la evaluación, y responda a la persona con el resultado de la revisión.</p><?php endif ?>
        <?php if ($s['tipo'] === 'Acceso'): ?><p class="small text-body-secondary">Entregue una copia de los datos que figuran en su perfil (CU-15) y deje constancia de cómo se la entregó.</p><?php endif ?>
        <div class="mb-2">
            <label for="estado" class="form-label small">Resultado</label>
            <select id="estado" name="estado" class="form-select<?= campo_invalido('estado') ?>">
                <option value="">Elija una opción</option>
                <option value="Atendida"<?= old('estado') === 'Atendida' ? ' selected' : '' ?>>Atendida</option>
                <option value="Denegada"<?= old('estado') === 'Denegada' ? ' selected' : '' ?>>Denegada (la ley o una obligación legal lo impide)</option>
            </select>
            <?= error_campo('estado') ?>
        </div>
        <label for="respuesta" class="form-label small">Respuesta a la persona</label>
        <textarea id="respuesta" name="respuesta" rows="3" maxlength="<?= DerechosTitular::LARGO_RESPUESTA ?>" class="form-control<?= campo_invalido('respuesta') ?>"><?= esc((string) old('respuesta')) ?></textarea>
        <?= error_campo('respuesta') ?>
        <div class="text-end mt-2"><button type="submit" class="btn btn-primary btn-sm">Registrar respuesta</button></div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
