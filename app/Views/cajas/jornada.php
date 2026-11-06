<?php
/**
 * CU-32 Consultar Movimientos de Caja, paso 6 (A2): movimientos, totales por medio y conciliación de una jornada.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $j
 */
use App\Libraries\Caja\ReglasCaja;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('cajas.consultar-movimientos') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Movimientos de caja</a></nav>
<div class="encabezado">
    <div>
        <h1>Jornada de <?= esc($j['codigo']) ?></h1>
        <p><?= esc($j['denominacion']) ?> · <?= esc($j['cajero']) ?> · abierta el <?= esc(formato_fecha_hora($j['apertura'])) ?><?= $j['cierre'] ? ' · cerrada el ' . esc(formato_fecha_hora($j['cierre'])) : '' ?></p>
    </div>
</div>
<?php if ($j['estado'] === 'Abierta'): ?>
    <div class="alert alert-info">La jornada sigue abierta: se muestran los movimientos registrados hasta ahora, sin conciliación (A2).</div>
<?php endif ?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-movimientos">
    <h2 class="h5" id="titulo-movimientos">Cobros de la jornada</h2>
    <?= view('cajas/_resumen', ['j' => $j]) ?>
</section>
<?php if ($j['estado'] === 'Cerrada'): ?>
    <section class="hoja p-4" aria-labelledby="titulo-conciliacion">
        <h2 class="h5" id="titulo-conciliacion">Conciliación del cierre</h2>
        <?php if ($j['conciliacion'] === [] && $j['efectivo_declarado'] !== null): ?>
            <p class="small mb-0">Jornada anterior a D-30, cerrada con arqueo de efectivo: esperado <?= esc(formato_importe($j['efectivo_esperado'])) ?>, declarado <?= esc(formato_importe($j['efectivo_declarado'])) ?>, diferencia <?= esc(formato_importe($j['diferencia'])) ?><?= $j['explicacion'] ? ' (' . esc($j['explicacion']) . ')' : '' ?>.</p>
        <?php elseif ($j['conciliacion'] === []): ?>
            <p class="small mb-0">La jornada no registró cobros ni acreditaciones.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Medio</th><th class="text-end">Registrado</th><th class="text-end">Acreditado</th><th class="text-end">Diferencia</th><th>Explicación</th></tr></thead>
                    <tbody>
                    <?php foreach ($j['conciliacion'] as $c): ?>
                        <tr<?= $c['diferencia'] !== '0.00' && (float) $c['diferencia'] !== 0.0 ? ' class="table-warning"' : '' ?>>
                            <td><?= esc(ReglasCaja::MEDIOS_VENTANILLA[$c['medio']] ?? $c['medio']) ?></td>
                            <td class="importe"><?= esc(formato_importe($c['total_registrado'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($c['total_acreditado'], false)) ?></td>
                            <td class="importe"><?= esc(formato_importe($c['diferencia'], false)) ?></td>
                            <td class="small"><?= esc((string) $c['explicacion']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
