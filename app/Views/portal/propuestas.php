<?php
/**
 * Refinanciaciones y ofertas: CU-52 pasos 1 y 2 y CU-43 pasos 1 y 2 (A5).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $ofertas
 * @var list<array<string, mixed>> $propuestas
 */
use App\Libraries\Campanas\ReglasCampanas;

$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.inicio') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio</a></nav>
<div class="encabezado">
    <div>
        <h1>Refinanciaciones y ofertas</h1>
        <p>Ofertas de crédito que recibió y propuestas de refinanciación de sus deudas.</p>
    </div>
</div>
<section class="hoja p-0 mb-3" aria-labelledby="t-ofertas">
    <h2 class="h5 px-4 pt-4" id="t-ofertas">Ofertas de crédito</h2>
    <?php if ($ofertas === []): ?>
        <p class="px-4 pb-4 mb-0">No recibió ofertas de crédito. Las ofertas llegan a los clientes que aceptan recibir comunicaciones comerciales (<a href="<?= url_to('portal.preferencias') ?>">Preferencias de comunicación</a>).</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Oferta</th><th>Línea</th><th class="text-end">Hasta</th><th class="text-end">Cuotas</th><th>TNA</th><th>Vigencia</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($ofertas as $o): $vigente = $o['estado'] === 'Vigente' && (int) $o['en_vigencia'] === 1; ?>
                    <tr>
                        <td><a href="<?= site_url('portal/ofertas/' . (int) $o['id']) ?>">N.º <?= (int) $o['id'] ?></a></td>
                        <td><?= esc($o['linea']) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $o['importe_maximo'], false)) ?></td>
                        <td class="text-end"><?= (int) $o['cantidad_cuotas'] ?></td>
                        <td><?= esc(formato_porcentaje((string) $o['tna'])) ?></td>
                        <td><?= esc(formato_fecha_hora((string) $o['vence'])) ?></td>
                        <td><?= $vigente ? '<span class="badge text-bg-success">Vigente</span>' : esc($o['estado'] === 'Vigente' ? 'Vencida' : (ReglasCampanas::ESTADOS_OFERTA[$o['estado']] ?? $o['estado'])) ?><?= $o['solicitud_numero'] !== null ? ' · solicitud N.º ' . (int) $o['solicitud_numero'] : '' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<section class="hoja p-0" aria-labelledby="t-propuestas">
    <h2 class="h5 px-4 pt-4" id="t-propuestas">Propuestas de refinanciación</h2>
    <?php if ($propuestas === []): ?>
        <p class="px-4 pb-4 mb-0">No tiene propuestas de refinanciación. Si le cuesta pagar sus cuotas, consulte en la sucursal: un analista puede proponerle refinanciar su deuda.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Propuesta</th><th>Registrada</th><th class="text-end">Capital refinanciado</th><th class="text-end">Cuotas</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($propuestas as $p): ?>
                    <tr>
                        <td><a href="<?= site_url('portal/propuestas/refinanciaciones/' . (int) $p['id']) ?>">N.º <?= (int) $p['numero'] ?><?= (int) $p['version'] > 1 ? ' (v' . (int) $p['version'] . ')' : '' ?></a></td>
                        <td><?= esc(formato_fecha((string) $p['fecha_registro'])) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $p['capital_refinanciado'], false)) ?></td>
                        <td class="text-end"><?= (int) $p['cantidad_cuotas'] ?></td>
                        <td><?= view('refinanciaciones/_estado', ['estado' => $p['estado']]) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
