<?php
/**
 * CU-36 Registrar Cierre de Caja, pasos 3 a 8 (A1 a A3, A5): conciliación de cada medio de pago (RN-32, D-30).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $j jornada abierta con su resumen
 */
use App\Libraries\Caja\ReglasCaja;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('cajas.abrir') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mi caja</a></nav>
<div class="encabezado">
    <div>
        <h1>Cerrar caja</h1>
        <p><?= esc($j['codigo'] . ' · ' . $j['denominacion']) ?> · abierta el <?= esc(formato_fecha_hora($j['apertura'])) ?></p>
    </div>
</div>
<?php if ($j['anterior']): ?><div class="alert alert-warning">La jornada es de una fecha anterior: ciérrela para volver a operar (RN-28).</div><?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-resumen">
    <h2 class="h5" id="titulo-resumen">Resumen de la jornada</h2>
    <?= view('cajas/_resumen', ['j' => $j]) ?>
    <p class="small text-body-secondary mt-2 mb-0">Las órdenes de pago electrónico pendientes no se computan como cobros confirmados (RN-32); se incorporan con los pagos electrónicos (iteración 3).</p>
</section>
<form method="post" action="<?= url_to('cajas.cerrar') ?>" class="hoja p-4" style="max-width: 64rem" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="version_fila" value="<?= (int) $j['version_fila'] ?>">
    <h2 class="h5">Conciliación</h2>
    <p class="small">Para cada medio, ingrese el total acreditado en las cuentas de la entidad según el extracto bancario o el resumen del procesador de tarjetas. Si no coincide con lo registrado, explique la diferencia.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Medio</th><th class="text-end">Registrado</th><th style="width: 12rem">Acreditado</th><th>Explicación de la diferencia</th></tr></thead>
            <tbody>
            <?php foreach (ReglasCaja::MEDIOS_VENTANILLA as $m => $nombre): $reg = $j['resumen']['por_medio'][$m]; ?>
                <tr>
                    <th scope="row" class="fw-medium"><label for="acreditado-<?= $m ?>"><?= esc($nombre) ?></label></th>
                    <td class="importe"><?= esc(formato_importe($reg)) ?></td>
                    <td>
                        <input type="text" class="form-control<?= campo_invalido("acreditado_{$m}") ?>" id="acreditado-<?= $m ?>" name="acreditado[<?= $m ?>]" inputmode="decimal" placeholder="<?= $reg === '0.00' ? '0,00' : '' ?>" value="<?= esc((string) (((array) old('acreditado'))[$m] ?? ''), 'attr') ?>"<?= $reg === '0.00' ? '' : ' required' ?>>
                        <?= error_campo("acreditado_{$m}") ?>
                    </td>
                    <td>
                        <label class="visually-hidden" for="explicacion-<?= $m ?>">Explicación de la diferencia de <?= esc(mb_strtolower($nombre)) ?></label>
                        <textarea class="form-control<?= campo_invalido("explicacion_{$m}") ?>" id="explicacion-<?= $m ?>" name="explicacion[<?= $m ?>]" rows="1" maxlength="500"><?= esc((string) (((array) old('explicacion'))[$m] ?? '')) ?></textarea>
                        <?= error_campo("explicacion_{$m}") ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="<?= url_to('cajas.abrir') ?>" class="btn btn-outline-secondary">Mantener abierta</a>
        <button type="submit" class="btn btn-primary">Cerrar la caja</button>
    </div>
</form>
<?php $this->endSection() ?>
