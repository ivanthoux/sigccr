<?php
/**
 * CU-84 Registrar Corrección de Solicitud, pasos 2 a 4.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var list<array<string, mixed>> $tipos
 */
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Solicitudes\RevisionLegajo;

$id = (int) $s['id'];
$observada = array_values(array_filter($s['revisiones'], static fn ($r) => $r['estado'] === 'Observada'))[0] ?? null;
$docs = array_column($s['documentos'], null, 'id');
$reemplazos = [];
foreach ($observada['verificaciones'] ?? [] as $v) {
    $d = $docs[(int) $v['documento_legajo_id']] ?? null;
    if ($v['resultado'] !== 'Conforme' && $d !== null) {
        $reemplazos[(string) $d['codigo']] = (int) $d['id'];
    }
}
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Registrar corrección</h1>
        <p>Solicitud N.º <?= (int) $s['numero'] ?> · <?= esc($s['denominacion']) ?> · <?= etiqueta_estado_solicitud((string) $s['estado']) ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-pedido">
    <h2 class="h5" id="titulo-pedido">Qué debe corregirse</h2>
    <?php if ($s['devolucion'] !== null): ?>
        <p class="mb-2">Devolución del Gerente: <?= esc((string) $s['devolucion']['fundamento']) ?></p>
        <p class="small mb-0">Si no incorpora documentación, la solicitud retoma <?= esc(mb_strtolower(EstadosSolicitud::etiqueta(EstadosSolicitud::ETAPAS_RETORNO[$s['devolucion']['etapa_retorno']] ?? 'PendienteVerificacion'))) ?>; si la incorpora, vuelve a verificación.</p>
    <?php elseif ($observada !== null): ?>
        <p class="mb-2"><?= esc((string) $observada['observaciones']) ?></p>
        <ul class="small mb-0">
            <?php foreach ($s['faltantes'] as $c): ?><li>Falta: <?= esc(array_column($tipos, 'nombre', 'codigo')[$c] ?? $c) ?></li><?php endforeach ?>
            <?php foreach ($observada['verificaciones'] as $v): if ($v['resultado'] === 'Conforme') { continue; } $d = $docs[(int) $v['documento_legajo_id']] ?? null; ?>
                <li><?= $d ? 'Reemplazar ' . esc($d['tipo']) . ' (' . esc($d['nombre_original']) . ')' : 'Documento ya reemplazado' ?>: <?= esc(RevisionLegajo::MOTIVOS[$v['motivo_observacion']] ?? '') ?><?= $v['observacion'] ? ' · ' . esc($v['observacion']) : '' ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<form method="post" action="<?= site_url("admin/solicitudes/{$id}/corregir") ?>" enctype="multipart/form-data" class="hoja p-4" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
    <h2 class="h5">Documentación y aclaraciones</h2>
    <?= view('solicitudes/_documentos', ['tipos' => $tipos, 'tipoCliente' => (string) $s['tipo_cliente'], 'exigidos' => $s['exigidos'], 'presentes' => array_diff(array_column($s['documentos'], 'codigo'), array_keys($reemplazos)), 'reemplazos' => $reemplazos]) ?>
    <div class="my-3">
        <label for="aclaracion" class="form-label">Aclaración <span class="text-body-secondary fw-normal">(obligatoria si no adjunta documentación)</span></label>
        <textarea class="form-control<?= campo_invalido('aclaracion') ?>" id="aclaracion" name="aclaracion" rows="3" maxlength="500"><?= esc((string) old('aclaracion')) ?></textarea>
        <?= error_campo('aclaracion') ?>
        <div class="form-text">Si hay que cambiar datos personales o económicos del cliente, hágalo con «Modificar datos» del cliente (A2, RN-12).</div>
    </div>
    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= site_url("admin/solicitudes/{$id}") ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Registrar corrección</button>
    </div>
</form>
<?php $this->endSection() ?>
