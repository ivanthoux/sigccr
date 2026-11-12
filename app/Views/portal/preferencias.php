<?php
/**
 * CU-98 Gestionar Preferencias de Comunicación, pasos 2 a 7 (A1, A3): estado del consentimiento para comunicaciones
 * comerciales y su cambio, con confirmación (RN-64).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
$com = $c['consentimientos']['ComunicacionesComerciales'] ?? null;
$otorgado = $com !== null && (int) $com['otorgado'] === 1;
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.perfil') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mi perfil</a></nav>
<div class="encabezado">
    <div>
        <h1>Preferencias de comunicación</h1>
        <p>Decida si quiere recibir ofertas y novedades comerciales de la entidad.</p>
    </div>
</div>
<section class="hoja p-4 mb-3" style="max-width: 44rem" aria-labelledby="t-estado">
    <h2 class="h5" id="t-estado">Estado actual</h2>
    <p class="mb-1"><strong><?= $otorgado ? 'Acepta recibir comunicaciones comerciales.' : 'No acepta recibir comunicaciones comerciales.' ?></strong></p>
    <p class="small text-body-secondary"><?= $com === null ? 'Todavía no registró una preferencia.' : 'Último cambio: ' . esc(formato_fecha_hora((string) $com['fecha'])) . ', por ' . ($com['medio'] === 'Portal' ? 'el portal' : 'la sucursal') . '.' ?></p>
    <form method="post" action="<?= url_to('portal.preferencias') ?>" data-confirmar="<?= $otorgado ? '¿Revoca su consentimiento? Dejará de recibir ofertas y novedades comerciales.' : '¿Otorga su consentimiento para recibir ofertas y novedades comerciales?' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="comunicaciones" value="<?= $otorgado ? 'Revocar' : 'Otorgar' ?>">
        <button type="submit" class="btn <?= $otorgado ? 'btn-outline-danger' : 'btn-primary' ?>"><?= $otorgado ? 'Revocar el consentimiento' : 'Otorgar el consentimiento' ?></button>
    </form>
</section>
<section class="hoja p-4" style="max-width: 44rem" aria-labelledby="t-sin-consentimiento">
    <h2 class="h5" id="t-sin-consentimiento">Comunicaciones que recibe siempre</h2>
    <p class="small mb-1">Su preferencia solo alcanza a las ofertas y novedades comerciales. Para operar con sus créditos seguirá recibiendo, sin necesidad de consentimiento:</p>
    <ul class="small mb-0">
        <li>los enlaces para activar, recuperar o verificar su acceso y su correo;</li>
        <li>los avisos de vencimiento y de deuda de sus cuotas;</li>
        <li>las notificaciones sobre sus solicitudes, pagos, refinanciaciones y devoluciones.</li>
    </ul>
</section>
<?php $this->endSection() ?>
