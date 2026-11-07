<?php
/**
 * CU-102 Registrar Revocación del Préstamo, pasos 3 a 8 (A1 a A5): plazo, importe a restituir, comunicación,
 * confirmación de la transferencia y constancia.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c datos de RevocacionService::datos()
 * @var string $hoy
 * @var array<string, mixed>|null $jornada
 */
$r = $c['revocacion'];
$p = $c['plazo'];
$k = $c['calculo'];
$enPlazo = $p['limite'] !== null && $hoy <= $p['limite'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2 d-print-none"><a href="<?= url_to('creditos.revocar') ?>?cliente=<?= (int) $c['cliente_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Créditos del cliente</a></nav>
<div class="encabezado">
    <div>
        <h1><?= $r !== null && $r['estado'] === 'Confirmada' ? 'Constancia de revocación' : 'Revocación del crédito' ?> N.º <?= (int) $c['numero'] ?></h1>
        <p><?= esc($c['denominacion']) ?> (<?= esc(formato_cuit($c['cuit_cuil'])) ?>) · <?= esc($c['linea']) ?></p>
    </div>
    <?php if ($r !== null && $r['estado'] === 'Confirmada'): ?>
        <button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button>
    <?php endif ?>
</div>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-plazo">
    <h2 class="h5" id="titulo-plazo">Plazo de revocación</h2>
    <dl class="resumen-plan mb-0">
        <div><dt>Desembolso</dt><dd><?= $p['desembolso'] ? esc(formato_fecha($p['desembolso'])) : '—' ?></dd></div>
        <div><dt>Recepción del contrato</dt><dd><?= $p['recepcion'] ? esc(formato_fecha($p['recepcion'])) : 'Sin constancia' ?></dd></div>
        <div><dt>Vence el plazo</dt><dd class="fw-semibold"><?= $p['limite'] ? esc(formato_fecha($p['limite'])) : '—' ?></dd></div>
        <div><dt>Importe neto desembolsado</dt><dd class="importe"><?= esc(formato_importe((string) $c['importe_neto'])) ?></dd></div>
    </dl>
    <p class="small text-body-secondary mt-2 mb-0">10 días hábiles desde la recepción del contrato o el desembolso, lo que ocurra último (RN-79, arts. 1110 a 1112 del Código Civil y Comercial).</p>
</section>

<?php if ($r === null): ?>
    <?php if (!in_array($c['estado'], ['Activo', 'EnMora'], true)): ?>
        <div class="alert alert-secondary">El crédito no está activo: no se puede revocar.</div>
    <?php elseif ($p['limite'] === null): ?>
        <div class="alert alert-warning">No hay constancia de recepción del contrato: no se puede computar el plazo de revocación (CU-103).</div>
    <?php elseif (!$enPlazo): ?>
        <div class="alert alert-warning">El plazo de revocación venció el <?= esc(formato_fecha($p['limite'])) ?>. El cliente puede cancelar el crédito anticipadamente (CU-97) (A1).</div>
    <?php else: ?>
        <form method="post" action="<?= site_url('admin/revocaciones/' . (int) $c['id']) ?>" class="hoja p-4" style="max-width: 52rem" novalidate data-confirmar="¿Registra la revocación comunicada por el cliente?">
            <?= csrf_field() ?>
            <h2 class="h5">Comunicación del cliente</h2>
            <?php if ($k !== null): ?>
                <p>Si la comunica hoy, el cliente restituye <strong><?= esc(formato_importe($k['restituir'])) ?></strong>: neto <?= esc(formato_importe($k['neto'])) ?>, interés de <?= (int) $k['dias'] ?> días <?= esc(formato_importe($k['interes'])) ?> e IVA <?= esc(formato_importe($k['iva'])) ?><?= $k['pagado']->isPositive() ? ', menos lo pagado ' . esc(formato_importe($k['pagado'])) : '' ?>. No se cobran gastos ni comisiones.</p>
            <?php endif ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="fecha_comunicacion" class="form-label">Fecha de la comunicación</label>
                    <input type="date" class="form-control<?= campo_invalido('fecha_comunicacion') ?>" id="fecha_comunicacion" name="fecha_comunicacion" min="<?= esc((string) $p['desembolso'], 'attr') ?>" max="<?= esc($hoy, 'attr') ?>" value="<?= esc((string) (old('fecha_comunicacion') ?? $hoy), 'attr') ?>">
                    <?= error_campo('fecha_comunicacion') ?>
                </div>
                <div class="col-md-8">
                    <label for="medio_comunicacion" class="form-label">Medio</label>
                    <input type="text" class="form-control<?= campo_invalido('medio_comunicacion') ?>" id="medio_comunicacion" name="medio_comunicacion" maxlength="60" list="medios-comunicacion" value="<?= esc((string) old('medio_comunicacion'), 'attr') ?>">
                    <datalist id="medios-comunicacion"><option value="Presencial en la sucursal"><option value="Correo electrónico"><option value="Carta documento"><option value="Portal de autogestión"></datalist>
                    <?= error_campo('medio_comunicacion') ?>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-danger">Registrar la revocación</button></div>
        </form>
    <?php endif ?>
<?php else: ?>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-revocacion">
        <h2 class="h5" id="titulo-revocacion">Revocación <?= $r['estado'] === 'Confirmada' ? 'confirmada' : 'pendiente de restitución' ?></h2>
        <dl class="resumen-plan mb-2">
            <div><dt>Comunicada</dt><dd><?= esc(formato_fecha($r['fecha_comunicacion'])) ?> · <?= esc($r['medio_comunicacion']) ?></dd></div>
            <div><dt>Neto desembolsado</dt><dd class="importe"><?= esc(formato_importe($r['capital'])) ?></dd></div>
            <div><dt>Interés (<?= (int) $r['dias_transcurridos'] ?> días)</dt><dd class="importe"><?= esc(formato_importe($r['interes_devengado'])) ?></dd></div>
            <div><dt>IVA del interés</dt><dd class="importe"><?= esc(formato_importe($r['iva_interes'])) ?></dd></div>
            <div><dt>Pagado antes</dt><dd class="importe"><?= esc(formato_importe($r['pagado_previo'])) ?></dd></div>
            <div><dt>A restituir</dt><dd class="importe fw-semibold"><?= esc(formato_importe($r['importe_restituir'])) ?></dd></div>
            <?php if ((float) $r['saldo_a_favor'] > 0): ?><div><dt>Saldo a favor del cliente</dt><dd class="importe"><?= esc(formato_importe($r['saldo_a_favor'])) ?></dd></div><?php endif ?>
        </dl>
        <?php if ($r['estado'] === 'Confirmada'): ?>
            <p class="mb-0">El crédito quedó revocado el <?= esc(formato_fecha_hora($r['fecha_confirmacion'])) ?> y sus cuotas no pagadas, sin efecto. No se cobraron gastos ni comisiones (RN-79).</p>
        <?php endif ?>
    </section>
    <?php if ($r['estado'] === 'PendienteRestitucion'): ?>
        <?php if ($jornada === null): ?>
            <div class="alert alert-warning d-print-none">Para registrar la transferencia recibida necesita una caja abierta hoy (<a href="<?= url_to('cajas.abrir') ?>">abrir caja</a>).</div>
        <?php endif ?>
        <form method="post" action="<?= site_url('admin/revocaciones/' . (int) $c['id'] . '/confirmar') ?>" class="hoja p-4 d-print-none" style="max-width: 52rem" novalidate data-confirmar="¿Confirma la restitución? El crédito quedará revocado.">
            <?= csrf_field() ?>
            <h2 class="h5">Transferencia recibida del cliente</h2>
            <p class="small">El crédito conserva su plan hasta confirmar la restitución (A2). La transferencia integra la conciliación de su caja (RN-32).</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="numero_operacion" class="form-label">N.º de operación</label>
                    <input type="text" class="form-control<?= campo_invalido('numero_operacion') ?>" id="numero_operacion" name="numero_operacion" maxlength="60" value="<?= esc((string) old('numero_operacion'), 'attr') ?>">
                    <?= error_campo('numero_operacion') ?>
                </div>
                <div class="col-md-6">
                    <label for="importe" class="form-label">Importe transferido</label>
                    <input type="text" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" inputmode="decimal" value="<?= esc((string) old('importe'), 'attr') ?>">
                    <?= error_campo('importe') ?>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary"<?= $jornada === null ? ' disabled' : '' ?>>Confirmar la restitución</button></div>
        </form>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
