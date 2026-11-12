<?php
/**
 * CU-49 Solicitar Pago Electrónico de Cuota, pasos 2 a 5 (A1, A3, A7, A8): cuotas impagas con su total actualizado
 * y selección de cuotas completas y consecutivas desde la más antigua (RN-29).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $creditos
 * @var list<int> $ordenes_pendientes
 */
$sel = (array) old('cuotas');
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.inicio') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio</a></nav>
<div class="encabezado">
    <div>
        <h1>Pagar una cuota</h1>
        <p>Genere una orden de pago electrónico y pague con el enlace de la pasarela.</p>
    </div>
</div>
<?php if ($creditos === []): ?>
    <div class="hoja p-4"><p class="mb-0">No tiene cuotas pendientes de pago.</p></div>
<?php else: ?>
    <?php if ($ordenes_pendientes !== []): ?>
        <div class="alert alert-warning">Algunas cuotas ya integran una orden pendiente y vigente (N.º <?= esc(implode(', N.º ', $ordenes_pendientes)) ?>): puede pagarla desde «Estado de cuenta» sin generar otra.</div>
    <?php endif ?>
    <form method="post" action="<?= url_to('portal.pagar') ?>" class="formulario-cobro" novalidate data-confirmar="¿Genera la orden de pago por el total de las cuotas seleccionadas?">
        <?= csrf_field() ?>
        <?php if (campo_invalido('seleccion') !== ''): ?><div class="alert alert-danger"><?= esc((string) (session('errores') ?? [])['seleccion']) ?></div><?php endif ?>
        <?php foreach ($creditos as $cr): ?>
            <section class="hoja p-4 mb-3" aria-labelledby="t-credito-<?= (int) $cr['id'] ?>">
                <h2 class="h5" id="t-credito-<?= (int) $cr['id'] ?>">Crédito N.º <?= (int) $cr['numero'] ?> · <?= esc($cr['linea']) ?></h2>
                <p class="small text-body-secondary">Elija cuotas completas y seguidas, empezando por la más antigua. El total incluye los punitorios al día de hoy.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col"><span class="visually-hidden">Seleccionar</span></th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Atraso</th><th class="text-end">Punitorios</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($cr['cuotas'] as $q): $idc = 'q-' . (int) $q['id']; ?>
                            <tr<?= $q['dias_atraso'] > 0 ? ' class="table-warning"' : '' ?>>
                                <td><input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="cuotas[<?= (int) $cr['id'] ?>][]" value="<?= (int) $q['numero'] ?>" data-cobro-cuota data-total="<?= esc($q['total']->toFixed(2), 'attr') ?>"<?= $q['orden_pendiente'] !== null ? ' disabled' : '' ?><?= in_array((string) $q['numero'], array_map('strval', (array) ($sel[$cr['id']] ?? [])), true) ? ' checked' : '' ?>></td>
                                <td><label for="<?= $idc ?>"><?= (int) $q['numero'] ?></label><?php if ($q['orden_pendiente'] !== null): ?> <span class="badge text-bg-warning">En la orden N.º <?= (int) $q['orden_pendiente'] ?></span><?php endif ?></td>
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
            <p class="mb-1">Total a pagar: <strong class="importe" data-cobro-total>$ 0,00</strong></p>
            <p class="small text-body-secondary mb-3">La orden tiene importe fijo y vence a las 00:00 de mañana. Generarla no cancela la deuda: el pago se acredita cuando la pasarela lo confirma.</p>
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url_to('portal.inicio') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-qr-code me-1" aria-hidden="true"></i>Generar la orden de pago</button>
            </div>
        </section>
    </form>
<?php endif ?>
<script src="<?= base_url('assets/sigccr/cobros.js') ?>"></script>
<?php $this->endSection() ?>
