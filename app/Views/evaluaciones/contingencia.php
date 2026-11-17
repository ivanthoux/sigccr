<?php
/**
 * CU-25 Registrar Evaluación de Contingencia, pasos 3 a 5.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var array<string, mixed> $intento intento automático no completado
 * @var array<string, mixed> $internos antecedentes internos actuales del cliente
 */
use App\Libraries\Evaluacion\Contingencia;

$id = (int) $s['id'];
$marcados = (array) (old('antecedentes') ?? []);
$deudas = array_values((array) (old('deudas') ?? []));
$deudas = array_pad($deudas, 3, ['entidad' => '', 'situacion' => '', 'monto' => '', 'periodo' => '']);
$recomendacion = (string) old('recomendacion');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}/evaluacion") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Evaluación de la solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Evaluación de contingencia</h1>
        <p>Solicitud N.º <?= (int) $s['numero'] ?> · <?= esc($s['denominacion']) ?> · CUIT/CUIL <?= esc(formato_cuit($s['cuit_cuil'])) ?> · <?= esc(formato_importe($s['importe'])) ?> en <?= (int) $s['cantidad_cuotas'] ?> cuotas</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-intento">
            <h2 class="h5" id="titulo-intento">Intento automático no completado</h2>
            <p class="mb-1"><?= esc((string) $intento['motivo_no_completada']) ?></p>
            <p class="small text-body-secondary mb-0">Intento del <?= esc(formato_fecha_hora($intento['fecha_inicio'])) ?> con la versión <?= (int) $intento['version_parametros'] ?> de los parámetros. Se conserva sin cambios como antecedente (RN-21).</p>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-internos">
            <h2 class="h5" id="titulo-internos">Antecedentes internos del cliente</h2>
            <dl class="row small mb-0">
                <dt class="col-sm-7 fw-medium">Ingresos declarados</dt><dd class="col-sm-5"><?= esc(formato_importe($internos['ingresos'])) ?></dd>
                <dt class="col-sm-7 fw-medium">Cuota de la solicitud (primera)</dt><dd class="col-sm-5"><?= esc(formato_importe($s['calculo']['primera_cuota'])) ?></dd>
                <dt class="col-sm-7 fw-medium">Cuotas vigentes y comprometidas</dt><dd class="col-sm-5"><?= esc(formato_importe($internos['obligaciones'])) ?></dd>
                <dt class="col-sm-7 fw-medium">Máximo atraso interno de 12 meses</dt><dd class="col-sm-5"><?= $internos['max_atraso'] === null ? 'Sin antecedentes' : (int) $internos['max_atraso'] . ' días' ?></dd>
                <dt class="col-sm-7 fw-medium">Antigüedad como cliente</dt><dd class="col-sm-5"><?= (int) $internos['antiguedad_meses'] ?> meses</dd>
            </dl>
        </section>
    </div>
</div>

