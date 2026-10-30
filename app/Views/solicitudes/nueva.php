<?php
/**
 * CU-22 pasos 3 y 4: simulaciones vigentes del cliente para formalizar.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $simulaciones
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('solicitudes.registrar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Registrar solicitud</h1>
        <p><?= esc($cliente['denominacion']) ?></p>
    </div>
    <?php if (tiene_permiso('solicitudes.simular')): ?>
        <a href="<?= url_to('solicitudes.simular') ?>?cliente=<?= (int) $cliente['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Nueva simulación</a>
    <?php endif ?>
</div>
<?php if (($cliente['cuit_cuil'] ?? null) === null || $cliente['cuit_cuil'] === ''): ?>
    <div class="alert alert-warning">El cliente no tiene CUIT/CUIL informado: debe completarse con <a href="<?= site_url('admin/clientes/' . (int) $cliente['id'] . '/editar') ?>">Modificar datos</a> antes de registrar una solicitud (A6).</div>
<?php endif ?>
<?php if ($simulaciones === []): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene simulaciones vigentes (duran 7 días corridos). Genere una con «Nueva simulación» para continuar.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Línea</th><th class="text-end">Importe</th><th>Cuotas</th><th class="text-end">Primera cuota</th><th>CFTEA</th><th>Generada</th><th>Vence</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($simulaciones as $s): ?>
                <tr>
                    <td><?= esc($s['linea']) ?><?= $s['simulacion_comparativa_id'] !== null ? ' <span class="badge text-bg-light border">comparativa</span>' : '' ?></td>
                    <td class="importe"><?= esc(formato_importe($s['importe'])) ?></td>
                    <td><?= (int) $s['cantidad_cuotas'] ?></td>
                    <td class="importe"><?= esc(formato_importe($s['valor_cuota'])) ?></td>
                    <td><?= esc(formato_porcentaje($s['cftea'])) ?></td>
                    <td><?= esc(formato_fecha_hora($s['fecha_generacion'])) ?></td>
                    <td><?= esc(formato_fecha_hora($s['vence'])) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= site_url('admin/solicitudes/nueva/' . (int) $s['id']) ?>">Formalizar</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
