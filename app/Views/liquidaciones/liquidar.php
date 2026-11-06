<?php
/**
 * CU-27 Liquidar Crédito Aprobado, pasos 3 a 6 (A4: constancia firmada del contrato, A7).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 */
use App\Libraries\Solicitudes\Desembolso;

$id = (int) $s['id'];
$juridica = $s['tipo_cliente'] === 'PersonaJuridica';
$neto = $s['calculo']['neto']->toFixed(2);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('creditos.liquidar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Créditos aprobados</a></nav>
<div class="encabezado">
    <div>
        <h1>Liquidar la solicitud N.º <?= (int) $s['numero'] ?></h1>
        <p><?= esc($s['denominacion']) ?> · CUIT/CUIL <?= esc(formato_cuit($s['cuit_cuil'])) ?> · <?= esc($s['linea']) ?>, versión <?= (int) $s['version'] ?></p>
    </div>
</div>
<?php if (!$s['constancia']): ?>
    <div class="alert alert-danger">Falta la constancia firmada del contrato de préstamo en el legajo: el Analista debe incorporarla antes de liquidar (CU-103, RN-25, RN-78).</div>
<?php endif ?>
<div class="alert alert-info">La aprobación vence el <strong><?= esc(formato_fecha_hora($s['vence_aprobacion'])) ?></strong>. Reserva de fondos: <?= $s['reserva'] !== null ? esc(formato_importe($s['reserva'])) : '<strong>sin reserva</strong>' ?>.</div>

<section class="hoja p-4 mb-3" aria-labelledby="titulo-plan">
    <h2 class="h5" id="titulo-plan">Condiciones aprobadas</h2>
    <?= view('partials/plan_preliminar', ['calculo' => $s['calculo'], 'importe' => (string) $s['importe_aprobado'], 'tna' => (string) $s['tna']]) ?>
</section>

<form method="post" action="<?= site_url("admin/liquidaciones/{$id}") ?>" class="hoja p-4" novalidate<?= $s['constancia'] ? '' : ' inert' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
    <h2 class="h5">Desembolso de <?= esc(formato_importe($neto)) ?></h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="documento_verificado" class="form-label">Documento que presenta <?= $juridica ? 'el representante (' . esc((string) $s['representante_nombre']) . ')' : 'el cliente' ?></label>
            <input type="text" class="form-control<?= campo_invalido('documento_verificado') ?>" id="documento_verificado" name="documento_verificado" inputmode="numeric" value="<?= esc((string) old('documento_verificado'), 'attr') ?>" required autocomplete="off">
            <?= error_campo('documento_verificado') ?>
            <div class="form-text">Ingréselo mirando el documento original: debe coincidir con el registrado (RN-25).</div>
        </div>
        <?php if ($s['requiere_conformidad']): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input<?= campo_invalido('conformidad') ?>" type="checkbox" id="conformidad" name="conformidad" value="1"<?= old('conformidad') ? ' checked' : '' ?>>
                    <label class="form-check-label" for="conformidad">El cliente prestó su conformidad con el plan aprobado por el Gerente y firmó la constancia (RN-22).</label>
                </div>
                <?= error_campo('conformidad') ?>
            </div>
        <?php endif ?>
        <div class="col-12">
            <p class="mb-0"><strong>Medio de desembolso:</strong> transferencia a una cuenta bancaria del cliente (CBU). No se admiten pagos en efectivo (Ley 25.345, art. 1) ni CVU de billeteras virtuales (D-27).</p>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-7">
                    <label for="cbu" class="form-label">CBU de destino</label>
                    <input type="text" class="form-control<?= campo_invalido('cbu') ?>" id="cbu" name="cbu" inputmode="numeric" maxlength="26" value="<?= esc((string) old('cbu'), 'attr') ?>">
                    <?= error_campo('cbu') ?>
                </div>
                <div class="col-md-5">
                    <label for="cuit_titular" class="form-label">CUIT/CUIL del titular</label>
                    <input type="text" class="form-control<?= campo_invalido('cuit_titular') ?>" id="cuit_titular" name="cuit_titular" inputmode="numeric" value="<?= esc((string) old('cuit_titular'), 'attr') ?>">
                    <?= error_campo('cuit_titular') ?>
                </div>
                <div class="col-12">
                    <label for="evidencia_titularidad" class="form-label">Verificación de la titularidad</label>
                    <input type="text" class="form-control<?= campo_invalido('evidencia_titularidad') ?>" id="evidencia_titularidad" name="evidencia_titularidad" maxlength="300" placeholder="Por ejemplo: constancia de CBU emitida por el banco, a nombre del cliente" value="<?= esc((string) old('evidencia_titularidad'), 'attr') ?>">
                    <?= error_campo('evidencia_titularidad') ?>
                </div>
                <div class="col-md-5">
                    <label for="comprobante" class="form-label">N.º de operación</label>
                    <input type="text" class="form-control<?= campo_invalido('comprobante') ?>" id="comprobante" name="comprobante" maxlength="60" value="<?= esc((string) old('comprobante'), 'attr') ?>">
                    <?= error_campo('comprobante') ?>
                </div>
                <div class="col-md-3">
                    <label for="fecha_transferencia" class="form-label">Fecha</label>
                    <input type="text" class="form-control<?= campo_invalido('fecha_transferencia') ?>" id="fecha_transferencia" name="fecha_transferencia" placeholder="dd/mm/aaaa" value="<?= esc((string) (old('fecha_transferencia') ?? formato_fecha(date('Y-m-d'))), 'attr') ?>">
                    <?= error_campo('fecha_transferencia') ?>
                </div>
                <div class="col-md-4">
                    <label for="importe_transferencia" class="form-label">Importe transferido</label>
                    <input type="text" class="form-control<?= campo_invalido('importe_transferencia') ?>" id="importe_transferencia" name="importe_transferencia" inputmode="decimal" value="<?= esc((string) old('importe_transferencia'), 'attr') ?>">
                    <?= error_campo('importe_transferencia') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="<?= url_to('creditos.liquidar') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Registrar el desembolso</button>
    </div>
</form>
<script src="<?= base_url('assets/sigccr/solicitudes.js') ?>"></script>
<?php $this->endSection() ?>