<form method="post" action="<?= site_url("admin/solicitudes/{$id}/contingencia") ?>" class="hoja p-4" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">

    <fieldset class="mb-3">
        <legend class="h6">Antecedentes verificables utilizados</legend>
        <?php foreach (Contingencia::ANTECEDENTES as $k => $t): ?>
            <div class="form-check">
                <input class="form-check-input<?= campo_invalido('antecedentes') ?>" type="checkbox" name="antecedentes[]" id="ant-<?= $k ?>" value="<?= $k ?>"<?= in_array($k, $marcados, true) ? ' checked' : '' ?><?= $k === 'consulta_manual_bcra' ? ' data-muestra="#deudas-manuales"' : '' ?>>
                <label class="form-check-label" for="ant-<?= $k ?>"><?= esc($t) ?></label>
            </div>
        <?php endforeach ?>
        <?= error_campo('antecedentes') ?>
    </fieldset>
    <div class="mb-3">
        <label for="detalle_antecedentes" class="form-label">Qué se verificó y con qué resultado</label>
        <textarea class="form-control<?= campo_invalido('detalle_antecedentes') ?>" id="detalle_antecedentes" name="detalle_antecedentes" rows="3" maxlength="2000" required><?= esc((string) old('detalle_antecedentes')) ?></textarea>
        <?= error_campo('detalle_antecedentes') ?>
    </div>

    <fieldset class="mb-3" id="deudas-manuales"<?= in_array('consulta_manual_bcra', $marcados, true) ? '' : ' hidden' ?>>
        <legend class="h6">Deudas informadas en la consulta manual <span class="fw-normal small text-body-secondary">(opcional; deje vacías las filas que no use)</span></legend>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th scope="col">Entidad</th><th scope="col">Situación (1 a 6)</th><th scope="col">Monto en pesos</th><th scope="col">Período (mm/aaaa)</th></tr></thead>
                <tbody>
                <?php foreach ($deudas as $i => $d): $err = (session('errores') ?? [])["deudas.{$i}"] ?? null; ?>
                    <tr>
                        <td><input type="text" class="form-control form-control-sm<?= $err ? ' is-invalid' : '' ?>" name="deudas[<?= $i ?>][entidad]" maxlength="150" value="<?= esc((string) ($d['entidad'] ?? ''), 'attr') ?>" aria-label="Entidad, fila <?= $i + 1 ?>"></td>
                        <td><input type="text" class="form-control form-control-sm" name="deudas[<?= $i ?>][situacion]" inputmode="numeric" maxlength="1" value="<?= esc((string) ($d['situacion'] ?? ''), 'attr') ?>" aria-label="Situación, fila <?= $i + 1 ?>"></td>
                        <td><input type="text" class="form-control form-control-sm" name="deudas[<?= $i ?>][monto]" inputmode="decimal" value="<?= esc((string) ($d['monto'] ?? ''), 'attr') ?>" aria-label="Monto, fila <?= $i + 1 ?>"></td>
                        <td><input type="text" class="form-control form-control-sm" name="deudas[<?= $i ?>][periodo]" placeholder="mm/aaaa" maxlength="7" value="<?= esc((string) ($d['periodo'] ?? ''), 'attr') ?>" aria-label="Período, fila <?= $i + 1 ?>"></td>
                    </tr>
                    <?php if ($err): ?><tr><td colspan="4" class="border-0 pt-0"><div class="invalid-feedback d-block"><?= esc($err) ?></div></td></tr><?php endif ?>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </fieldset>

    <fieldset class="mb-3">
        <legend class="h6">Recomendación del Analista</legend>
        <div class="row g-2">
            <?php foreach (['Favorable' => 'El cliente puede afrontar el crédito según los antecedentes verificados.', 'Desfavorable' => 'Los antecedentes no respaldan el otorgamiento.'] as $k => $ayuda): ?>
                <div class="col-md-6">
                    <input type="radio" class="btn-check" name="recomendacion" id="rec-<?= $k ?>" value="<?= $k ?>"<?= $recomendacion === $k ? ' checked' : '' ?> required>
                    <label class="btn btn-outline-secondary w-100 text-start opcion-resultado" for="rec-<?= $k ?>"><span class="fw-semibold d-block"><?= $k ?></span><span class="small"><?= $ayuda ?></span></label>
                </div>
            <?php endforeach ?>
        </div>
        <?= error_campo('recomendacion') ?>
    </fieldset>
    <div class="mb-3">
        <label for="justificacion" class="form-label">Justificación de la recomendación</label>
        <textarea class="form-control<?= campo_invalido('justificacion') ?>" id="justificacion" name="justificacion" rows="4" maxlength="2000" required><?= esc((string) old('justificacion')) ?></textarea>
        <?= error_campo('justificacion') ?>
    </div>
    <p class="small text-body-secondary">Al registrar, la solicitud pasa a resolución del Gerente por el procedimiento convencional. La recomendación no aprueba la solicitud (RN-21).</p>
    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= site_url("admin/solicitudes/{$id}/evaluacion") ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Registrar evaluación de contingencia</button>
    </div>
</form>
<script src="<?= recurso('assets/sigccr/solicitudes.js') ?>"></script>
<?php $this->endSection() ?>
