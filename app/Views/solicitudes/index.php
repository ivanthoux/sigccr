<?php
/**
 * CU-29 Localizar Solicitud de Crédito (por número, cliente o estado).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $criterios
 * @var list<array<string, mixed>> $solicitudes
 */
use App\Libraries\Solicitudes\EstadosSolicitud;

$c = static fn (string $k): string => $criterios[$k] ?? '';
$buscado = array_filter($criterios, static fn ($v) => $v !== '') !== [];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Solicitudes de crédito</h1>
        <p>Trámites registrados y su estado.</p>
    </div>
    <?php if (tiene_permiso('solicitudes.registrar')): ?>
        <a href="<?= url_to('solicitudes.registrar') ?>" class="btn btn-primary"><i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>Registrar solicitud</a>
    <?php endif ?>
</div>
<form method="get" action="<?= url_to('solicitudes.localizar') ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar solicitudes">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-lg-2">
            <label for="numero" class="form-label small">Número</label>
            <input type="text" class="form-control" id="numero" name="numero" inputmode="numeric" value="<?= esc($c('numero'), 'attr') ?>">
        </div>
        <div class="col-sm-8 col-lg-4">
            <label for="texto" class="form-label small">Cliente</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($c('texto'), 'attr') ?>">
        </div>
        <div class="col-md-6 col-lg-3">
            <label for="estado" class="form-label small">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (EstadosSolicitud::ETIQUETAS as $k => $v): ?><option value="<?= $k ?>"<?= $c('estado') === $k ? ' selected' : '' ?>><?= esc($v) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6 col-lg-3 d-flex gap-2 justify-content-lg-end">
            <a href="<?= url_to('solicitudes.localizar') ?>" class="btn btn-outline-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
        </div>
    </div>
</form>
<?php if ($solicitudes === []): ?>
    <div class="hoja p-4"><p class="mb-0"><?= $buscado ? 'No hay solicitudes que coincidan con la búsqueda.' : 'Todavía no hay solicitudes registradas.' ?></p></div>
<?php else: ?>
    <div class="hoja p-3">
        <?php if (!$buscado): ?><p class="small text-body-secondary mb-2">Solicitudes registradas más recientemente.</p><?php endif ?>
        <table class="table table-hover align-middle mb-0" data-tabla>
            <thead><tr><th>N.º</th><th>Cliente</th><th>Línea</th><th class="text-end">Importe</th><th>Cuotas</th><th>Registrada</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><a class="fw-medium" href="<?= site_url('admin/solicitudes/' . (int) $s['id']) ?>"><?= (int) $s['numero'] ?></a></td>
                    <td><?= esc($s['denominacion']) ?></td>
                    <td><?= esc($s['linea']) ?></td>
                    <td class="importe"><?= esc(formato_importe($s['importe'])) ?></td>
                    <td><?= (int) $s['cantidad_cuotas'] ?></td>
                    <td data-order="<?= esc((string) $s['fecha_registro'], 'attr') ?>"><?= esc(formato_fecha_hora($s['fecha_registro'])) ?></td>
                    <td><?= etiqueta_estado_solicitud((string) $s['estado'], $s['tipo_rechazo']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
