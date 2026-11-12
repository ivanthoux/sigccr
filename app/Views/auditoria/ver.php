<?php
/**
 * CU-57 pasos 5 y 6 (A4): detalle de un registro de auditoría.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $a
 * @var string $volver consulta del listado
 */
use App\Libraries\Alertas\OperacionAlerta;
use App\Services\Reportes\AuditoriaConsultaService as A;

$datos = $a['detalle_datos'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Registro de auditoría N.º <?= (int) $a['id'] ?></h1>
        <p><?= esc(A::categoria((string) $a['accion'], $a['proceso'])) ?> · <code><?= esc((string) $a['accion']) ?></code></p>
    </div>
    <a href="<?= esc(url_to('auditoria.consultar') . ($volver !== '' ? '?' . $volver : ''), 'attr') ?>" class="btn btn-outline-secondary">Volver al listado</a>
</div>
<section class="hoja p-4 mb-3" style="max-width: 56rem">
    <dl class="row mb-0">
        <dt class="col-sm-4">Fecha y hora</dt><dd class="col-sm-8"><?= esc(formato_fecha_hora((string) $a['fecha_hora'])) ?></dd>
        <?php if ($a['proceso'] !== null): ?>
            <dt class="col-sm-4">Responsable</dt><dd class="col-sm-8">Proceso automático <code><?= esc((string) $a['proceso']) ?></code>; no hay un usuario responsable (A4).</dd>
        <?php else: ?>
            <dt class="col-sm-4">Responsable</dt><dd class="col-sm-8"><?= esc((string) $a['responsable']) ?><?= $a['rol'] !== null ? ' <span class="text-body-secondary">(' . esc((string) $a['rol']) . ')</span>' : '' ?></dd>
        <?php endif ?>
        <dt class="col-sm-4">Objeto afectado</dt><dd class="col-sm-8"><?= esc(OperacionAlerta::OBJETOS[$a['objeto_tipo']] ?? (string) $a['objeto_tipo']) ?><?= $a['objeto_id'] !== null ? ' ' . esc((string) $a['objeto_id']) : '' ?></dd>
        <dt class="col-sm-4">Resultado</dt><dd class="col-sm-8"><?= esc((string) $a['resultado']) ?></dd>
        <?php if ($a['motivo'] !== null): ?><dt class="col-sm-4">Motivo</dt><dd class="col-sm-8"><?= esc((string) $a['motivo']) ?></dd><?php endif ?>
        <?php if ($a['ip_origen'] !== null): ?><dt class="col-sm-4">Dirección de origen</dt><dd class="col-sm-8"><?= esc((string) $a['ip_origen']) ?></dd><?php endif ?>
    </dl>
</section>
<section class="hoja p-4" style="max-width: 56rem">
    <h2 class="h6"><?= $a['proceso'] !== null ? 'Parámetros aplicados y datos registrados' : 'Datos registrados' ?></h2>
    <?php if ($datos === null || $datos === []): ?>
        <p class="small text-body-secondary mb-0">El registro no tiene datos adicionales.</p>
    <?php else: ?>
        <?php if (isset($datos['antes']) || isset($datos['despues']) || isset($datos['anterior']) || isset($datos['nuevo'])): ?><p class="small text-body-secondary">Incluye los datos anteriores y posteriores de la modificación.</p><?php endif ?>
        <pre class="small bg-body-tertiary p-3 rounded mb-0" style="white-space: pre-wrap"><?= esc((string) json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
