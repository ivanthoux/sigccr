<?php
/**
 * CU-50 A4: detalle de una oferta con su estado, comunicaciones, respuesta y solicitud vinculada.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $o
 */
use App\Libraries\Campanas\ReglasCampanas;
use App\Libraries\Solicitudes\EstadosSolicitud;

$estadosCom = ['Pendiente' => 'Pendiente', 'AceptadaProveedor' => 'Aceptada por el proveedor', 'Entregada' => 'Entregada', 'ReintentoProgramado' => 'Reintento programado', 'Fallida' => 'Fallida', 'Cancelada' => 'Cancelada', 'EnRevision' => 'En revisión'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url('admin/campanas/' . (int) $o['campana_id']) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Campaña N.º <?= (int) $o['campana_numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Oferta a <?= esc($o['denominacion']) ?></h1>
        <p><?= esc($o['linea']) ?> · <?= esc(ReglasCampanas::ESTADOS_OFERTA[$o['estado']] ?? $o['estado']) ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" style="max-width: 48rem">
    <dl class="row small mb-0">
        <dt class="col-sm-5">Importe máximo</dt><dd class="col-sm-7"><?= esc(formato_importe((string) $o['importe_maximo'])) ?> en <?= (int) $o['cantidad_cuotas'] ?> cuotas</dd>
        <dt class="col-sm-5">Tasas</dt><dd class="col-sm-7">TNA <?= esc(formato_porcentaje((string) $o['tna'])) ?> · TEA <?= esc(formato_porcentaje((string) $o['tea'])) ?> · CFTEA <?= esc(formato_porcentaje((string) $o['cftea'])) ?></dd>
        <dt class="col-sm-5">Vigencia</dt><dd class="col-sm-7">hasta el <?= esc(formato_fecha_hora((string) $o['vence'])) ?></dd>
        <dt class="col-sm-5">Respuesta</dt><dd class="col-sm-7"><?= $o['fecha_respuesta'] !== null ? esc(ReglasCampanas::ESTADOS_OFERTA[$o['estado']]) . ' el ' . esc(formato_fecha_hora((string) $o['fecha_respuesta'])) : 'Sin respuesta' ?></dd>
        <dt class="col-sm-5">Solicitud vinculada</dt><dd class="col-sm-7"><?= $o['solicitud_numero'] !== null ? 'N.º ' . (int) $o['solicitud_numero'] . ' (' . esc(EstadosSolicitud::etiqueta((string) $o['solicitud_estado'])) . ')' : '—' ?></dd>
    </dl>
</section>
<section class="hoja p-4" style="max-width: 48rem">
    <h2 class="h6">Comunicaciones</h2>
    <?php if ($o['comunicaciones'] === []): ?>
        <p class="small mb-0">Sin comunicaciones.</p>
    <?php else: ?>
        <ul class="small mb-0"><?php foreach ($o['comunicaciones'] as $m): ?><li>Correo <?= esc(mb_strtolower($estadosCom[$m['estado']] ?? $m['estado'])) ?> (<?= (int) $m['intentos'] ?> intento(s)), creado el <?= esc(formato_fecha_hora((string) $m['creado_en'])) ?></li><?php endforeach ?></ul>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
