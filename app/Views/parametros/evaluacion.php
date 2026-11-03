<?php
/**
 * CU-58 Configurar Parámetros de Evaluación Crediticia (RN-69, D-13).
 *
 * @var CodeIgniter\View\View $this
 * @var array{id: int, numero: int, valores: array<string, mixed>} $vigente
 * @var array<string, mixed> $f valores del formulario
 * @var bool $sugeridos
 * @var list<array<string, mixed>> $historial
 */
use App\Libraries\Validacion\ValidadorParametrosEvaluacion as V;

if (old('motivo') !== null) {
    foreach (['pesos', 'bcra', 'comportamiento', 'endeudamiento', 'antiguedad', 'categorias', 'resultados', 'vigencia_dias'] as $k) {
        $f[$k] = old($k) ?? $f[$k];
    }
    $f['situaciones_excluyentes'] = (array) (old('situaciones_excluyentes') ?? []);
}
$valor = static fn (string $nombre, $v, string $extra = '') => '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end' . campo_invalido(str_replace(['[', ']'], ['.', ''], $nombre)) . '" name="' . $nombre . '" value="' . esc((string) $v, 'attr') . '" ' . $extra . '>';
$tramos = static function (string $grupo, array $filas, string $etiquetaLimite, string $sufijo) use ($valor): string {
    $html = '<table class="table table-sm align-middle mb-2"><thead><tr><th>' . $etiquetaLimite . '</th><th class="text-end" style="width:7rem">Valor</th></tr></thead><tbody>';
    $filas = array_pad($filas, 4, ['limite' => '', 'valor' => '']);
    foreach ($filas as $i => $t) {
        $html .= '<tr><td><div class="input-group input-group-sm">' . $valor("{$grupo}[tramos][{$i}][limite]", $t['limite'], 'aria-label="' . esc($etiquetaLimite, 'attr') . '"') . "<span class=\"input-group-text\">{$sufijo}</span></div></td><td>" . $valor("{$grupo}[tramos][{$i}][valor]", $t['valor'], 'aria-label="Valor"') . '</td></tr>';
    }

    return $html . '</tbody></table>' . error_campo("{$grupo}.tramos");
};
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Parámetros de evaluación crediticia</h1>
        <p>Motor que combina la Central de Deudores del BCRA con los antecedentes internos. Versión vigente: <?= (int) $vigente['numero'] ?>.</p>
    </div>
    <a href="<?= url_to('parametros.evaluacion') ?>?sugeridos=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Cargar valores sugeridos</a>
</div>

<?php if ($sugeridos): ?>
    <div class="alert alert-info">El formulario muestra los valores sugeridos de RN-69. Se aplican recién al registrar la nueva versión.</div>
<?php endif ?>

