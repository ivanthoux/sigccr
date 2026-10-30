<?php
/**
 * CU-99 pasos 3 y 4: pedido opcional del cliente y criterio de orden.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 */
use App\Services\Solicitudes\ComparativaService;

$criterio = (string) (old('criterio') ?? 'Monto');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('simulaciones.comparar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Simular todas las líneas</h1>
        <p><?= esc($cliente['denominacion']) ?> · ingresos declarados <?= esc(formato_importe($cliente['ingresos_declarados'])) ?></p>
    </div>
</div>
<form method="post" action="<?= url_to('simulaciones.comparar') ?>" class="hoja p-4" style="max-width: 48rem" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
    <p class="small">Se calcula una sola capacidad de pago y, para cada línea que admite al cliente, el monto máximo que le puede prestar y una alternativa recomendada. Deje vacíos el importe y las cuotas si el cliente no sabe cuánto necesita.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="importe" class="form-label">Importe deseado <span class="text-body-secondary fw-normal">(opcional)</span></label>
            <input type="text" class="form-control<?= campo_invalido('importe') ?>" id="importe" name="importe" inputmode="decimal" placeholder="500.000,00" value="<?= esc((string) old('importe'), 'attr') ?>">
            <?= error_campo('importe') ?>
        </div>
        <div class="col-md-6">
            <label for="cantidad_cuotas" class="form-label">Cuotas preferidas <span class="text-body-secondary fw-normal">(opcional)</span></label>
            <input type="text" class="form-control<?= campo_invalido('cantidad_cuotas') ?>" id="cantidad_cuotas" name="cantidad_cuotas" inputmode="numeric" value="<?= esc((string) old('cantidad_cuotas'), 'attr') ?>">
            <?= error_campo('cantidad_cuotas') ?>
        </div>
        <fieldset class="col-12">
            <legend class="form-label fs-6">Ordenar por</legend>
            <?php foreach (ComparativaService::CRITERIOS as $k => $t): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="criterio" id="criterio-<?= $k ?>" value="<?= $k ?>"<?= $criterio === $k ? ' checked' : '' ?>>
                    <label class="form-check-label" for="criterio-<?= $k ?>"><?= esc($t) ?></label>
                </div>
            <?php endforeach ?>
            <?= error_campo('criterio') ?>
        </fieldset>
    </div>
    <div class="text-end mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-columns-gap me-1" aria-hidden="true"></i>Comparar líneas</button></div>
</form>
<?php $this->endSection() ?>
