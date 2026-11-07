<?php
/**
 * CU-102 paso 2: créditos del cliente con su plazo de revocación.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $creditos
 */
$estados = ['Activo' => 'Activo', 'EnMora' => 'En mora', 'Cancelado' => 'Cancelado', 'Refinanciado' => 'Refinanciado', 'Revocado' => 'Revocado'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('creditos.revocar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Revocación del préstamo</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?></p>
    </div>
</div>
<?php if ($creditos === []): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene créditos liquidados.</p></div>
<?php else: ?>
    <div class="hoja p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Crédito</th><th>Línea</th><th>Desembolso</th><th class="text-end">Capital</th><th>Estado</th><th>Plazo de revocación</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($creditos as $c): ?>
                    <tr>
                        <td class="fw-medium"><?= (int) $c['numero'] ?></td>
                        <td><?= esc($c['linea']) ?></td>
                        <td><?= esc(formato_fecha_hora($c['fecha_liquidacion'])) ?></td>
                        <td class="importe"><?= esc(formato_importe($c['capital'], false)) ?></td>
                        <td><?= esc($estados[$c['estado']] ?? $c['estado']) ?></td>
                        <td>
                            <?php if ($c['revocacion'] !== null): ?>
                                <span class="badge <?= $c['revocacion'] === 'Confirmada' ? 'text-bg-secondary' : 'text-bg-warning' ?>"><?= $c['revocacion'] === 'Confirmada' ? 'Revocado' : 'Pendiente de restitución' ?></span>
                            <?php elseif ($c['fecha_limite'] === null): ?>
                                <span class="small text-body-secondary">Sin constancia de recepción del contrato</span>
                            <?php else: ?>
                                <?= $c['en_plazo'] ? 'Hasta el ' . esc(formato_fecha($c['fecha_limite'])) : '<span class="text-body-secondary">Venció el ' . esc(formato_fecha($c['fecha_limite'])) . '</span>' ?>
                            <?php endif ?>
                        </td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/revocaciones/' . (int) $c['id']) ?>">Ver</a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
