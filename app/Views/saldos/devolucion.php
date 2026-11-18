<?php
/**
 * CU-78 paso 4 (A1 a A3) y constancia de devolución (paso 10); CU-77 paso 9 y A5.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $a
 */
use App\Services\Caja\DevolucionService;

$e = $a['ejecucion'];
$pendiente = $a['estado'] === 'PendienteEjecucion' && $e === null;
$this->extend('layouts/panel');
$this->section('contenido');
?>
<span class="documento-propio" hidden></span><?php // D-57: con encabezado propio; al imprimir lleva solo la cabecera y el pie de cada hoja ?>
<nav aria-label="Ruta" class="mb-2 d-print-none">
    <?php if (tiene_permiso('saldos-favor.ejecutar-devolucion')): ?><a href="<?= url_to('saldos-favor.ejecutar-devolucion') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Devoluciones</a>
    <?php else: ?><a href="<?= url_to('saldos-favor.autorizar-devolucion') ?>?cliente=<?= (int) $a['cliente_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Saldos del cliente</a><?php endif ?>
</nav>
<div class="encabezado">
    <div>
        <h1><?= $a['estado'] === 'Completada' ? 'Constancia de devolución' : 'Devolución' ?> N.º <?= (int) $a['id'] ?> <span class="badge text-bg-secondary align-middle fs-6"><?= esc(DevolucionService::ESTADOS[$a['estado']] ?? $a['estado']) ?></span></h1>
        <p><?= esc($a['denominacion']) ?> · <?= esc(formato_cuit($a['cuit_cuil'])) ?></p>
    </div>
    <?php if ($a['estado'] === 'Completada'): ?><button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button><?php endif ?>
</div>
<section class="hoja p-4 mb-3">
    <dl class="resumen-plan mb-0">
        <div><dt>Importe</dt><dd class="importe fw-semibold"><?= esc(formato_importe((string) $a['importe'])) ?></dd></div>
        <div><dt>Destino</dt><dd><?= $a['destino'] === 'MedioOriginal' ? 'Medio de pago original · transacción ' . esc((string) $a['id_externo']) : 'Transferencia al CBU ' . esc((string) $a['cbu_cvu']) . ' (' . esc((string) $a['entidad_bancaria']) . '), titular ' . esc(formato_cuit((string) $a['cuit_titular'])) ?></dd></div>
        <div><dt>Motivo</dt><dd><?= esc($a['motivo']) ?></dd></div>
        <div><dt>Solicitud del cliente</dt><dd><?= esc(formato_fecha((string) $a['fecha_solicitud'])) ?> · <?= esc($a['medio_solicitud']) ?></dd></div>
        <div><dt>Autorizada</dt><dd><?= esc(formato_fecha_hora($a['fecha'])) ?> por <?= esc($a['gerente']) ?></dd></div>
        <div><dt>Vence</dt><dd><?= esc(formato_fecha_hora($a['vence'])) ?><?= $pendiente && !$a['vigente'] ? ' (vencida)' : '' ?></dd></div>
        <?php if ($a['evidencia_titularidad'] !== null): ?><div><dt>Titularidad verificada</dt><dd><?= esc($a['evidencia_titularidad']) ?></dd></div><?php endif ?>
        <?php if ($a['estado'] === 'Anulada'): ?><div><dt>Anulada</dt><dd><?= esc(formato_fecha_hora((string) $a['fecha_anulacion'])) ?> por <?= esc((string) $a['anulada_por']) ?>: <?= esc((string) $a['motivo_anulacion']) ?></dd></div><?php endif ?>
    </dl>
