<?php
/**
 * CU-44 Condonar Intereses Punitorios, pasos 3 a 6 (A3): cuotas con punitorios pendientes, máximo condonable,
 * importe y fundamento (RN-38).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $cuotas
 * @var string $limite
 */
$sel = array_map('intval', (array) old('cuotas'));
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('punitorios.condonar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Condonar punitorios</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?> · máximo condonable: <?= esc(formato_porcentaje($limite)) ?> de los punitorios pendientes (RN-38)</p>
    </div>
</div>
<?php if ($cuotas === []): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene cuotas con intereses punitorios pendientes (A3).</p></div>
<?php else: ?>
    <form method="post" action="<?= url_to('punitorios.condonar') ?>" novalidate data-confirmar="¿Confirma la condonación? Reduce los punitorios pendientes y su IVA; el capital no cambia.">
        <?= csrf_field() ?>
        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
        <section class="hoja p-4 mb-3">
            <?php if (campo_invalido('cuotas') !== ''): ?><div class="alert alert-danger"><?= esc((string) (session('errores') ?? [])['cuotas']) ?></div><?php endif ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th scope="col"><span class="visually-hidden">Seleccionar</span></th><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Atraso</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Gastos</th><th class="text-end">Punitorios</th><th class="text-end">IVA punitorios</th></tr></thead>
                    <tbody>
                    <?php foreach ($cuotas as $q): $idc = 'q-' . (int) $q['id']; ?>
                        <tr>
                            <td><input class="form-check-input" type="checkbox" id="<?= $idc ?>" name="cuotas[]" value="<?= (int) $q['id'] ?>"<?= in_array((int) $q['id'], $sel, true) ? ' checked' : '' ?>></td>
                            <td><?= (int) $q['credito_numero'] ?></td>
                            <td><label for="<?= $idc ?>"><?= (int) $q['numero'] ?></label></td>
                            <td><?= esc(formato_fecha($q['vencimiento'])) ?></td>
                            <td class="text-end"><?= (int) $q['dias_atraso'] ?> días</td>
                            <td class="importe"><?= esc(formato_importe($q['conceptos']['Capital'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($q['conceptos']['InteresOrdinario'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($q['conceptos']['GastosCargos'], false)) ?></td>
                            <td class="importe fw-medium"><?= esc(formato_importe($q['conceptos']['Punitorio'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe(round((float) $q['iva_punitorio_pendiente'], 2), false)) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="hoja p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="importe" class="form-label">Importe a condonar (sin IVA)</label>
                    <input type="text" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" inputmode="decimal" value="<?= esc((string) old('importe'), 'attr') ?>">
                    <?= error_campo('importe') ?>
                </div>
                <div class="col-md-9">
                    <label for="fundamento" class="form-label">Fundamento</label>
                    <textarea class="form-control<?= campo_invalido('fundamento') ?>" id="fundamento" name="fundamento" rows="2" maxlength="1000"><?= esc((string) old('fundamento')) ?></textarea>
                    <?= error_campo('fundamento') ?>
                </div>
            </div>
            <p class="small text-body-secondary mt-2">Se aplica desde la cuota más antigua y reduce en la misma proporción el IVA de los punitorios. Si las cuotas integran una propuesta de refinanciación vigente, la propuesta quedará Observada (RN-36).</p>
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url_to('punitorios.condonar') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar la condonación</button>
            </div>
        </section>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
