<?php
/**
 * CU-52 Registrar Respuesta a Oferta Crediticia, pasos 4 a 8 (A1, A4, A5): condiciones de la oferta, elección del
 * importe y las cuotas entre las alternativas permitidas hoy, y aceptación o rechazo con confirmación.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $o
 */
use App\Libraries\Campanas\ReglasCampanas;

$vigente = $o['estado'] === 'Vigente' && (int) $o['en_vigencia'] === 1 && $o['campana_estado'] === 'Activa';
$elegida = (string) (old('alternativa') ?? ($o['aceptables'] !== [] ? ReglasCampanas::clave($o['aceptables'][0]) : ''));
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.propuestas') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Refinanciaciones y ofertas</a></nav>
<div class="encabezado">
    <div>
        <h1>Oferta de crédito N.º <?= (int) $o['id'] ?></h1>
        <p><?= esc($o['linea']) ?> · campaña N.º <?= (int) $o['campana_numero'] ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" style="max-width: 48rem" aria-labelledby="t-condiciones">
    <h2 class="h5" id="t-condiciones">Condiciones</h2>
    <dl class="resumen-plan">
        <div><dt>Importe máximo</dt><dd class="importe"><?= esc(formato_importe((string) $o['importe_maximo'])) ?></dd></div>
        <div><dt>Plazo</dt><dd><?= (int) $o['cantidad_cuotas'] ?> cuotas</dd></div>
        <?php if ($o['primera_cuota'] !== null): ?><div><dt>Cuota estimada</dt><dd class="importe"><?= esc(formato_importe($o['primera_cuota'])) ?></dd></div><?php endif ?>
        <div><dt>TNA</dt><dd><?= esc(formato_porcentaje((string) $o['tna'])) ?></dd></div>
        <div><dt>TEA</dt><dd><?= esc(formato_porcentaje((string) $o['tea'])) ?></dd></div>
        <div><dt>CFTEA</dt><dd><?= esc(formato_porcentaje((string) $o['cftea'])) ?></dd></div>
    </dl>
    <p class="small mb-0">Vigente hasta el <?= esc(formato_fecha_hora((string) $o['vence'])) ?>. La cuota estimada es la primera, con el gasto administrativo y el IVA; el costo financiero total incluye el gasto de otorgamiento (RN-63).</p>
</section>
<?php if (!$vigente): ?>
    <div class="alert alert-secondary" style="max-width: 48rem">
        <?php if ($o['estado'] === 'Aceptada'): ?>Aceptó esta oferta el <?= esc(formato_fecha_hora((string) $o['fecha_respuesta'])) ?>: generó la <a href="<?= site_url('portal/solicitudes/' . (int) $o['solicitud_id']) ?>">solicitud N.º <?= (int) $o['solicitud_numero'] ?></a>.
        <?php elseif ($o['estado'] === 'Rechazada'): ?>Rechazó esta oferta el <?= esc(formato_fecha_hora((string) $o['fecha_respuesta'])) ?>.
        <?php else: ?>La oferta venció: ya no puede responderse.<?php endif ?>
    </div>
<?php elseif ($o['aceptables'] === []): ?>
    <div class="alert alert-warning" style="max-width: 48rem">Con sus ingresos y obligaciones de hoy no hay alternativas dentro de su capacidad de pago para esta oferta. Puede rechazarla o consultar en la sucursal.</div>
<?php endif ?>
<?php if ($vigente): ?>
    <?php if ($o['aceptables'] !== []): ?>
        <form method="post" action="<?= site_url('portal/ofertas/' . (int) $o['id'] . '/aceptar') ?>" class="hoja p-4 mb-3" style="max-width: 48rem" novalidate data-confirmar="¿Acepta la oferta con la alternativa elegida? Se generará una solicitud de crédito; no implica el desembolso ni genera deuda.">
            <?= csrf_field() ?>
            <input type="hidden" name="version_fila" value="<?= (int) $o['version_fila'] ?>">
            <h2 class="h5">Aceptar la oferta</h2>
            <fieldset>
                <legend class="form-label fs-6">Elija el importe y la cantidad de cuotas</legend>
                <?php if (campo_invalido('alternativa') !== ''): ?><div class="alert alert-danger py-2"><?= esc((string) (session('errores') ?? [])['alternativa']) ?></div><?php endif ?>
                <?php foreach ($o['aceptables'] as $i => $a): $clave = ReglasCampanas::clave($a); ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="alternativa" id="alt-<?= $i ?>" value="<?= esc($clave, 'attr') ?>"<?= $elegida === $clave ? ' checked' : '' ?>>
                        <label class="form-check-label" for="alt-<?= $i ?>"><strong><?= esc(formato_importe($a['importe'])) ?></strong> en <?= (int) $a['cuotas'] ?> cuotas · primera cuota <?= esc(formato_importe($a['primera_cuota'])) ?> · CFTEA <?= esc(formato_porcentaje($a['cftea']->toFixed(12))) ?></label>
                    </div>
                <?php endforeach ?>
            </fieldset>
            <p class="small text-body-secondary mt-3">Al aceptar se genera una solicitud de crédito con la alternativa elegida. Para continuar deberá presentar en la sucursal la documentación que exige la línea; la solicitud se evalúa como cualquier otra.</p>
            <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Aceptar la oferta</button></div>
        </form>
    <?php endif ?>
    <form method="post" action="<?= site_url('portal/ofertas/' . (int) $o['id'] . '/rechazar') ?>" style="max-width: 48rem" class="text-end" data-confirmar="¿Rechaza la oferta? No se generará ninguna solicitud.">
        <?= csrf_field() ?>
        <input type="hidden" name="version_fila" value="<?= (int) $o['version_fila'] ?>">
        <button type="submit" class="btn btn-outline-danger">Rechazar la oferta</button>
    </form>
<?php endif ?>
<?php $this->endSection() ?>
