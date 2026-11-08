<?php
/**
 * CU-38 Consultar Estado de Pago Electrónico, paso 4 (A1 a A3), y CU-34 paso 11: orden con su enlace de pago, cuotas,
 * transacciones informadas, incidencias de conciliación y cobro resultante.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $orden
 */
$estados = ['PendienteConfirmacion' => 'Pendiente de confirmación', 'Confirmada' => 'Confirmada', 'Rechazada' => 'Rechazada', 'EnConciliacion' => 'En conciliación', 'Vencida' => 'Vencida'];
$colores = ['PendienteConfirmacion' => 'text-bg-warning', 'Confirmada' => 'text-bg-success', 'Rechazada' => 'text-bg-danger', 'EnConciliacion' => 'text-bg-info', 'Vencida' => 'text-bg-secondary'];
$incidencias = ['ImporteDistinto' => 'Importe distinto al de la orden', 'FueraDePlazo' => 'Pago posterior al vencimiento', 'ObligacionesCanceladas' => 'Cuotas ya canceladas por otro medio', 'EstadoDesconocido' => 'Estado no definitivo de la pasarela'];
$abierta = in_array($orden['estado'], ['PendienteConfirmacion', 'EnConciliacion'], true);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('pagos-electronicos.consultar-estado') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Pagos electrónicos</a></nav>
<div class="encabezado">
    <div>
        <h1>Orden N.º <?= (int) $orden['numero'] ?> <span class="badge <?= $colores[$orden['estado']] ?? 'text-bg-light' ?> align-middle fs-6"><?= esc($estados[$orden['estado']] ?? $orden['estado']) ?></span></h1>
        <p><?= esc($orden['denominacion']) ?> · <?= esc(formato_cuit($orden['cuit_cuil'])) ?> · <?= esc(formato_importe($orden['importe'])) ?></p>
    </div>
    <form method="post" action="<?= site_url('admin/pagos-electronicos/' . (int) $orden['id'] . '/consultar') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Consultar a la pasarela</button>
    </form>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-pago">
            <h2 class="h5" id="titulo-pago">Medio de pago</h2>
            <dl class="row mb-3">
                <dt class="col-5">Emisión</dt><dd class="col-7"><?= esc(formato_fecha_hora($orden['emision'])) ?></dd>
                <dt class="col-5">Vence</dt><dd class="col-7"><?= esc(formato_fecha_hora($orden['vence'])) ?> (límite exclusivo)</dd>
                <dt class="col-5">Referencia</dt><dd class="col-7"><code><?= esc((string) $orden['referencia_externa']) ?></code></dd>
                <dt class="col-5">Presentada en</dt><dd class="col-7"><?= esc((string) $orden['medio_presentado']) ?></dd>
            </dl>
            <?php if ($abierta && $orden['url_pago'] !== null): ?>
                <label for="url-pago" class="form-label">Enlace de pago para el cliente</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="url-pago" value="<?= esc($orden['url_pago'], 'attr') ?>" readonly>
                    <a class="btn btn-primary" href="<?= esc($orden['url_pago'], 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir</a>
                </div>
                <p class="form-text">La página de la pasarela muestra el código QR y los medios disponibles. El cobro se registra al confirmarse el pago, no al abrir el enlace (RN-30).</p>
            <?php elseif ($orden['estado'] === 'Vencida'): ?>
                <p class="mb-0">La orden venció sin pago: genere una nueva si el cliente quiere pagar por este medio (CU-34 A6).</p>
            <?php endif ?>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-cuotas">
            <h2 class="h5" id="titulo-cuotas">Cuotas incluidas</h2>
            <table class="table table-sm mb-0">
                <thead><tr><th>Crédito</th><th>Cuota</th><th>Vencimiento</th><th class="text-end">Importe en la orden</th><th>Estado actual</th></tr></thead>
                <tbody>
                <?php foreach ($orden['cuotas'] as $q): ?>
                    <tr><td><?= (int) $q['credito'] ?></td><td><?= (int) $q['numero'] ?></td><td><?= esc(formato_fecha($q['vencimiento'])) ?></td><td class="importe"><?= esc(formato_importe($q['importe'], false)) ?></td><td><?= esc($q['estado']) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
<section class="hoja p-4 mt-3" aria-labelledby="titulo-tx">
    <h2 class="h5" id="titulo-tx">Transacciones informadas por la pasarela</h2>
    <?php if ($orden['transacciones'] === []): ?>
        <p class="mb-0">La pasarela todavía no informó pagos para esta orden (A1).</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Identificador</th><th>Estado informado</th><th class="text-end">Importe</th><th>Fecha efectiva</th><th>Recibida</th><th>Cobro</th></tr></thead>
                <tbody>
                <?php foreach ($orden['transacciones'] as $t): ?>
                    <tr>
                        <td><code><?= esc($t['id_externo']) ?></code></td>
                        <td><?= esc($t['estado']) ?></td>
                        <td class="importe"><?= esc($t['moneda']) ?> <?= esc(formato_importe($t['importe'], false)) ?></td>
                        <td><?= esc(formato_fecha_hora($t['fecha_efectiva'])) ?></td>
                        <td><?= esc(formato_fecha_hora($t['fecha_recepcion'])) ?></td>
                        <td>
                            <?php if ($t['cobro_id'] !== null): ?>
                                <?php if (tiene_permiso('cobros.emitir-comprobante')): ?><a href="<?= site_url('admin/cobros/' . (int) $t['cobro_id'] . '/comprobante') ?>">N.º <?= (int) $t['cobro'] ?></a><?php else: ?>N.º <?= (int) $t['cobro'] ?><?php endif ?>
                                <span class="small text-body-secondary">(<?= esc(formato_importe($t['importe_cobro'], false)) ?>)</span>
                            <?php else: ?>—<?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
    <?php foreach ($orden['saldos_a_favor'] as $s): ?>
        <p class="mt-3 mb-0"><i class="bi bi-wallet2 me-1" aria-hidden="true"></i>Saldo a favor del cliente generado por el excedente: <strong><?= esc(formato_importe($s['importe_original'])) ?></strong> (RN-57).</p>
    <?php endforeach ?>
</section>
<?php if ($orden['incidencias'] !== []): ?>
    <section class="hoja p-4 mt-3" aria-labelledby="titulo-inc">
        <h2 class="h5" id="titulo-inc">Incidencias de conciliación</h2>
        <ul class="list-group">
            <?php foreach ($orden['incidencias'] as $i): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div><?= esc($incidencias[$i['tipo']] ?? $i['tipo']) ?> <span class="small text-body-secondary">· <?= esc($i['id_externo']) ?> · <?= esc(formato_fecha_hora($i['fecha'])) ?></span>
                        <?php if ($i['resolucion'] !== null): ?><div class="small"><?= esc($i['resolucion']) ?></div><?php endif ?></div>
                    <span class="badge <?= $i['estado'] === 'Pendiente' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= esc($i['estado']) ?></span>
                </li>
            <?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
