<?php
/**
 * CU-85 Registrar Movimiento de Fondos: disponibilidad (RN-71), movimientos y sus correcciones (RN-44).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $d
 * @var string $saldos
 * @var array<string, mixed>|null $proyeccion
 * @var list<array<string, mixed>> $movimientos
 */
use App\Libraries\Caja\ReglasCaja;

$hoy = date('Y-m-d');
$sel = (int) session('movimiento');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Fondos de la entidad</h1>
        <p>Disponibilidad para aprobar y desembolsar créditos (RN-71) y movimientos que no se originan en operaciones del sistema.</p>
    </div>
</div>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-disp">
    <h2 class="h5" id="titulo-disp">Disponibilidad actual</h2>
    <dl class="resumen-plan mb-2">
        <div><dt>Fondos registrados</dt><dd class="importe"><?= esc(formato_importe($d['fondos'])) ?></dd></div>
        <div><dt>Reservas y compromisos</dt><dd class="importe"><?= esc(formato_importe($d['afectaciones'])) ?></dd></div>
        <div><dt>Reserva mínima</dt><dd class="importe"><?= esc(formato_importe($d['reserva_minima'])) ?></dd></div>
        <div><dt>Disponible libre</dt><dd class="importe fw-semibold"><?= esc(formato_importe($d['libre'])) ?></dd></div>
    </dl>
    <p class="small text-body-secondary mb-0">Fondos = movimientos realizados + cobros y saldos imputados a cuotas − desembolsos. Los saldos a favor de clientes (<?= esc(formato_importe($saldos)) ?>) no integran la disponibilidad.</p>
</section>

<?php if ($proyeccion !== null): ?>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-proyeccion">
        <h2 class="h5" id="titulo-proyeccion">Última proyección a <?= (int) $proyeccion['periodo_dias'] ?> días</h2>
        <dl class="resumen-plan mb-2">
            <div><dt>Fondos al proyectar</dt><dd class="importe"><?= esc(formato_importe((string) $proyeccion['disponible_actual'])) ?></dd></div>
            <div><dt>Ingresos esperados</dt><dd class="importe"><?= esc(formato_importe((string) $proyeccion['ingresos_esperados'])) ?></dd></div>
            <div><dt>Egresos comprometidos</dt><dd class="importe"><?= esc(formato_importe((string) $proyeccion['egresos_comprometidos'])) ?></dd></div>
            <div><dt>Disponible proyectado</dt><dd class="importe fw-semibold"><?= esc(formato_importe((string) $proyeccion['disponible_proyectado'])) ?></dd></div>
        </dl>
        <p class="small text-body-secondary mb-0">Calculada el <?= esc(formato_fecha_hora($proyeccion['fecha'])) ?> por el proceso diario de las 06:00: cuotas a vencer ajustadas por el factor de cobrabilidad, menos reservas, compromisos, egresos programados y la reserva mínima (CU-92, RN-39).</p>
    </section>
<?php endif ?>

<form method="post" action="<?= url_to('fondos.movimientos') ?>" class="hoja p-4 mb-3" novalidate aria-labelledby="titulo-nuevo">
    <?= csrf_field() ?>
    <h2 class="h5" id="titulo-nuevo">Registrar un movimiento</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <label for="tipo" class="form-label">Tipo</label>
            <select class="form-select<?= campo_invalido('tipo') ?>" id="tipo" name="tipo" required>
                <option value="">Seleccione…</option>
                <?php foreach (ReglasCaja::TIPOS_FONDOS as $k => $t): ?><option value="<?= $k ?>"<?= old('tipo') === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
            </select>
            <?= error_campo('tipo') ?>
        </div>
        <fieldset class="col-md-4">
            <legend class="form-label fs-6">Carácter</legend>
            <?php foreach (['Ingreso', 'Egreso'] as $k): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="caracter" id="caracter-<?= $k ?>" value="<?= $k ?>"<?= (old('caracter') ?? 'Ingreso') === $k ? ' checked' : '' ?>>
                    <label class="form-check-label" for="caracter-<?= $k ?>"><?= $k ?></label>
                </div>
            <?php endforeach ?>
            <?= error_campo('caracter') ?>
        </fieldset>
        <div class="col-md-4">
            <div class="form-check mt-md-4">
                <input class="form-check-input" type="checkbox" id="programado" name="programado" value="1"<?= old('programado') ? ' checked' : '' ?>>
                <label class="form-check-label" for="programado">Programado (fecha futura)</label>
            </div>
        </div>
        <div class="col-md-4">
            <label for="importe" class="form-label">Importe</label>
            <input type="text" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" inputmode="decimal" value="<?= esc($sel === 0 ? (string) old('importe') : '', 'attr') ?>" required>
            <?= $sel === 0 ? error_campo('importe') : '' ?>
        </div>
        <div class="col-md-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="text" class="form-control<?= campo_invalido('fecha') ?>" id="fecha" name="fecha" placeholder="dd/mm/aaaa" value="<?= esc((string) (old('fecha') ?? formato_fecha($hoy)), 'attr') ?>" required>
            <?= error_campo('fecha') ?>
        </div>
        <div class="col-md-5">
            <label for="referencia" class="form-label">Referencia documental <span class="text-body-secondary fw-normal">(opcional)</span></label>
            <input type="text" class="form-control<?= campo_invalido('referencia') ?>" id="referencia" name="referencia" maxlength="100" value="<?= esc((string) old('referencia'), 'attr') ?>">
            <?= error_campo('referencia') ?>
        </div>
        <div class="col-12">
            <label for="concepto" class="form-label">Concepto</label>
            <input type="text" class="form-control<?= campo_invalido('concepto') ?>" id="concepto" name="concepto" maxlength="200" value="<?= esc((string) old('concepto'), 'attr') ?>" required>
            <?= error_campo('concepto') ?>
        </div>
    </div>
    <div class="text-end mt-3"><button type="submit" class="btn btn-primary">Registrar movimiento</button></div>
