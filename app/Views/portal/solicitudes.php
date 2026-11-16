<?php
/**
 * CU-46 Consultar Estado de Solicitudes, pasos 2 y 3 (A1, A5).
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $solicitudes
 */
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.inicio') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio</a></nav>
<div class="encabezado">
    <div>
        <h1>Mis solicitudes</h1>
        <p>Estado de sus solicitudes de crédito y lo que falta para que avancen.</p>
    </div>
</div>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No tiene solicitudes de crédito registradas. Para pedir un crédito, acérquese a la sucursal: un analista simulará las alternativas con usted.</p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>N.º</th><th>Línea</th><th class="text-end">Importe</th><th class="text-end">Cuotas</th><th>Fecha</th><th>Estado</th><th>Acción pendiente</th></tr></thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td><a href="<?= site_url('portal/solicitudes/' . (int) $s['id']) ?>"><?= (int) $s['numero'] ?></a></td>
                        <td><?= esc($s['linea']) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $s['importe'], false)) ?></td>
                        <td class="text-end"><?= (int) $s['cantidad_cuotas'] ?></td>
                        <td><?= esc(formato_fecha((string) $s['fecha_registro'])) ?></td>
                        <td><?= esc($s['etiqueta']) ?></td>
                        <td class="small"><?= $s['accion'] !== null ? esc($s['accion']) : '<span class="text-body-secondary">—</span>' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
