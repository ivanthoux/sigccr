<?php
/**
 * CU-34 Generar Orden de Pago Electrónico, pasos 2 a 4 (A2, A3, A7): cuotas pendientes del cliente y selección de
 * las que integran la orden, por su total actualizado (RN-29, RN-54).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $creditos
 * @var list<int> $ordenes_pendientes
 */
$sel = (array) old('cuotas');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('pagos-electronicos.generar-orden') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Orden de pago electrónico</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?></p>
    </div>
</div>
<?php if ($creditos === []): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene cuotas pendientes de pago (A2).</p></div>
<?php else: ?>
    <?php if ($ordenes_pendientes !== []): ?>
        <div class="alert alert-warning">Hay cuotas en la orden N.º <?= esc(implode(', N.º ', $ordenes_pendientes)) ?>, pendiente y vigente: no pueden integrar otra orden (A7).</div>
    <?php endif ?>
    <form method="post" action="<?= url_to('pagos-electronicos.generar-orden') ?>" class="formulario-cobro" novalidate data-confirmar="¿Genera la orden de pago por el total de las cuotas seleccionadas?">
        <?= csrf_field() ?>
        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
        <?php if (campo_invalido('seleccion') !== ''): ?><div class="alert alert-danger"><?= esc((string) (session('errores') ?? [])['seleccion']) ?></div><?php endif ?>
        <?php foreach ($creditos as $cr): ?>
            <section class="hoja p-4 mb-3" aria-labelledby="titulo-credito-<?= (int) $cr['id'] ?>">
                <h2 class="h5" id="titulo-credito-<?= (int) $cr['id'] ?>">Crédito N.º <?= (int) $cr['numero'] ?> · <?= esc($cr['linea']) ?></h2>
                <p class="small text-body-secondary">Seleccione cuotas completas y consecutivas desde la más antigua (RN-29). El total incluye los punitorios al día de hoy.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col"><span class="visually-hidden">Seleccionar</span></th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Atraso</th><th class="text-end">Punitorios</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($cr['cuotas'] as $q): $idc = 'q-' . (int) $q['id']; ?>
                            <tr<?= $q['dias_atraso'] > 0 ? ' class="table-warning"' : '' ?>>
                                <td><input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="cuotas[<?= (int) $cr['id'] ?>][]" value="<?= (int) $q['numero'] ?>" data-cobro-cuota data-total="<?= esc($q['total']->toFixed(2), 'attr') ?>"<?= $q['orden_pendiente'] !== null ? ' disabled' : '' ?><?= in_array((string) $q['numero'], array_map('strval', (array) ($sel[$cr['id']] ?? [])), true) ? ' checked' : '' ?>></td>
                                <td><label for="<?= $idc ?>"><?= (int) $q['numero'] ?></label><?php if ($q['orden_pendiente'] !== null): ?> <span class="badge text-bg-warning">Orden N.º <?= (int) $q['orden_pendiente'] ?></span><?php endif ?></td>
                                <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                                <td class="text-end"><?= $q['dias_atraso'] > 0 ? (int) $q['dias_atraso'] . ' días' : '—' ?></td>
                                <td class="importe"><?= esc(formato_importe($q['conceptos']['Punitorio'], false)) ?></td>
                                <td class="importe fw-medium"><?= esc(formato_importe($q['total'], false)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach ?>
        <section class="hoja p-4">
            <p class="mb-1">Importe de la orden: <strong class="importe" data-cobro-total>$ 0,00</strong></p>
            <p class="small text-body-secondary mb-3">La orden tiene importe fijo y vence a las 00:00 de mañana. El pago se confirma cuando la pasarela lo informa (RN-30, RN-54).</p>
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url_to('pagos-electronicos.generar-orden') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-qr-code me-1" aria-hidden="true"></i>Generar la orden</button>
            </div>
        </section>
    </form>
<?php endif ?>
<script src="<?= base_url('assets/sigccr/cobros.js') ?>"></script>
<?php $this->endSection() ?>
