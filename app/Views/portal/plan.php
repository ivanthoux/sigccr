<?php
/**
 * CU-47 Consultar Estado de Cuenta, paso 4 (A6, A7): plan de cuotas de un crédito, con los punitorios calculados a
 * la fecha de consulta, y el importe de la cancelación anticipada a hoy (RN-67), sin registrar ninguna operación.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $credito
 * @var list<array<string, mixed>> $cuotas
 * @var string|null $cancelacion
 * @var string $fecha
 */
$estadosCuota = ['Pendiente' => 'Pendiente', 'Vencida' => 'Vencida', 'Pagada' => 'Pagada', 'Sustituida' => 'Sustituida', 'SinEfecto' => 'Sin efecto'];
$estados = ['Activo' => 'Al día', 'EnMora' => 'En mora', 'Cancelado' => 'Cancelado', 'Refinanciado' => 'Refinanciado', 'Revocado' => 'Revocado'];
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.estado-cuenta') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Estado de cuenta</a></nav>
<div class="encabezado">
    <div>
        <h1>Crédito N.º <?= (int) $credito['numero'] ?></h1>
        <p><?= esc($credito['linea']) ?> · <?= esc(formato_importe((string) $credito['capital'])) ?> en <?= (int) $credito['cantidad_cuotas'] ?> cuotas · <?= esc($estados[$credito['estado']] ?? $credito['estado']) ?></p>
    </div>
</div>
<?php if ($cancelacion !== null): ?>
    <div class="alert alert-light border">
        <strong>Cancelación anticipada:</strong> para cancelar hoy todo el crédito debería pagar <strong class="importe"><?= esc(formato_importe($cancelacion)) ?></strong>, sin comisión y sin los intereses de las cuotas que todavía no vencieron. La cancelación se hace en la sucursal; el importe cambia día a día.
    </div>
<?php endif ?>
<section class="hoja p-0">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Gastos</th><th class="text-end">Punitorios</th><th class="text-end">Total actualizado</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($cuotas as $q): $impaga = $q['total'] !== null; ?>
                <tr<?= $q['dias_atraso'] > 0 ? ' class="table-warning"' : '' ?>>
                    <td><?php if ($impaga): ?><a href="<?= site_url('portal/estado-cuenta/creditos/' . (int) $credito['id'] . '/cuotas/' . (int) $q['numero']) ?>"><?= (int) $q['numero'] ?></a><?php else: ?><?= (int) $q['numero'] ?><?php endif ?></td>
                    <td><?= esc(formato_fecha((string) $q['vencimiento'])) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $q['capital'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $q['interes'], false)) ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $q['gastos'], false)) ?></td>
                    <td class="importe"><?= $impaga ? esc(formato_importe($q['conceptos']['Punitorio'], false)) : '' ?></td>
                    <td class="importe fw-medium"><?= $impaga ? esc(formato_importe($q['total'], false)) : '' ?></td>
                    <td><?= esc($estadosCuota[$q['estado']] ?? $q['estado']) ?><?= $q['dias_atraso'] > 0 ? ' · ' . (int) $q['dias_atraso'] . ' días' : '' ?><?= (int) $q['con_parcial'] === 1 && $impaga ? ' <span class="badge text-bg-light border">Pago parcial</span>' : '' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<p class="small text-body-secondary mt-2 mb-0">El capital, el interés y los gastos son los del plan original; el IVA se incluye en el total. «Total actualizado» es lo que falta pagar de cada cuota al <?= esc(formato_fecha($fecha)) ?>.</p>
<?php $this->endSection() ?>
