<?php
/**
 * CU-33 Registrar Cobro en Ventanilla, pasos 3 a 7 (A2 a A4, A8): obligaciones pendientes del cliente, selección de
 * cuotas, importe a cobrar y medios de pago bancarios (RN-29, RN-77, D-12).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var array<string, mixed>|null $jornada
 * @var list<array<string, mixed>> $creditos
 * @var string $total_vencido
 * @var int $max_atraso
 * @var bool $morosidad_avanzada
 * @var int $umbral
 * @var list<int> $ordenes_pendientes
 */
use App\Libraries\Caja\MediosCobro;

$sel = (array) old('cuotas');
$mediosOld = array_values((array) old('medios'));
$etiquetas = ['Punitorio' => 'Punitorios', 'InteresOrdinario' => 'Interés', 'GastosCargos' => 'Gastos', 'IVA' => 'IVA', 'NoCapitalizable' => 'No capitalizable', 'Capital' => 'Capital'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('cobros.registrar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Cobro en ventanilla</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?><?= $jornada ? ' · caja ' . esc($jornada['codigo']) : '' ?></p>
    </div>
</div>
<?php if ($jornada === null): ?>
    <div class="alert alert-warning">No tiene una caja abierta hoy: <a href="<?= url_to('cajas.abrir') ?>">ábrala</a> para registrar el cobro (CU-31, RN-28).</div>
<?php endif ?>
<?php if ($creditos === []): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene cuotas pendientes de pago (A2).</p></div>
<?php else: ?>
    <?php if ($morosidad_avanzada): ?>
        <div class="alert alert-danger">
            <strong>Morosidad avanzada:</strong> el cliente tiene cuotas con <?= (int) $max_atraso ?> días de atraso (umbral: <?= (int) $umbral ?> días, RN-65) y adeuda <?= esc(formato_importe($total_vencido)) ?> vencidos.
            Puede cobrar informándolo al cliente o derivarlo al Analista de Crédito para evaluar una refinanciación (CU-39).
        </div>
    <?php endif ?>
    <?php if ($ordenes_pendientes !== []): ?>
        <div class="alert alert-warning">
            <strong>Pago electrónico pendiente:</strong> hay cuotas incluidas en la orden N.º <?= esc(implode(', N.º ', $ordenes_pendientes)) ?>, aún vigente.
            Si el cliente la paga además de este cobro, el importe duplicado quedará como saldo a favor (CU-33 A6, RN-57).
        </div>
    <?php endif ?>
    <form method="post" action="<?= url_to('cobros.registrar') ?>" class="formulario-cobro" novalidate data-confirmar="¿Confirma el cobro con los medios informados?">
        <?= csrf_field() ?>
        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
        <?php if (campo_invalido('seleccion') !== ''): ?><div class="alert alert-danger"><?= esc((string) (session('errores') ?? [])['seleccion']) ?></div><?php endif ?>
        <?php foreach ($creditos as $cr): ?>
            <section class="hoja p-4 mb-3" aria-labelledby="titulo-credito-<?= (int) $cr['id'] ?>">
                <h2 class="h5" id="titulo-credito-<?= (int) $cr['id'] ?>">Crédito N.º <?= (int) $cr['numero'] ?> · <?= esc($cr['linea']) ?> <span class="badge <?= $cr['estado'] === 'EnMora' ? 'text-bg-danger' : 'text-bg-success' ?> ms-1"><?= $cr['estado'] === 'EnMora' ? 'En mora' : 'Activo' ?></span></h2>
                <p class="small text-body-secondary">Seleccione cuotas consecutivas desde la más antigua (RN-29). Las cuotas no vencidas se cobran a su importe nominal.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col"><span class="visually-hidden">Seleccionar</span></th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Atraso</th>
                            <?php foreach ($etiquetas as $e): ?><th class="text-end"><?= esc($e) ?></th><?php endforeach ?><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($cr['cuotas'] as $q): $idc = 'q-' . (int) $q['id']; ?>
                            <tr<?= $q['dias_atraso'] > 0 ? ' class="table-warning"' : '' ?>>
                                <td><input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="cuotas[<?= (int) $cr['id'] ?>][]" value="<?= (int) $q['numero'] ?>" data-cobro-cuota data-total="<?= esc($q['total']->toFixed(2), 'attr') ?>"<?= in_array((string) $q['numero'], array_map('strval', (array) ($sel[$cr['id']] ?? [])), true) ? ' checked' : '' ?>></td>
                                <td><label for="<?= $idc ?>"><?= (int) $q['numero'] ?></label><?php if ($q['orden_pendiente'] !== null): ?> <span class="badge text-bg-warning">Orden N.º <?= (int) $q['orden_pendiente'] ?></span><?php endif ?></td>
                                <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                                <td class="text-end"><?= $q['dias_atraso'] > 0 ? (int) $q['dias_atraso'] . ' días' : '—' ?></td>
                                <?php foreach (array_keys($etiquetas) as $k): ?><td class="importe"><?= esc(formato_importe($q['conceptos'][$k], false)) ?></td><?php endforeach ?>
                                <td class="importe fw-medium"><?= esc(formato_importe($q['total'], false)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach ?>
        <section class="hoja p-4" aria-labelledby="titulo-pago">
            <h2 class="h5" id="titulo-pago">Importe y medios de pago</h2>
            <p class="mb-3">Total de las cuotas seleccionadas: <strong class="importe" data-cobro-total>$ 0,00</strong></p>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="importe" class="form-label">Importe a cobrar</label>
                    <input type="text" class="form-control<?= campo_invalido('importe', 'ayuda-importe') ?>" id="importe" name="importe" inputmode="decimal" value="<?= esc((string) old('importe'), 'attr') ?>">
                    <div id="ayuda-importe" class="form-text">Vacío cobra el total. Un importe menor se imputa por antigüedad y la última cuota queda con saldo (D-12).</div>
                    <?= error_campo('importe') ?>
                </div>
            </div>
            <fieldset>
                <legend class="h6">Medios con que paga el cliente</legend>
                <p class="small text-body-secondary">No se reciben pagos en efectivo (RN-77). La suma de los medios debe ser igual al importe a cobrar.</p>
                <?php for ($i = 0; $i < 3; $i++): $mo = (array) ($mediosOld[$i] ?? []); ?>
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small" for="medio-<?= $i ?>">Medio <?= $i + 1 ?></label>
                            <select class="form-select" id="medio-<?= $i ?>" name="medios[<?= $i ?>][medio]">
                                <?php // Los medios 2 y 3 empiezan «Sin usar»: si no, parecía que el cobro tenía tres pagos ?>
                                <?php if ($i > 0): ?><option value=""<?= ($mo['medio'] ?? '') === '' ? ' selected' : '' ?>>Sin usar</option><?php endif ?>
                                <?php foreach (MediosCobro::MEDIOS as $k => $n): ?><option value="<?= $k ?>"<?= ($mo['medio'] ?? ($i === 0 ? 'Transferencia' : '')) === $k ? ' selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="medio-importe-<?= $i ?>">Importe</label>
                            <input type="text" class="form-control" id="medio-importe-<?= $i ?>" name="medios[<?= $i ?>][importe]" inputmode="decimal" value="<?= esc((string) ($mo['importe'] ?? ''), 'attr') ?>"<?= $i === 0 ? ' data-cobro-primer-medio' : '' ?>>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small" for="medio-operacion-<?= $i ?>">N.º de operación o comprobante</label>
                            <input type="text" class="form-control" id="medio-operacion-<?= $i ?>" name="medios[<?= $i ?>][numero_operacion]" maxlength="60" value="<?= esc((string) ($mo['numero_operacion'] ?? ''), 'attr') ?>">
                        </div>
                    </div>
                <?php endfor ?>
                <?php if (campo_invalido('medios') !== ''): ?><div class="text-danger small"><?= esc((string) (session('errores') ?? [])['medios']) ?></div><?php endif ?>
            </fieldset>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="<?= url_to('cobros.registrar') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"<?= $jornada === null ? ' disabled' : '' ?>><i class="bi bi-check2 me-1" aria-hidden="true"></i>Registrar el cobro</button>
            </div>
        </section>
    </form>
<?php endif ?>
<script src="<?= recurso('assets/sigccr/cobros.js') ?>"></script>
<?php $this->endSection() ?>
