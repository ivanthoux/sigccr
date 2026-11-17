<?php
/**
 * CU-57 pasos 5 y 6 (A4): detalle de un registro de auditoría.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $a
 * @var string $volver consulta del listado
 */
use App\Libraries\Alertas\OperacionAlerta;
use App\Libraries\Auditoria\Cambios;
use App\Services\Reportes\AuditoriaConsultaService as A;

// D-48: los cambios de una modificación se presentan campo a campo; el resto del detalle, como datos registrados
['cambios' => $cambios, 'datos' => $datos] = Cambios::desdeDetalle($a['detalle_datos']);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Registro de auditoría N.º <?= (int) $a['id'] ?></h1>
        <p><?= esc(A::categoria((string) $a['accion'], $a['proceso'])) ?> · <code><?= esc((string) $a['accion']) ?></code></p>
    </div>
    <a href="<?= url_to('auditoria.consultar') . ($volver !== '' ? '?' . esc($volver) : '') ?>" class="btn btn-outline-secondary">Volver al listado</a>
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
<?php if ($cambios !== []): ?>
<section class="hoja p-4 mb-3" style="max-width: 56rem">
    <h2 class="h6">Campos modificados</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <caption class="visually-hidden">Valor anterior y valor nuevo de cada campo modificado</caption>
            <thead><tr><th scope="col">Campo</th><th scope="col">Valor anterior</th><th scope="col">Valor nuevo</th></tr></thead>
            <tbody>
            <?php foreach ($cambios as $campo => $c): ?>
                <tr>
                    <th scope="row" class="fw-normal"><?= esc(Cambios::etiqueta((string) $campo)) ?></th>
                    <td><?= esc(Cambios::mostrar($c['anterior'] ?? null)) ?></td>
                    <td><?= esc(Cambios::mostrar($c['nuevo'] ?? null)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif ?>
<section class="hoja p-4" style="max-width: 56rem">
    <h2 class="h6"><?= $a['proceso'] !== null ? 'Parámetros aplicados y datos registrados' : ($cambios !== [] ? 'Otros datos registrados' : 'Datos registrados') ?></h2>
    <?php if ($datos === []): ?>
        <p class="small text-body-secondary mb-0"><?= $cambios !== [] ? 'El registro no tiene otros datos.' : 'El registro no tiene datos adicionales.' ?></p>
    <?php else: ?>
        <pre class="small bg-body-tertiary p-3 rounded mb-0" style="white-space: pre-wrap"><?= esc((string) json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
