<?php
/**
 * CU-59 Configurar Parámetros de Estructuración Automática (D-22).
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
        <h1>Parámetros de estructuración automática</h1>
        <p>Capacidad de pago, monto máximo financiable y alternativas de financiación. Versión vigente: <?= (int) $vigente['numero'] ?>. Las tasas, gastos, montos y plazos se configuran en cada línea.</p>
    </div>
    <a href="<?= url_to('parametros.estructuracion') ?>?sugeridos=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Cargar valores iniciales</a>
</div>
<?php if ($sugeridos): ?><div class="alert alert-info">El formulario muestra los valores iniciales. Se aplican recién al registrar la nueva versión.</div><?php endif ?>
<form method="post" action="<?= url_to('parametros.estructuracion') ?>" style="max-width: 64rem" novalidate>
    <?= csrf_field() ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Capacidad de pago y monto máximo</h2>
        <div class="row g-3">
            <?= $campo('porcentaje_afectacion', 'Afectación máxima de los ingresos', 'Capacidad = ingresos × afectación − cuotas vigentes y comprometidas (RN-18).', '%') ?>
            <?= $campo('tope_global_cliente', 'Tope global por cliente', 'Opcional. Limita el monto máximo financiable de cualquier línea; vacío, sin tope.', '$') ?>
            <?= $campo('paso_redondeo', 'Redondeo del monto máximo', 'El monto máximo se redondea hacia abajo a este múltiplo.', '$') ?>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Alternativas, impuestos y vigencia</h2>
        <div class="row g-3">
            <?= $campo('escalones', 'Escalones de importe', 'Porcentajes del monto máximo separados por espacios, empezando en 100 (por ejemplo, 100 75 50).', '%', 'col-md-5') ?>
            <?= $campo('alicuota_iva', 'Alícuota de IVA', 'Sobre intereses, gastos y punitorios (RN-68); 0 si la operación está exenta.', '%', 'col-md-3') ?>
            <?= $campo('vigencia_alternativas_dias', 'Vigencia de las alternativas', 'Días corridos desde su generación (RN-48).', 'días', 'col-md-4') ?>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Nueva versión</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="fecha_vigencia" class="form-label">Rige desde</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_vigencia') ?>" id="fecha_vigencia" name="fecha_vigencia" min="<?= date('Y-m-d') ?>" value="<?= esc((string) old('fecha_vigencia', date('Y-m-d')), 'attr') ?>">
                <?= error_campo('fecha_vigencia') ?>
            </div>
            <div class="col-md-9">
                <label for="motivo" class="form-label">Motivo del cambio</label>
                <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="2" maxlength="1000"><?= esc((string) old('motivo')) ?></textarea>
                <?= error_campo('motivo') ?>
            </div>
        </div>
        <p class="small text-body-secondary mt-2 mb-0">Las simulaciones, alternativas y ofertas ya generadas conservan sus condiciones durante su vigencia (RN-15).</p>
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
