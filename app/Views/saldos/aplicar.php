<?php
/**
 * CU-76 Aplicar Saldo a Favor a Obligaciones, pasos 3 a 7 (A1 a A3): saldo disponible, cuotas pendientes por
 * antigüedad y constancia de la conformidad del cliente (RN-29, RN-57).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $saldos
 * @var list<array<string, mixed>> $aplicaciones
 * @var list<array<string, mixed>> $creditos
 */
use App\Libraries\Caja\SaldoAFavor;

$disponible = SaldoAFavor::disponible($saldos);
$reservado = array_sum(array_map(static fn ($s) => (float) $s['importe_reservado'], $saldos));
$sel = (array) old('cuotas');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('saldos-favor.aplicar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Aplicar saldo a favor</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?> · disponible: <strong><?= esc(formato_importe($disponible)) ?></strong></p>
    </div>
</div>
<?php if ($saldos !== []): ?>
    <section class="hoja p-4 mb-3"><h2 class="h5">Saldos a favor</h2><?= view('saldos/_saldos', ['saldos' => $saldos]) ?></section>
<?php endif ?>
<?php if (!$disponible->isPositive()): ?>
    <div class="hoja p-4 mb-3"><p class="mb-0"><?= $reservado > 0 ? 'El saldo está reservado para una devolución autorizada: no puede aplicarse hasta que se resuelva (A2).' : 'El cliente no tiene saldo a favor disponible (A1).' ?></p></div>
<?php elseif ($creditos === []): ?>
    <div class="hoja p-4 mb-3"><p class="mb-0">El cliente no tiene cuotas pendientes a las que aplicar el saldo.</p></div>
<?php else: ?>
    <form method="post" action="<?= url_to('saldos-favor.aplicar') ?>" class="formulario-cobro" novalidate data-confirmar="¿Confirma la aplicación del saldo a favor a las cuotas seleccionadas?">
        <?= csrf_field() ?>
        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
        <?php if (campo_invalido('seleccion') !== ''): ?><div class="alert alert-danger"><?= esc((string) (session('errores') ?? [])['seleccion']) ?></div><?php endif ?>
        <?php foreach ($creditos as $cr): ?>
            <section class="hoja p-4 mb-3">
                <h2 class="h5">Crédito N.º <?= (int) $cr['numero'] ?> · <?= esc($cr['linea']) ?></h2>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th scope="col"><span class="visually-hidden">Seleccionar</span></th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Atraso</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($cr['cuotas'] as $q): $idc = 'q-' . (int) $q['id']; $marcar = $sel === [] ? $q['dias_atraso'] > 0 : in_array((string) $q['numero'], array_map('strval', (array) ($sel[$cr['id']] ?? [])), true); ?>
                        <tr<?= $q['dias_atraso'] > 0 ? ' class="table-warning"' : '' ?>>
                            <td><input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="cuotas[<?= (int) $cr['id'] ?>][]" value="<?= (int) $q['numero'] ?>" data-cobro-cuota data-total="<?= esc($q['total']->toFixed(2), 'attr') ?>"<?= $marcar ? ' checked' : '' ?>></td>
                            <td><label for="<?= $idc ?>"><?= (int) $q['numero'] ?></label></td>
                            <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                            <td class="text-end"><?= $q['dias_atraso'] > 0 ? (int) $q['dias_atraso'] . ' días' : '—' ?></td>
                            <td class="importe fw-medium"><?= esc(formato_importe($q['total'], false)) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach ?>
        <section class="hoja p-4 mb-3">
            <p class="mb-1">Total seleccionado: <strong class="importe" data-cobro-total>$ 0,00</strong> · se aplicará hasta <?= esc(formato_importe($disponible)) ?>.</p>
            <p class="small text-body-secondary">Seleccione cuotas consecutivas desde la más antigua. Se imputa con la prelación de RN-29; si el saldo no alcanza, la última cuota queda con una imputación parcial excepcional (RN-57).</p>
            <h2 class="h6 mt-3">Constancia de la solicitud o conformidad del cliente</h2>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_conformidad" class="form-label">Fecha</label>
                    <input type="date" class="form-control<?= campo_invalido('fecha_conformidad') ?>" id="fecha_conformidad" name="fecha_conformidad" value="<?= esc((string) (old('fecha_conformidad') ?? hoy_negocio()), 'attr') ?>">
                    <?= error_campo('fecha_conformidad') ?>
                </div>
                <div class="col-md-4">
                    <label for="medio_conformidad" class="form-label">Medio</label>
                    <input type="text" class="form-control<?= campo_invalido('medio_conformidad') ?>" id="medio_conformidad" name="medio_conformidad" maxlength="100" value="<?= esc((string) old('medio_conformidad'), 'attr') ?>" placeholder="Presencial, correo electrónico…">
                    <?= error_campo('medio_conformidad') ?>
                </div>
                <div class="col-md-5">
                    <label for="observaciones" class="form-label">Observaciones (opcional)</label>
                    <input type="text" class="form-control<?= campo_invalido('observaciones') ?>" id="observaciones" name="observaciones" maxlength="150" value="<?= esc((string) old('observaciones'), 'attr') ?>">
                    <?= error_campo('observaciones') ?>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="<?= url_to('saldos-favor.aplicar') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Aplicar el saldo</button>
            </div>
        </section>
    </form>
<?php endif ?>
<?php if ($aplicaciones !== []): ?>
    <section class="hoja p-4">
        <h2 class="h5">Aplicaciones registradas</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>Fecha</th><th class="text-end">Importe</th><th class="text-end">Cuotas</th><th>Constancia</th><th>Cajero</th></tr></thead>
            <tbody>
            <?php foreach ($aplicaciones as $a): ?>
                <tr><td><?= esc(formato_fecha_hora($a['fecha'])) ?></td><td class="importe"><?= esc(formato_importe((string) $a['importe'], false)) ?></td><td class="text-end"><?= (int) $a['cuotas'] ?></td><td><?= esc($a['constancia_conformidad']) ?></td><td><?= esc($a['cajero']) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
<?php endif ?>
<script src="<?= base_url('assets/sigccr/cobros.js') ?>"></script>
<?php $this->endSection() ?>
