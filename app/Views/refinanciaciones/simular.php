<?php
/**
 * CU-39 Simular Plan de Refinanciación, pasos 3 a 6 (A1, A5): créditos del cliente con su deuda actualizada; solo los
 * En mora se admiten (RN-70), y la simulación comprende todas sus cuotas no pagadas.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $creditos
 * @var array{id: int, tasa_minima: string, tasa_maxima: string, cuotas_maximas: int} $limites
 */
$sel = array_map('intval', (array) old('creditos'));
$admitidos = array_filter($creditos, static fn ($c) => $c['admitido']);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('refinanciaciones.simular') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Simular refinanciación</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?></p>
    </div>
</div>
<?php if ($admitidos === []): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene créditos en mora: no hay obligaciones admitidas para refinanciar (A1, RN-70).</p></div>
<?php else: ?>
    <form method="post" action="<?= url_to('refinanciaciones.simular') ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
        <section class="hoja p-4 mb-3" aria-labelledby="titulo-deuda">
            <h2 class="h5" id="titulo-deuda">Deuda al día de hoy</h2>
            <p class="small text-body-secondary">Se comprenden todas las cuotas no pagadas de cada crédito seleccionado. El capital se refinancia; los intereses vencidos, gastos, IVA y punitorios forman un concepto no capitalizable que se reparte sin interés entre las nuevas cuotas (RN-70).</p>
            <?php if (campo_invalido('creditos') !== ''): ?><div class="alert alert-danger"><?= esc((string) (session('errores') ?? [])['creditos']) ?></div><?php endif ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th scope="col"><span class="visually-hidden">Incluir</span></th><th>Crédito</th><th>Estado</th><th class="text-end">Cuotas</th><th class="text-end">Capital</th><th class="text-end">Interés vencido</th><th class="text-end">Gastos e IVA</th><th class="text-end">Punitorios</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($creditos as $c): $idc = 'c-' . (int) $c['id']; ?>
                        <tr<?= $c['admitido'] ? '' : ' class="text-body-secondary"' ?>>
                            <td><input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="creditos[]" value="<?= (int) $c['id'] ?>"<?= $c['admitido'] ? '' : ' disabled' ?><?= in_array((int) $c['id'], $sel, true) || ($sel === [] && $c['admitido'] && count($admitidos) === 1) ? ' checked' : '' ?>></td>
                            <td><label for="<?= $idc ?>"><?= (int) $c['numero'] ?></label>
                                <?php if ($c['propuesta_vigente'] !== null): ?><a class="badge text-bg-warning text-decoration-none" href="<?= site_url('admin/refinanciaciones/' . (int) $c['propuesta_vigente']['id']) ?>">Propuesta N.º <?= (int) $c['propuesta_vigente']['numero'] ?></a><?php endif ?></td>
                            <td><?= $c['admitido'] ? 'En mora' : 'Activo (no admitido)' ?></td>
                            <td class="text-end"><?= (int) $c['cuotas'] ?></td>
                            <td class="importe"><?= esc(formato_importe($c['capital_pendiente'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($c['interes_vencido'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($c['gastos_iva'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($c['punitorios'], false)) ?></td>
                            <td class="importe fw-medium"><?= esc(formato_importe($c['total'], false)) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="hoja p-4" aria-labelledby="titulo-condiciones">
            <h2 class="h5" id="titulo-condiciones">Condiciones del nuevo plan</h2>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="tasa" class="form-label">TNA (%)</label>
                    <input type="text" class="form-control<?= campo_invalido('tasa', 'ayuda-tasa') ?>" id="tasa" name="tasa" inputmode="decimal" value="<?= esc((string) old('tasa'), 'attr') ?>">
                    <div id="ayuda-tasa" class="form-text">Entre <?= esc(formato_porcentaje($limites['tasa_minima'])) ?> y <?= esc(formato_porcentaje($limites['tasa_maxima'])) ?>.</div>
                    <?= error_campo('tasa') ?>
                </div>
                <div class="col-md-3">
                    <label for="cuotas" class="form-label">Cantidad de cuotas</label>
                    <input type="text" class="form-control<?= campo_invalido('cuotas', 'ayuda-cuotas') ?>" id="cuotas" name="cuotas" inputmode="numeric" value="<?= esc((string) old('cuotas'), 'attr') ?>">
                    <div id="ayuda-cuotas" class="form-text">Hasta <?= (int) $limites['cuotas_maximas'] ?>. Primer vencimiento a los 30 días de la aplicación.</div>
                    <?= error_campo('cuotas') ?>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="<?= url_to('refinanciaciones.simular') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Simular</button>
            </div>
        </section>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
