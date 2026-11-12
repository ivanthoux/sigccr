<?php
/**
 * CU-50 Consultar Campaña Crediticia, paso 6 (A2, A4): condiciones de activación, vigencia, capital, ofertas,
 * comunicaciones, respuestas, solicitudes originadas y resultados. Solo lectura.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
use App\Libraries\Campanas\ReglasCampanas;
use App\Libraries\Solicitudes\EstadosSolicitud;

$r = $c['resultados'];
$p = $c['parametros'];
$estadosCom = ['Pendiente' => 'Pendiente', 'AceptadaProveedor' => 'Aceptada por el proveedor', 'Entregada' => 'Entregada', 'ReintentoProgramado' => 'Reintento programado', 'Fallida' => 'Fallida', 'Cancelada' => 'Cancelada', 'EnRevision' => 'En revisión'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('campanas.consultar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Campañas crediticias</a></nav>
<div class="encabezado">
    <div>
        <h1>Campaña N.º <?= (int) $c['numero'] ?> <?= $c['estado'] === 'Activa' ? '<span class="badge text-bg-success fs-6 align-middle">Activa</span>' : '<span class="badge text-bg-secondary fs-6 align-middle">Finalizada</span>' ?></h1>
        <p><?= esc($c['linea']) ?> · activada el <?= esc(formato_fecha_hora((string) $c['activacion'])) ?></p>
    </div>
</div>
<?php if ($c['estado'] === 'Finalizada'): ?>
    <div class="alert alert-secondary">Finalizada el <?= esc(formato_fecha_hora((string) $c['fecha_cierre'])) ?> por <?= esc(mb_strtolower(ReglasCampanas::CAUSAS[$c['causa_cierre']] ?? (string) $c['causa_cierre'])) ?>, con <?= esc(formato_importe((string) $c['capital_comprometido'])) ?> comprometidos. Se conserva como antecedente; las solicitudes originadas siguen su trámite (RN-43).</div>
<?php endif ?>
<div class="row g-3 mb-3">
    <section class="col-lg-6"><div class="hoja p-4 h-100">
        <h2 class="h5">Activación y condiciones</h2>
        <dl class="row small mb-0">
            <dt class="col-6">Disponibilidad proyectada</dt><dd class="col-6"><?= esc(formato_importe((string) $c['disponible_proyectado'])) ?> a <?= (int) $c['periodo_dias'] ?> días (<?= esc(formato_fecha_hora((string) $c['fecha_proyeccion'])) ?>)</dd>
            <dt class="col-6">Umbral de disponibilidad</dt><dd class="col-6"><?= esc(formato_importe((string) $p['umbral_disponibilidad'])) ?></dd>
            <dt class="col-6">Parámetros de campañas</dt><dd class="col-6">versión <?= (int) $c['version_parametros'] ?></dd>
            <dt class="col-6">Línea y versión</dt><dd class="col-6"><?= esc($c['linea']) ?>, versión <?= (int) $c['version_condiciones'] ?></dd>
            <dt class="col-6">Condiciones</dt><dd class="col-6">TNA <?= esc(formato_porcentaje((string) $c['tna'])) ?>; <?= esc(formato_importe((string) $c['monto_minimo'])) ?> a <?= esc(formato_importe((string) $c['monto_maximo'])) ?>; <?= (int) $c['cuotas_minimas'] ?> a <?= (int) $c['cuotas_maximas'] ?> cuotas</dd>
            <dt class="col-6">Vigencia</dt><dd class="col-6">hasta el <?= esc(formato_fecha_hora((string) $c['vence'])) ?></dd>
        </dl>
    </div></section>
    <section class="col-lg-6"><div class="hoja p-4 h-100">
        <h2 class="h5">Resultados</h2>
        <dl class="resumen-plan mb-2">
            <div><dt>Capital asignado</dt><dd class="importe"><?= esc(formato_importe((string) $c['capital_asignado'])) ?></dd></div>
            <div><dt>Capital comprometido</dt><dd class="importe"><?= esc(formato_importe((string) $c['capital_comprometido'])) ?></dd></div>
            <div><dt>Capital colocado</dt><dd class="importe"><?= esc(formato_importe($r['capital_colocado'])) ?></dd></div>
        </dl>
        <p class="small mb-1"><?= (int) $r['emitidas'] ?> oferta(s): <?= (int) $r['vigentes'] ?> vigente(s), <?= (int) $r['aceptadas'] ?> aceptada(s), <?= (int) $r['rechazadas'] ?> rechazada(s) y <?= (int) $r['vencidas'] ?> vencida(s). Aceptación: <?= esc(str_replace('.', ',', $r['tasa_aceptacion'])) ?> %.</p>
        <p class="small mb-1"><?= (int) $r['solicitudes'] ?> solicitud(es) originada(s) por <?= esc(formato_importe($r['capital_solicitado'])) ?>.</p>
        <p class="small mb-0">Comunicaciones: <?= $c['comunicaciones'] === [] ? 'ninguna' : esc(implode(', ', array_map(static fn ($x) => (int) $x['cantidad'] . ' ' . mb_strtolower($estadosCom[$x['estado']] ?? $x['estado']), $c['comunicaciones']))) ?>.</p>
    </div></section>
</div>
<section class="hoja p-0">
    <h2 class="h5 px-4 pt-4">Clientes elegibles y ofertas</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cliente</th><th>CUIT/CUIL</th><th class="text-end">Importe máximo</th><th class="text-end">Cuotas</th><th>Estado</th><th>Respuesta</th><th>Solicitud</th></tr></thead>
            <tbody>
            <?php foreach ($c['ofertas'] as $o): ?>
                <tr>
                    <td><a href="<?= site_url('admin/campanas/ofertas/' . (int) $o['id']) ?>"><?= esc($o['denominacion']) ?></a></td>
                    <td><?= $o['cuit_cuil'] !== null ? esc(formato_cuit($o['cuit_cuil'])) : '—' ?></td>
                    <td class="importe"><?= esc(formato_importe((string) $o['importe_maximo'], false)) ?></td>
                    <td class="text-end"><?= (int) $o['cantidad_cuotas'] ?></td>
                    <td><?= esc(ReglasCampanas::ESTADOS_OFERTA[$o['estado']] ?? $o['estado']) ?></td>
                    <td><?= $o['fecha_respuesta'] !== null ? esc(formato_fecha_hora((string) $o['fecha_respuesta'])) : '—' ?></td>
                    <td><?php if ($o['solicitud_numero'] !== null && tiene_permiso('solicitudes.consultar')): ?><a href="<?= site_url('admin/solicitudes/' . (int) $o['solicitud_id']) ?>">N.º <?= (int) $o['solicitud_numero'] ?></a> <span class="small text-body-secondary"><?= esc(EstadosSolicitud::etiqueta((string) $o['solicitud_estado'])) ?></span><?php elseif ($o['solicitud_numero'] !== null): ?>N.º <?= (int) $o['solicitud_numero'] ?><?php else: ?>—<?php endif ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<?php $this->endSection() ?>
