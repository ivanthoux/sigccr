<?php
/**
 * CU-48 Obtener Comprobante de Pago, pasos 2 y 3 (A1, A6).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $cobros
 */
use App\Libraries\Caja\ReglasCaja;

$medios = ReglasCaja::MEDIOS_VENTANILLA + ['Efectivo' => 'Efectivo', 'PagoElectronico' => 'Pago electrónico', 'SaldoAFavor' => 'Saldo a favor'];
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.inicio') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio</a></nav>
<div class="encabezado">
    <div>
        <h1>Comprobantes</h1>
        <p>Comprobantes de sus pagos, para ver, descargar o imprimir.</p>
    </div>
</div>
<?php if ($cobros === []): ?>
    <div class="hoja p-4"><p class="mb-0">Todavía no tiene pagos registrados.</p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Comprobante</th><th>Fecha y hora</th><th class="text-end">Importe</th><th>Medio</th><th>Crédito / cuotas</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($cobros as $b): ?>
                    <tr>
                        <td><a href="<?= site_url('portal/comprobantes/' . (int) $b['id']) ?>">N.º <?= (int) $b['comprobante'] ?></a></td>
                        <td><?= esc(formato_fecha_hora((string) $b['fecha_hora'])) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $b['importe'], false)) ?></td>
                        <td class="small"><?= esc(implode(', ', array_map(static fn ($m) => $medios[trim($m)] ?? trim($m), explode(',', (string) $b['medios'])))) ?></td>
                        <td class="small"><?= esc((string) $b['cuotas']) ?></td>
                        <td><?= $b['estado'] === 'Anulado' ? '<span class="badge text-bg-danger">Anulado</span>' : 'Confirmado' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-body-secondary mt-2 mb-0">«Crédito / cuotas» indica el número de crédito y de cada cuota alcanzada por el pago.</p>
<?php endif ?>
<?php $this->endSection() ?>
