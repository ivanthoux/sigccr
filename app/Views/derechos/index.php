<?php
/**
 * CU-108 Atender Solicitud de Derechos del Titular, paso 4: solicitudes por fecha límite.
 *
 * @var CodeIgniter\View\View $this
 * @var string $estado
 * @var string $tipo
 * @var list<array<string, mixed>> $solicitudes
 */
use App\Libraries\Clientes\DerechosTitular;

$etiquetaSituacion = ['EnPlazo' => ['En plazo', 'text-bg-secondary'], 'PorVencer' => ['Por vencer', 'text-bg-warning'], 'Vencido' => ['Vencida', 'text-bg-danger']];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Derechos del titular de los datos</h1>
        <p>Pedidos de acceso, rectificación, actualización, supresión y revisión de decisiones automatizadas (Ley 25.326). Las que vencen antes aparecen primero.</p>
    </div>
</div>
<form method="get" class="hoja p-3 mb-3" role="search" aria-label="Filtrar solicitudes de derechos">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="estado" class="form-label small">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                <?php foreach (DerechosTitular::ESTADOS as $k => $t): ?><option value="<?= $k ?>"<?= $estado === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="tipo" class="form-label small">Pedido</label>
            <select id="tipo" name="tipo" class="form-select">
                <option value="">Todos</option>
                <?php foreach (DerechosTitular::TIPOS as $k => $t): ?><option value="<?= $k ?>"<?= $tipo === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
            </select>
        </div>
        <?= acciones_filtro(limpiar: url_to('derechos.atender')) ?>
    </div>
</form>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay solicitudes con esos criterios.</p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">N.º</th><th scope="col">Cliente</th><th scope="col">Pedido</th><th scope="col">Canal</th><th scope="col">Recibida</th><th scope="col">Fecha límite</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td><a href="<?= site_url('admin/derechos/' . (int) $s['id']) ?>"><?= (int) $s['id'] ?></a></td>
                        <td><?= esc($s['cliente']) ?></td>
                        <td><?= esc(DerechosTitular::TIPOS[$s['tipo']] ?? $s['tipo']) ?></td>
                        <td><?= esc($s['canal']) ?></td>
                        <td><?= esc(formato_fecha_hora((string) $s['creado_en'])) ?></td>
                        <td><?= esc(formato_fecha((string) $s['vence'])) ?></td>
                        <td>
                            <?= esc(DerechosTitular::ESTADOS[$s['estado']] ?? $s['estado']) ?>
                            <?php if ($s['situacion'] !== null): ?><span class="badge <?= $etiquetaSituacion[$s['situacion']][1] ?>"><?= $etiquetaSituacion[$s['situacion']][0] ?></span><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
