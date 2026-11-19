<?php
/**
 * CU-56 Consultar Informe de Caja Diaria: criterios (paso 2), jornadas, cobros por medio, conciliaciones y órdenes
 * electrónicas pendientes (pasos 3 y 4; A1 a A3). El Cajero solo consulta sus jornadas (A2).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $f
 * @var array<string, mixed> $i
 * @var array<string, list<array<string, mixed>>> $opciones
 * @var bool $soloCajero
 */
use App\Services\Reportes\ReporteService;

$medios = ReporteService::medios();
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Informe de caja diaria</h1>
        <p>Jornadas del <strong><?= esc($i['periodo']['etiqueta']) ?></strong>: cobros confirmados por medio de pago, conciliaciones del cierre y órdenes electrónicas pendientes.</p>
    </div>
    <button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button>
</div>
<form method="get" class="hoja p-3 mb-3 row g-2 align-items-end d-print-none" novalidate>
    <div class="col-6 col-md-3 col-xl-2"><label for="desde" class="form-label small">Fecha</label><input type="date" id="desde" name="desde" class="form-control<?= campo_invalido('desde') ?>" value="<?= esc($f['desde'] !== '' ? $f['desde'] : $i['periodo']['desde'], 'attr') ?>"><?= error_campo('desde') ?></div>
    <div class="col-6 col-md-3 col-xl-2"><label for="hasta" class="form-label small">hasta <span class="text-body-secondary">(opcional)</span></label><input type="date" id="hasta" name="hasta" class="form-control<?= campo_invalido('hasta') ?>" value="<?= esc($f['hasta'], 'attr') ?>"><?= error_campo('hasta') ?></div>
    <div class="col-6 col-md-3"><label for="caja" class="form-label small">Caja</label>
        <select id="caja" name="caja" class="form-select"><option value="">Todas</option><?php foreach ($opciones['cajas'] as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['caja'] === (string) $c['id'] ? ' selected' : '' ?>><?= esc($c['nombre']) ?></option><?php endforeach ?></select></div>
    <?php if (!$soloCajero): ?>
        <div class="col-6 col-md-3"><label for="cajero" class="form-label small">Cajero</label>
            <select id="cajero" name="cajero" class="form-select"><option value="">Todos</option><?php foreach ($opciones['cajeros'] as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['cajero'] === (string) $c['id'] ? ' selected' : '' ?>><?= esc($c['nombre']) ?></option><?php endforeach ?></select></div>
    <?php endif ?>
    <?= acciones_filtro(texto: 'Consultar', icono: 'bi-arrow-repeat', antes: '<a href="' . url_to('cajas.informe-diario') . '" class="btn btn-outline-secondary">Hoy</a>') ?>
</form>
<?php if ($i['aviso'] !== null): ?><div class="alert alert-warning"><?= esc($i['aviso']) ?></div><?php endif ?>
<?php if ($i['jornadas'] === []): ?>
    <div class="hoja p-4"><p class="mb-0">No se registraron jornadas de caja para la fecha y los criterios seleccionados (A1).</p></div>
<?php else: ?>
    <?php if ($i['totales']['abiertas'] > 0): ?><div class="alert alert-info">Hay <?= (int) $i['totales']['abiertas'] ?> jornada(s) abierta(s): sus totales son parciales y no tienen conciliación (A3).</div><?php endif ?>
    <div class="row g-2 mb-3">
        <?php foreach ([['Jornadas', count($i['jornadas']), 'entero'], ['Total cobrado', $i['totales']['cobrado'], 'importe'], ['Diferencias de conciliación', $i['totales']['diferencias'], 'importe'], ['Órdenes electrónicas pendientes', $i['totales']['ordenes'], 'importe']] as [$e, $val, $tipo]): ?>
            <div class="col-6 col-md-3"><div class="hoja p-2 h-100"><div class="small text-body-secondary"><?= esc($e) ?></div><div class="fs-5 fw-semibold"><?= esc(valor_reporte($val, $tipo, true)) ?></div></div></div>
        <?php endforeach ?>
    </div>
    <section class="hoja p-4 mb-3">
        <h2 class="h6">Jornadas</h2>
        <p class="small text-body-secondary d-print-none">Seleccione una jornada para ver su detalle de movimientos (CU-32).</p>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Caja</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th>Estado</th><th class="importe">Cobros</th><th class="importe">Cobrado</th></tr></thead>
            <tbody>
            <?php foreach ($i['jornadas'] as $j): ?>
                <tr><td><?php if (tiene_permiso('cajas.consultar-movimientos')): ?><a href="<?= site_url('admin/cajas/jornadas/' . (int) $j['id']) ?>"><?= esc($j['codigo'] . ' · ' . $j['caja']) ?></a><?php else: ?><?= esc($j['codigo'] . ' · ' . $j['caja']) ?><?php endif ?></td><td><?= esc($j['cajero']) ?></td><td><?= esc(formato_fecha_hora((string) $j['apertura'])) ?></td><td><?= esc(formato_fecha_hora((string) $j['cierre'])) ?: '—' ?></td>
                    <td><?= $j['estado'] === 'Abierta' ? '<span class="badge text-bg-success">Abierta</span>' : 'Cerrada' ?></td><td class="importe"><?= (int) $j['cobros'] ?></td><td class="importe"><?= esc(formato_importe((string) $j['cobrado'], false)) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table></div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h6">Cobros confirmados por medio de pago</h2>
        <?php if ($i['medios'] === []): ?><p class="small text-body-secondary mb-0">No hay cobros confirmados en las jornadas.</p><?php else: ?>
        <table class="table table-sm mb-0" style="max-width: 36rem"><thead><tr><th>Medio</th><th class="importe">Pagos</th><th class="importe">Importe</th></tr></thead><tbody>
            <?php foreach ($i['medios'] as $m): ?><tr><td><?= esc($medios[$m['medio']] ?? $m['medio']) ?></td><td class="importe"><?= (int) $m['cantidad'] ?></td><td class="importe"><?= esc(formato_importe((string) $m['importe'], false)) ?></td></tr><?php endforeach ?>
        </tbody></table>
        <?php endif ?>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h6">Conciliaciones del cierre</h2>
        <?php if ($i['conciliaciones'] === []): ?><p class="small text-body-secondary mb-0">Sin conciliaciones: las jornadas abiertas se concilian al cerrarse.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Caja</th><th>Apertura</th><th>Medio</th><th class="importe">Registrado</th><th class="importe">Acreditado</th><th class="importe">Diferencia</th><th>Explicación</th></tr></thead><tbody>
            <?php foreach ($i['conciliaciones'] as $c): ?><tr><td><?= esc($c['codigo']) ?></td><td><?= esc(formato_fecha_hora((string) $c['apertura'])) ?></td><td><?= esc($medios[$c['medio']] ?? $c['medio']) ?></td><td class="importe"><?= esc(formato_importe((string) $c['total_registrado'], false)) ?></td><td class="importe"><?= esc(formato_importe((string) $c['total_acreditado'], false)) ?></td><td class="importe<?= (float) $c['diferencia'] !== 0.0 ? ' text-danger' : '' ?>"><?= esc(formato_importe((string) $c['diferencia'], false)) ?></td><td class="small"><?= esc((string) ($c['explicacion'] ?? '')) ?></td></tr><?php endforeach ?>
        </tbody></table></div>
        <?php endif ?>
    </section>
<?php endif ?>
<section class="hoja p-4 mb-3">
    <h2 class="h6">Órdenes de pago electrónico pendientes emitidas en el período</h2>
    <?php if ($i['ordenes'] === []): ?><p class="small text-body-secondary mb-0">No hay órdenes pendientes de confirmación.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Orden</th><th>Emisión</th><th>Vence</th><th>Cliente</th><th class="importe">Importe</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($i['ordenes'] as $o): ?><tr><td><?= (int) $o['numero'] ?></td><td><?= esc(formato_fecha_hora((string) $o['emision'])) ?></td><td><?= esc(formato_fecha_hora((string) $o['vence'])) ?></td><td><?= esc($o['denominacion']) ?></td><td class="importe"><?= esc(formato_importe((string) $o['importe'], false)) ?></td><td><?= $o['estado'] === 'EnConciliacion' ? 'En conciliación' : 'Pendiente de confirmación' ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <p class="small text-body-secondary mt-2 mb-0">No integran los totales cobrados hasta su confirmación (RN-47).</p>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
