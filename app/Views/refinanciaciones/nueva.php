<?php
/**
 * CU-40 Registrar Propuesta de Refinanciación, pasos 3 y 4: simulaciones vigentes del cliente sin propuesta.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $simulaciones
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('refinanciaciones.registrar-propuesta') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Registrar propuesta</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?></p>
    </div>
    <?php if (tiene_permiso('refinanciaciones.simular')): ?>
        <a class="btn btn-outline-primary" href="<?= url_to('refinanciaciones.simular') ?>?cliente=<?= (int) $cliente['id'] ?>"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Nueva simulación</a>
    <?php endif ?>
</div>
<div class="hoja p-3">
    <?php if ($simulaciones === []): ?>
        <p class="mb-0">El cliente no tiene simulaciones vigentes sin propuesta: genere una con CU-39 (A1).</p>
    <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Generada</th><th class="text-end">Deuda consolidada</th><th>Condiciones</th><th class="text-end">Cuota</th><th>Vigente hasta</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($simulaciones as $s): ?>
                <tr>
                    <td><?= esc(formato_fecha_hora($s['fecha'])) ?></td>
                    <td class="importe"><?= esc(formato_importe($s['deuda_consolidada'], false)) ?></td>
                    <td><?= (int) $s['cantidad_cuotas'] ?> cuotas · TNA <?= esc(formato_porcentaje((string) $s['tasa'])) ?></td>
                    <td class="importe"><?= esc(formato_importe($s['valor_cuota'], false)) ?></td>
                    <td><?= esc(formato_fecha_hora($s['vence'])) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/refinanciaciones/simulaciones/' . (int) $s['id']) ?>">Revisar y registrar</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
