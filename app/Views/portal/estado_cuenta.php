<?php
/**
 * CU-47 Consultar Estado de Cuenta, paso 2 (A1, A2): créditos, deuda por concepto con los punitorios a hoy, cuotas
 * vencidas, próximos vencimientos, saldo a favor y lo comprometido en operaciones pendientes. La deuda y el saldo a
 * favor se muestran por separado y la consulta no aplica nada (RN-57).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $creditos
 * @var array{conceptos: array<string, string>, total: string, pendientes: int, vencidas: int} $deuda
 * @var list<array<string, mixed>> $vencidas
 * @var list<array<string, mixed>> $proximos
 * @var string $saldo_disponible
 * @var string $saldo_reservado
 * @var list<array<string, mixed>> $ordenes
 */
use App\Libraries\Portal\ReglasPortal;

$estados = ['Activo' => 'Al día', 'EnMora' => 'En mora', 'Cancelado' => 'Cancelado', 'Refinanciado' => 'Refinanciado', 'Revocado' => 'Revocado'];
$conDeuda = (float) $deuda['total'] > 0;
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.inicio') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio</a></nav>
<div class="encabezado">
    <div>
        <h1>Estado de cuenta</h1>
        <p>Al <?= esc(formato_fecha(hoy_negocio())) ?>, con los intereses punitorios calculados hasta hoy.</p>
    </div>
    <?php if ($conDeuda): ?><a href="<?= url_to('portal.pagar') ?>" class="btn btn-primary"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Pagar cuotas</a><?php endif ?>
</div>

<section class="hoja p-4 mb-3" aria-labelledby="t-deuda">
    <h2 class="h5" id="t-deuda">Deuda</h2>
    <?php if (!$conDeuda): ?>
        <p class="mb-0">No tiene deuda pendiente.</p>
    <?php else: ?>
        <dl class="resumen-plan">
            <div><dt>Saldo deudor total</dt><dd class="importe"><?= esc(formato_importe($deuda['total'])) ?></dd></div>
            <?php foreach (ReglasPortal::CONCEPTOS as $k => $etiqueta): if ($k === 'NoCapitalizable' && (float) $deuda['conceptos'][$k] === 0.0) { continue; } ?>
                <div><dt><?= esc($etiqueta) ?></dt><dd class="importe fw-normal"><?= esc(formato_importe($deuda['conceptos'][$k])) ?></dd></div>
            <?php endforeach ?>
        </dl>
        <?php $pend = (int) $deuda['pendientes']; $venc = (int) $deuda['vencidas']; ?>
        <p class="small mb-0"><?= $pend ?> <?= $pend === 1 ? 'cuota pendiente' : 'cuotas pendientes' ?>; <strong><?= $venc === 0 ? 'ninguna vencida' : ($venc === 1 ? '1 vencida' : $venc . ' vencidas') ?></strong>.
            <?php if ($morosidad_avanzada): ?><span class="text-danger">Tiene cuotas con un atraso prolongado: comuníquese con la entidad para regularizar o refinanciar su deuda.</span><?php endif ?></p>
    <?php endif ?>
</section>

<?php if ($vencidas !== []): ?>
    <section class="hoja p-4 mb-3" aria-labelledby="t-vencidas">
        <h2 class="h5" id="t-vencidas">Cuotas vencidas</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Atraso</th><th class="text-end">Punitorios</th><th class="text-end">Total actualizado</th></tr></thead>
                <tbody>
                <?php foreach ($vencidas as $q): ?>
                    <tr class="table-warning">
                        <td><?= (int) $q['credito_numero'] ?></td>
                        <td><a href="<?= site_url('portal/estado-cuenta/creditos/' . (int) $q['credito_id'] . '/cuotas/' . (int) $q['numero']) ?>"><?= (int) $q['numero'] ?></a></td>
                        <td><?= esc(formato_fecha((string) $q['vencimiento'])) ?></td>
                        <td class="text-end"><?= (int) $q['dias_atraso'] ?> días</td>
                        <td class="importe"><?= esc(formato_importe($q['conceptos']['Punitorio'], false)) ?></td>
                        <td class="importe fw-medium"><?= esc(formato_importe($q['total'], false)) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>

<div class="row g-3 mb-3">
    <section class="col-md-6" aria-labelledby="t-proximos">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-proximos">Próximos vencimientos</h2>
            <?php if ($proximos === []): ?>
                <p class="small mb-0">No tiene cuotas por vencer.</p>
            <?php else: ?>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($proximos as $q): ?>
                        <li class="d-flex justify-content-between border-bottom py-1"><span><?= esc(formato_fecha((string) $q['vencimiento'])) ?> · crédito <?= (int) $q['credito_numero'] ?>, cuota <?= (int) $q['numero'] ?></span><span class="importe"><?= esc(formato_importe($q['total'])) ?></span></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </section>
    <section class="col-md-6" aria-labelledby="t-saldo">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-saldo">Saldo a favor</h2>
            <?php if ((float) $saldo_disponible === 0.0 && (float) $saldo_reservado === 0.0): ?>
                <p class="small mb-0">No tiene saldo a favor.</p>
            <?php else: ?>
                <p class="mb-1">Disponible: <strong class="importe"><?= esc(formato_importe($saldo_disponible)) ?></strong></p>
                <?php if ((float) $saldo_reservado > 0): ?><p class="small mb-1">Comprometido en una devolución en curso: <?= esc(formato_importe($saldo_reservado)) ?></p><?php endif ?>
                <p class="small text-body-secondary mb-0">El saldo a favor no se descuenta solo de su deuda: para aplicarlo o pedir su devolución, consulte en la sucursal.</p>
            <?php endif ?>
            <?php if ($ordenes !== []): ?>
                <h3 class="h6 mt-3">Pagos electrónicos en curso</h3>
                <ul class="small mb-0">
                    <?php foreach ($ordenes as $o): ?>
                        <li><a href="<?= site_url('portal/pagar/ordenes/' . (int) $o['id']) ?>">Orden N.º <?= (int) $o['numero'] ?></a> por <?= esc(formato_importe((string) $o['importe'])) ?> — <?= $o['estado'] === 'EnConciliacion' ? 'en conciliación con la pasarela' : 'pendiente de pago hasta las ' . esc(formato_fecha_hora((string) $o['vence'])) ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </section>
</div>

<section class="hoja p-0" aria-labelledby="t-creditos">
    <h2 class="h5 px-4 pt-4" id="t-creditos">Mis créditos</h2>
    <?php if ($creditos === []): ?>
        <p class="px-4 pb-4 mb-0">No tiene créditos.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>N.º</th><th>Línea</th><th class="text-end">Capital</th><th class="text-end">Cuotas</th><th>Liquidado</th><th>Estado</th><th class="text-end">Cuotas pendientes</th></tr></thead>
                <tbody>
                <?php foreach ($creditos as $cr): ?>
                    <tr>
                        <td><a href="<?= site_url('portal/estado-cuenta/creditos/' . (int) $cr['id']) ?>"><?= (int) $cr['numero'] ?></a></td>
                        <td><?= esc($cr['linea']) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $cr['capital'], false)) ?></td>
                        <td class="text-end"><?= (int) $cr['cantidad_cuotas'] ?></td>
                        <td><?= esc(formato_fecha((string) $cr['fecha_liquidacion'])) ?></td>
                        <td><?= $cr['estado'] === 'EnMora' ? '<span class="badge text-bg-warning">En mora</span>' : esc($estados[$cr['estado']] ?? $cr['estado']) ?></td>
                        <td class="text-end"><?= (int) $cr['impagas'] ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
