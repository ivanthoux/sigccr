<?php
/**
 * CU-23 Verificar Legajo de Solicitud, pasos 3 a 5 y A4.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var list<array<string, mixed>> $tipos
 */
use App\Libraries\Solicitudes\RevisionLegajo;

$id = (int) $s['id'];
$nombres = array_column($tipos, 'nombre', 'codigo');
$v = static fn (int $doc, string $k): string => (string) (old("verificacion.{$doc}.{$k}") ?? '');
$resultado = (string) old('resultado');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Verificar legajo</h1>
        <p>Solicitud N.º <?= (int) $s['numero'] ?> · <?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?></p>
    </div>
</div>

<form method="post" action="<?= site_url("admin/solicitudes/{$id}/verificar") ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-docs">
        <h2 class="h5" id="titulo-docs">Documentos del legajo</h2>
        <p class="small text-body-secondary">Revise la suficiencia, vigencia, legibilidad y consistencia de cada documento. Exigidos por la línea:
            <?= $s['exigidos'] === [] ? 'solo la identificación.' : esc(implode(', ', array_map(static fn ($c) => $nombres[$c] ?? $c, $s['exigidos']))) . '.' ?></p>
        <?php if ($s['faltantes'] !== []): ?>
            <div class="alert alert-warning">Falta en el legajo: <?= esc(implode(', ', array_map(static fn ($c) => $nombres[$c] ?? $c, $s['faltantes']))) ?>. Puede incorporarla abajo o observar la solicitud.</div>
        <?php endif ?>
        <?php foreach ($s['documentos'] as $d): $doc = (int) $d['id']; $exigido = in_array($d['codigo'], $s['exigidos'], true); ?>
            <fieldset class="verificacion-documento<?= campo_invalido('doc_' . $doc) !== '' ? ' con-error' : '' ?>">
                <legend class="h6 mb-1"><?= esc($d['tipo']) ?><?= $exigido ? ' <span class="badge text-bg-light border">exigido</span>' : '' ?></legend>
                <p class="small mb-2"><a href="<?= site_url('admin/clientes/' . (int) $s['cliente_id'] . '/documentos/' . $doc) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark me-1" aria-hidden="true"></i><?= esc($d['nombre_original']) ?></a> · incorporado el <?= esc(formato_fecha($d['fecha_incorporacion'])) ?></p>
                <div class="row g-2 align-items-start">
                    <div class="col-md-4">
                        <div class="btn-group w-100" role="group" aria-label="Resultado de <?= esc($d['tipo'], 'attr') ?>">
                            <?php foreach (RevisionLegajo::RESULTADOS_DOCUMENTO as $k => $etq): $idr = "res-{$doc}-{$k}"; ?>
                                <input type="radio" class="btn-check" name="verificacion[<?= $doc ?>][resultado]" id="<?= $idr ?>" value="<?= $k ?>"<?= $v($doc, 'resultado') === $k ? ' checked' : '' ?> data-motivo="mot-<?= $doc ?>">
                                <label class="btn btn-outline-secondary btn-sm" for="<?= $idr ?>"><?= $etq ?></label>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="col-md-3" id="mot-<?= $doc ?>">
                        <label class="visually-hidden" for="motivo-<?= $doc ?>">Motivo</label>
                        <select class="form-select form-select-sm" id="motivo-<?= $doc ?>" name="verificacion[<?= $doc ?>][motivo]">
                            <option value="">Motivo de la observación</option>
                            <?php foreach (RevisionLegajo::MOTIVOS as $k => $etq): ?><option value="<?= $k ?>"<?= $v($doc, 'motivo') === $k ? ' selected' : '' ?>><?= esc($etq) ?></option><?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="visually-hidden" for="obs-<?= $doc ?>">Observación</label>
                        <input type="text" class="form-control form-control-sm" id="obs-<?= $doc ?>" name="verificacion[<?= $doc ?>][observacion]" maxlength="500" placeholder="Observación (opcional)" value="<?= esc($v($doc, 'observacion'), 'attr') ?>">
                    </div>
                </div>
                <?= error_campo('doc_' . $doc) ?>
            </fieldset>
        <?php endforeach ?>
    </section>

    <section class="hoja p-4 mb-3" aria-labelledby="titulo-resultado">
        <h2 class="h5" id="titulo-resultado">Resultado de la revisión</h2>
        <div class="row g-2 mb-3">
            <?php foreach (['Verificada' => ['Verificada', 'Pasa a evaluación crediticia.'], 'Observada' => ['Observada', 'Faltantes o inconsistencias subsanables: queda pendiente de documentación.'], 'Rechazada' => ['Rechazada', 'Incumplimiento no subsanable: finaliza por causa documental (RN-19).']] as $k => [$t, $ayuda]): ?>
                <div class="col-md-4">
                    <input type="radio" class="btn-check" name="resultado" id="resultado-<?= $k ?>" value="<?= $k ?>"<?= $resultado === $k ? ' checked' : '' ?> required>
                    <label class="btn btn-outline-secondary w-100 text-start opcion-resultado" for="resultado-<?= $k ?>"><span class="fw-semibold d-block"><?= $t ?></span><span class="small"><?= $ayuda ?></span></label>
                </div>
            <?php endforeach ?>
        </div>
        <?= error_campo('resultado') ?>
        <div class="mb-3" data-si-resultado="Observada">
            <label for="observaciones" class="form-label">Correcciones necesarias</label>
            <textarea class="form-control<?= campo_invalido('observaciones') ?>" id="observaciones" name="observaciones" rows="3" maxlength="1000"><?= esc((string) old('observaciones')) ?></textarea>
            <?= error_campo('observaciones') ?>
        </div>
        <div class="mb-3" data-si-resultado="Rechazada">
            <label for="motivo_rechazo" class="form-label">Motivo del rechazo documental</label>
            <textarea class="form-control<?= campo_invalido('motivo_rechazo') ?>" id="motivo_rechazo" name="motivo_rechazo" rows="2" maxlength="500"><?= esc((string) old('motivo_rechazo')) ?></textarea>
            <?= error_campo('motivo_rechazo') ?>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= site_url("admin/solicitudes/{$id}") ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar revisión</button>
        </div>
    </section>
</form>

<form method="post" action="<?= site_url("admin/solicitudes/{$id}/documentos") ?>" enctype="multipart/form-data" class="hoja p-4" aria-labelledby="titulo-incorporar">
    <?= csrf_field() ?>
    <h2 class="h6" id="titulo-incorporar">Incorporar documentación al legajo (A4)</h2>
    <?= view('solicitudes/_documentos', ['tipos' => $tipos, 'tipoCliente' => (string) $s['tipo_cliente'], 'exigidos' => [], 'presentes' => array_column($s['documentos'], 'codigo')]) ?>
    <div class="text-end mt-2"><button type="submit" class="btn btn-outline-secondary"><i class="bi bi-upload me-1" aria-hidden="true"></i>Incorporar</button></div>
</form>
<script src="<?= base_url('assets/sigccr/solicitudes.js') ?>"></script>
<?php $this->endSection() ?>