<form method="post" action="<?= url_to('parametros.evaluacion') ?>" style="max-width: 64rem" novalidate>
    <?= csrf_field() ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Pesos de los indicadores</h2>
        <p class="small text-body-secondary">El puntaje (0 a 100) es la suma de cada indicador por su peso. Los pesos deben sumar 100 %.</p>
        <div class="row g-3">
            <?php foreach (V::INDICADORES as $k => $nombre): ?>
                <div class="col-6 col-md-3">
                    <label class="form-label small"><?= esc($nombre) ?></label>
                    <div class="input-group input-group-sm"><?= $valor("pesos[{$k}]", $f['pesos'][$k]) ?><span class="input-group-text">%</span></div>
                    <?= error_campo("pesos.{$k}") ?>
                </div>
            <?php endforeach ?>
        </div>
        <?= error_campo('pesos') ?>
    </section>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <section class="hoja p-4 h-100">
                <h2 class="h5">Situación en el sistema financiero (BCRA)</h2>
                <p class="small text-body-secondary">Valor según la peor situación informada en los últimos 12 meses.</p>
                <div class="row g-2">
                    <?php foreach (['1' => 'Situación 1 (normal)', '2' => 'Situación 2', '3' => 'Situación 3', '4' => 'Situación 4', '5' => 'Situación 5', '6' => 'Situación 6', 'sin_deudas' => 'Sin deudas informadas'] as $k => $t): ?>
                        <div class="col-6"><label class="form-label small mb-0"><?= $t ?></label><?= $valor("bcra[{$k}]", $f['bcra'][$k]) ?><?= error_campo("bcra.{$k}") ?></div>
                    <?php endforeach ?>
                </div>
                <fieldset class="mt-3">
                    <legend class="form-label small mb-1">Situaciones vigentes que rechazan cualquiera sea el puntaje</legend>
                    <?php for ($s = 1; $s <= 6; $s++): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="excl-<?= $s ?>" name="situaciones_excluyentes[]" value="<?= $s ?>"<?= in_array($s, array_map('intval', $f['situaciones_excluyentes']), true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="excl-<?= $s ?>"><?= $s ?></label>
                        </div>
                    <?php endfor ?>
                </fieldset>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="hoja p-4 h-100">
                <h2 class="h5">Comportamiento de pago interno</h2>
                <p class="small text-body-secondary">Máximo atraso de los últimos 12 meses, hasta el límite inclusive.</p>
                <?= $tramos('comportamiento', $f['comportamiento']['tramos'], 'Atraso hasta', 'días') ?>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small mb-0">Más atraso</label><?= $valor('comportamiento[resto]', $f['comportamiento']['resto']) ?><?= error_campo('comportamiento.resto') ?></div>
                    <div class="col-6"><label class="form-label small mb-0">Sin antecedentes</label><?= $valor('comportamiento[sin_antecedentes]', $f['comportamiento']['sin_antecedentes']) ?><?= error_campo('comportamiento.sin_antecedentes') ?></div>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="hoja p-4 h-100">
                <h2 class="h5">Endeudamiento</h2>
                <p class="small text-body-secondary">Cuotas vigentes y comprometidas sobre ingresos declarados.</p>
                <?= $tramos('endeudamiento', $f['endeudamiento']['tramos'], 'Relación hasta', '%') ?>
                <label class="form-label small mb-0">Relación mayor</label><?= $valor('endeudamiento[resto]', $f['endeudamiento']['resto']) ?><?= error_campo('endeudamiento.resto') ?>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="hoja p-4 h-100">
                <h2 class="h5">Antigüedad como cliente</h2>
                <p class="small text-body-secondary">De mayor a menor: desde esa cantidad de meses inclusive.</p>
                <?= $tramos('antiguedad', $f['antiguedad']['tramos'], 'Desde', 'meses') ?>
                <label class="form-label small mb-0">Menor antigüedad</label><?= $valor('antiguedad[resto]', $f['antiguedad']['resto']) ?><?= error_campo('antiguedad.resto') ?>
            </section>
        </div>
    </div>

    <section class="hoja p-4 mb-3">
        <h2 class="h5">Categorías de riesgo y resultado</h2>
        <?= error_campo('resultados') ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Categoría</th><th style="width:10rem">Puntaje mínimo</th><th>Resultado</th></tr></thead>
                <tbody>
                <?php foreach (V::CATEGORIAS as $c): ?>
                    <tr>
                        <th scope="row"><?= $c ?></th>
                        <td><?= $valor("categorias[{$c}]", $f['categorias'][$c]) ?><?= error_campo("categorias.{$c}") ?></td>
                        <td>
                            <select class="form-select form-select-sm<?= campo_invalido("resultados.{$c}") ?>" name="resultados[<?= $c ?>]" aria-label="Resultado de la categoría <?= $c ?>">
                                <?php foreach (V::RESULTADOS as $k => $t): ?><option value="<?= $k ?>"<?= ($f['resultados'][$c] ?? '') === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="hoja p-4 mb-3">
        <h2 class="h5">Nueva versión</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="vigencia_dias" class="form-label">Vigencia de la evaluación</label>
                <div class="input-group"><?= str_replace('form-control-sm ', '', $valor('vigencia_dias', $f['vigencia_dias'], 'id="vigencia_dias"')) ?><span class="input-group-text">días</span></div>
                <?= error_campo('vigencia_dias') ?>
            </div>
            <div class="col-md-3">
                <label for="fecha_vigencia" class="form-label">Rige desde</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_vigencia') ?>" id="fecha_vigencia" name="fecha_vigencia" min="<?= date('Y-m-d') ?>" value="<?= esc((string) old('fecha_vigencia', date('Y-m-d')), 'attr') ?>">
                <?= error_campo('fecha_vigencia') ?>
            </div>
            <div class="col-md-6">
                <label for="motivo" class="form-label">Motivo del cambio</label>
                <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="2" minlength="5" maxlength="1000"><?= esc((string) old('motivo')) ?></textarea>
                <?= error_campo('motivo') ?>
            </div>
        </div>
        <?= view('partials/confirmar_cambio') ?>
        <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary">Registrar nueva versión</button></div>
    </section>
</form>

<?php if ($historial !== []): ?>
    <section class="hoja p-4" style="max-width: 64rem">
        <h2 class="h5">Versiones anteriores</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>Versión</th><th>Vigente desde</th><th>Responsable</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($historial as $h): ?>
                <tr><td><?= (int) $h['numero'] ?></td><td><?= esc(formato_fecha_hora((string) $h['vigente_desde'])) ?></td><td><?= esc((string) ($h['usuario'] ?? 'Instalación')) ?></td><td><?= esc((string) $h['motivo']) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