</section>
<?php if ($e !== null): ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Ejecución</h2>
        <dl class="resumen-plan mb-0">
            <div><dt>Inicio</dt><dd><?= esc(formato_fecha_hora($e['inicio'])) ?> por <?= esc($e['cajero']) ?></dd></div>
            <div><dt>Operación</dt><dd><code><?= esc((string) $e['identificador_operacion']) ?></code></dd></div>
            <div><dt>Resultado</dt><dd><?= esc($e['resultado']) ?><?= $e['fecha_confirmacion'] !== null ? ' el ' . esc(formato_fecha_hora($e['fecha_confirmacion'])) : '' ?></dd></div>
            <?php if ($e['comprobante'] !== null): ?><div><dt>Comprobante</dt><dd><?= esc($e['comprobante']) ?><?= $e['fecha_transferencia'] !== null ? ' del ' . esc(formato_fecha((string) $e['fecha_transferencia'])) : '' ?></dd></div><?php endif ?>
            <?php if ($e['detalle'] !== null): ?><div><dt>Detalle</dt><dd><?= esc($e['detalle']) ?></dd></div><?php endif ?>
        </dl>
        <?php if ($a['estado'] === 'EnConciliacion'): ?><p class="mt-2 mb-0 text-warning-emphasis">Resultado incierto: no repita la devolución. El importe sigue reservado hasta la conciliación automática (A4).</p><?php endif ?>
    </section>
<?php endif ?>
<?php if ($pendiente && tiene_permiso('saldos-favor.ejecutar-devolucion')): ?>
    <form method="post" action="<?= site_url('admin/devoluciones/' . (int) $a['id'] . '/ejecutar') ?>" class="hoja p-4 mb-3 d-print-none" novalidate data-confirmar="¿Confirma la ejecución de la devolución por el medio autorizado?">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $a['version_fila'] ?>">
        <h2 class="h5">Ejecutar</h2>
        <?php if ($a['destino'] === 'Transferencia'): ?>
            <p>Realice la transferencia desde el canal bancario de la entidad y registre su comprobante (A1).</p>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label" for="numero_operacion">N.º de operación</label><input type="text" maxlength="100" class="form-control<?= campo_invalido('numero_operacion') ?>" id="numero_operacion" name="numero_operacion" value="<?= esc((string) old('numero_operacion'), 'attr') ?>"><?= error_campo('numero_operacion') ?></div>
                <div class="col-md-3"><label class="form-label" for="fecha">Fecha</label><input type="date" class="form-control<?= campo_invalido('fecha') ?>" id="fecha" name="fecha" value="<?= esc((string) (old('fecha') ?? hoy_negocio()), 'attr') ?>"><?= error_campo('fecha') ?></div>
                <div class="col-md-2"><label class="form-label" for="importe">Importe</label><input type="text" inputmode="decimal" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" value="<?= esc((string) old('importe'), 'attr') ?>"><?= error_campo('importe') ?></div>
                <div class="col-md-4"><label class="form-label" for="cbu">CBU destino</label><input type="text" inputmode="numeric" maxlength="22" class="form-control<?= campo_invalido('cbu') ?>" id="cbu" name="cbu" value="<?= esc((string) old('cbu'), 'attr') ?>"><?= error_campo('cbu') ?></div>
            </div>
        <?php else: ?>
            <p class="mb-0">Se solicitará a la pasarela la devolución de <?= esc(formato_importe((string) $a['importe'])) ?> sobre la transacción <?= esc((string) $a['id_externo']) ?>. Desde el inicio, el vencimiento ya no libera la reserva.</p>
        <?php endif ?>
        <div class="text-end mt-3"><button class="btn btn-primary" type="submit"<?= $a['vigente'] ? '' : ' title="Vencida: al confirmar quedará Vencida (A2)"' ?>>Ejecutar la devolución</button></div>
    </form>
<?php endif ?>
<?php if ($pendiente && tiene_permiso('saldos-favor.autorizar-devolucion')): ?>
    <form method="post" action="<?= site_url('admin/saldos-favor/autorizaciones/' . (int) $a['id'] . '/anular') ?>" class="hoja p-4 d-print-none" novalidate data-confirmar="¿Anula la autorización? El importe volverá a estar disponible.">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $a['version_fila'] ?>">
        <h2 class="h5">Anular la autorización (A5)</h2>
        <label class="form-label" for="motivo_anulacion">Motivo</label>
        <input type="text" maxlength="500" class="form-control<?= campo_invalido('motivo_anulacion') ?>" id="motivo_anulacion" name="motivo_anulacion" value="<?= esc((string) old('motivo_anulacion'), 'attr') ?>">
        <?= error_campo('motivo_anulacion') ?>
        <div class="text-end mt-3"><button class="btn btn-outline-danger" type="submit">Anular</button></div>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