</form>

<section class="hoja p-3" aria-labelledby="titulo-movs">
    <h2 class="h5 px-1" id="titulo-movs">Movimientos</h2>
    <?php if ($movimientos === []): ?>
        <p class="px-1 mb-0">Todavía no hay movimientos de fondos registrados.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>N.º</th><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Estado</th><th class="text-end">Importe</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($movimientos as $m): $mid = (int) $m['id']; ?>
                    <tr>
                        <td><?= $mid ?></td>
                        <td><?= esc(formato_fecha($m['fecha'])) ?></td>
                        <td><?= esc(ReglasCaja::TIPOS_FONDOS[$m['tipo']] ?? $m['tipo']) ?></td>
                        <td class="small"><?= esc($m['concepto']) ?><?= $m['referencia'] ? ' · ' . esc($m['referencia']) : '' ?><?= $m['motivo'] ? '<br><span class="text-body-secondary">Motivo: ' . esc($m['motivo']) . '</span>' : '' ?><?= $m['correccion_id'] ? '<br><span class="text-body-secondary">Corregido por el N.º ' . (int) $m['correccion_id'] . '</span>' : '' ?></td>
                        <td><span class="badge rounded-pill <?= $m['estado'] === 'Realizado' ? 'text-bg-light border' : ($m['estado'] === 'Programado' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= esc($m['estado']) ?></span></td>
                        <td class="importe <?= $m['caracter'] === 'Egreso' ? 'text-danger' : '' ?>"><?= $m['caracter'] === 'Egreso' ? '−' : '' ?><?= esc(formato_importe($m['importe'], false)) ?></td>
                        <td class="text-end">
                            <?php if ($m['estado'] === 'Realizado' && !$m['correccion_id'] && !$m['movimiento_corregido_id']): ?>
                                <details<?= $sel === $mid ? ' open' : '' ?>><summary class="btn btn-sm btn-outline-secondary">Corregir</summary>
                                    <form method="post" action="<?= site_url("admin/fondos/{$mid}/corregir") ?>" class="mt-2 text-start" novalidate>
                                        <?= csrf_field() ?>
                                        <input type="text" class="form-control form-control-sm<?= $sel === $mid ? campo_invalido('motivo') : '' ?>" name="motivo" maxlength="300" placeholder="Motivo de la corrección" aria-label="Motivo de la corrección del movimiento <?= $mid ?>" required>
                                        <?= $sel === $mid ? error_campo('motivo') . error_campo('importe') : '' ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary mt-1">Registrar el movimiento inverso</button>
                                    </form>
                                </details>
                            <?php elseif ($m['estado'] === 'Programado'): ?>
                                <?php if (substr((string) $m['fecha'], 0, 10) <= $hoy): ?>
                                    <form method="post" action="<?= site_url("admin/fondos/{$mid}/confirmar") ?>" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Confirmar</button></form>
                                <?php endif ?>
                                <details class="d-inline-block"<?= $sel === $mid ? ' open' : '' ?>><summary class="btn btn-sm btn-outline-secondary">Anular</summary>
                                    <form method="post" action="<?= site_url("admin/fondos/{$mid}/anular") ?>" class="mt-2 text-start" novalidate>
                                        <?= csrf_field() ?>
                                        <input type="text" class="form-control form-control-sm" name="motivo" maxlength="300" placeholder="Motivo de la anulación" aria-label="Motivo de la anulación del movimiento <?= $mid ?>" required>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary mt-1">Anular</button>
                                    </form>
                                </details>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
