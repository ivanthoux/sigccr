<?php
/**
 * CU-83 Consultar Alertas: alertas dirigidas al usuario o a su rol.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $filtros
 * @var list<array<string, mixed>> $alertas
 */
use App\Libraries\Alertas\OperacionAlerta;
use App\Services\Comunes\AlertaService;

$f = static fn (string $k): string => $filtros[$k] ?? '';
$tipos = ['Operativa' => 'Operativa', 'Vencimiento' => 'Vencimiento', 'Integracion' => 'Integración', 'Proceso' => 'Proceso automático', 'Seguridad' => 'Seguridad'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Alertas</h1>
        <p>Situaciones que requieren su intervención o la de su rol. Se resuelven solas cuando se resuelve la situación que las originó.</p>
    </div>
</div>

<form method="get" action="<?= url_to('alertas.consultar') ?>" class="hoja p-3 mb-3" aria-label="Filtrar alertas">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="estado" class="form-label small">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <?php foreach (['Pendiente' => 'Pendientes', 'Resuelta' => 'Resueltas', 'Todas' => 'Todas'] as $k => $t): ?><option value="<?= $k ?>"<?= $f('estado') === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="tipo" class="form-label small">Tipo</label>
            <select class="form-select" id="tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach ($tipos as $k => $t): ?><option value="<?= $k ?>"<?= $f('tipo') === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label for="desde" class="form-label small">Desde</label>
            <input type="date" class="form-control" id="desde" name="desde" value="<?= esc($f('desde'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label for="hasta" class="form-label small">Hasta</label>
            <input type="date" class="form-control" id="hasta" name="hasta" value="<?= esc($f('hasta'), 'attr') ?>">
        </div>
        <?= acciones_filtro(limpiar: url_to('alertas.consultar')) ?>
    </div>
</form>

<?php if ($alertas === []): ?>
    <div class="hoja p-4"><p class="mb-0"><?= $f('estado') === 'Pendiente' ? 'No tiene alertas pendientes.' : 'No hay alertas que coincidan con los filtros.' ?></p></div>
<?php else: ?>
    <div class="hoja p-3">
        <table class="table table-hover align-middle mb-0" data-tabla data-order="[]">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Relacionada con</th><th>Causa</th><th>Acción pendiente</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($alertas as $a): ?>
                <tr<?= $a['vista'] === null && $a['estado'] === 'Pendiente' ? ' class="fw-semibold"' : '' ?>>
                    <td class="text-nowrap"><a href="<?= site_url('admin/alertas/' . $a['id']) ?>"><?= esc(formato_fecha_hora((string) $a['fecha_generacion'])) ?></a></td>
                    <td><?= esc($tipos[$a['tipo']] ?? $a['tipo']) ?></td>
                    <td><?= $a['objeto_tipo'] !== null ? esc((OperacionAlerta::OBJETOS[$a['objeto_tipo']] ?? $a['objeto_tipo']) . ' ' . $a['objeto_id']) : '' ?></td>
                    <td><?= esc((string) $a['causa']) ?></td>
                    <td><?= esc((string) $a['accion_pendiente']) ?></td>
                    <td>
                        <span class="badge rounded-pill <?= $a['estado'] === 'Pendiente' ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $a['estado'] === 'Pendiente' ? 'Pendiente' : 'Resuelta' ?></span>
                        <?php if ($a['vista'] === null && $a['estado'] === 'Pendiente'): ?><span class="visually-hidden">, sin ver</span><?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
