<?php
/**
 * CU-78 Ejecutar Devolución de Saldo a Favor, pasos 1 y 2: autorizaciones por número, cliente, estado o fecha.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $filtros
 * @var list<array<string, mixed>> $autorizaciones
 */
use App\Services\Caja\DevolucionService;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Devoluciones de saldo a favor</h1>
        <p>Autorizadas por el Gerente; se ejecutan por el medio de pago original o por transferencia (RN-73).</p>
    </div>
</div>
<form method="post" class="hoja p-3 mb-3 row g-2 align-items-end" role="search" aria-label="Buscar devoluciones">
    <?= csrf_field() ?>
    <div class="col-md-2"><label for="numero" class="form-label small">N.º</label><input type="text" class="form-control" id="numero" name="numero" inputmode="numeric" value="<?= esc($filtros['numero'], 'attr') ?>"></div>
    <div class="col-md-4"><label for="texto" class="form-label small">Cliente o CUIT/CUIL</label><input type="text" class="form-control" id="texto" name="texto" value="<?= esc($filtros['texto'], 'attr') ?>"></div>
    <div class="col-6 col-md-3 col-xl-2"><label for="fecha" class="form-label small">Autorizada el</label><input type="date" class="form-control" id="fecha" name="fecha" value="<?= esc($filtros['fecha'], 'attr') ?>"></div>
    <div class="col-md-2"><label for="estado" class="form-label small">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="activas"<?= $filtros['estado'] === 'activas' ? ' selected' : '' ?>>En curso</option>
            <option value="todas"<?= $filtros['estado'] === 'todas' ? ' selected' : '' ?>>Todas</option>
            <?php foreach (DevolucionService::ESTADOS as $k => $n): ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= esc($n) ?></option><?php endforeach ?>
        </select></div>
    <?= acciones_filtro(limpiar: current_url()) ?>
</form>
<div class="hoja p-3">
    <?php if ($autorizaciones === []): ?>
        <p class="mb-0">No hay devoluciones con esos criterios.</p>
    <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>N.º</th><th>Cliente</th><th class="text-end">Importe</th><th>Destino</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($autorizaciones as $a): ?>
                <tr>
                    <td class="fw-medium"><?= (int) $a['id'] ?></td>
                    <td><?= esc($a['denominacion']) ?><div class="small text-body-secondary"><?= esc(formato_cuit($a['cuit_cuil'])) ?></div></td>
                    <td class="importe"><?= esc(formato_importe((string) $a['importe'], false)) ?></td>
                    <td><?= $a['destino'] === 'MedioOriginal' ? 'Medio original' : 'Transferencia · …' . esc(substr((string) $a['cbu_cvu'], -4)) ?></td>
                    <td><?= $a['estado'] === 'PendienteEjecucion' ? ($a['vigente'] ? esc(formato_fecha_hora($a['vence'])) : '<span class="text-danger">Vencida</span>') : '—' ?></td>
                    <td><?= esc(DevolucionService::ESTADOS[$a['estado']] ?? $a['estado']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/devoluciones/' . (int) $a['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
