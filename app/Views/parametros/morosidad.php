<?php
/**
 * CU-60 Configurar Parámetros de Morosidad (RN-34, RN-35, RN-38, RN-53, RN-65).
 *
 * @var CodeIgniter\View\View $this
 * @var array{id: int, numero: int, valores: array<string, mixed>} $vigente
 * @var array<string, string> $f valores del formulario
 * @var bool $sugeridos
 * @var list<array<string, mixed>> $historial
 */
$v = static fn (string $k): string => (string) (old($k) ?? $f[$k]);
$campo = static fn (string $k, string $etiqueta, string $ayuda, string $sufijo = '', string $col = 'col-md-4'): string =>
    "<div class=\"{$col}\"><label for=\"{$k}\" class=\"form-label\">{$etiqueta}</label><div class=\"input-group\"><input type=\"text\" inputmode=\"decimal\" class=\"form-control" . campo_invalido($k) . "\" id=\"{$k}\" name=\"{$k}\" value=\"" . esc($v($k), 'attr') . '">' . ($sufijo !== '' ? "<span class=\"input-group-text\">{$sufijo}</span>" : '') . '</div>' . error_campo($k) . "<div class=\"form-text\">{$ayuda}</div></div>";
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Parámetros de morosidad</h1>
        <p>Intereses punitorios, tolerancia, morosidad avanzada, condonación y refinanciación. Versión vigente: <?= (int) $vigente['numero'] ?>.</p>
    </div>
    <a href="<?= url_to('parametros.morosidad') ?>?sugeridos=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Cargar valores iniciales</a>
</div>
<?php if ($sugeridos): ?><div class="alert alert-info">El formulario muestra los valores iniciales. Se aplican recién al registrar la nueva versión.</div><?php endif ?>
<form method="post" action="<?= url_to('parametros.morosidad') ?>" style="max-width: 64rem" novalidate>
    <?= csrf_field() ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Intereses punitorios</h2>
        <div class="row g-3">
            <?= $campo('tasa_punitoria_diaria', 'Tasa punitoria diaria', 'Interés simple diario sobre el capital vencido pendiente, sin intereses ni gastos en la base (RN-34).', '% diario') ?>
            <?= $campo('tope_sobre_tna', 'Tope sobre la TNA de la línea', 'Se aplica la menor entre la tasa diaria y este porcentaje de la TNA / 365; hasta 50 % (RN-34).', '%') ?>
            <?= $campo('tolerancia_dias', 'Tolerancia', 'Días corridos de atraso sin punitorios; superados, se cobran desde el primer día (RN-53).', 'días') ?>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Morosidad avanzada y condonación</h2>
        <div class="row g-3">
            <?= $campo('umbral_morosidad_avanzada_dias', 'Umbral de morosidad avanzada', 'Días de atraso de una cuota desde los que el cliente está en morosidad avanzada (RN-65).', 'días') ?>
            <?= $campo('limite_condonacion', 'Máximo condonable', 'Porcentaje de los punitorios pendientes que el Gerente puede condonar (RN-38).', '%') ?>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Refinanciación</h2>
        <div class="row g-3">
            <?= $campo('refinanciacion_tasa_minima', 'TNA mínima', 'Rango de tasas admitido para las propuestas de refinanciación (RN-70).', '%') ?>
            <?= $campo('refinanciacion_tasa_maxima', 'TNA máxima', '', '%') ?>
            <?= $campo('refinanciacion_cuotas_maximas', 'Cuotas máximas', 'Plazo máximo de una refinanciación.', 'cuotas') ?>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Nueva versión</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="fecha_vigencia" class="form-label">Rige desde</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_vigencia') ?>" id="fecha_vigencia" name="fecha_vigencia" min="<?= hoy_negocio() ?>" value="<?= esc((string) old('fecha_vigencia', hoy_negocio()), 'attr') ?>">
                <?= error_campo('fecha_vigencia') ?>
            </div>
            <div class="col-md-9">
                <label for="motivo" class="form-label">Motivo del cambio</label>
                <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="2" maxlength="1000"><?= esc((string) old('motivo')) ?></textarea>
                <?= error_campo('motivo') ?>
            </div>
        </div>
        <p class="small text-body-secondary mt-3 mb-0">La tasa punitoria y su tope rigen para todos los créditos desde la fecha indicada, sin modificar los días ya calculados. La tolerancia es la que se asigna a los créditos al liquidarse: los ya liquidados conservan la suya (RN-35). El umbral de morosidad avanzada se aplica a todos los clientes (RN-65).</p>
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
