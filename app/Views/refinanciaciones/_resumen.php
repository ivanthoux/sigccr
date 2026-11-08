<?php
/**
 * Encabezado y resumen de una propuesta para los formularios de CU-41, CU-74 y CU-96.
 *
 * @var array<string, mixed> $p
 * @var string $accion
 */
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url('admin/refinanciaciones/' . (int) $p['id']) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a la propuesta</a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc($accion) ?> · propuesta N.º <?= (int) $p['numero'] ?><?= (int) $p['version'] > 1 ? ' (versión ' . (int) $p['version'] . ')' : '' ?></h1>
        <p><?= esc($p['denominacion']) ?> · <?= esc(formato_cuit($p['cuit_cuil'])) ?> · <?= view('refinanciaciones/_estado', ['estado' => $p['estado']]) ?> · vence <?= esc(formato_fecha_hora($p['vence'])) ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3">
    <dl class="resumen-plan mb-0">
        <div><dt>Capital refinanciado</dt><dd class="importe"><?= esc(formato_importe((string) $p['capital_refinanciado'])) ?></dd></div>
        <div><dt>No capitalizable al corte</dt><dd class="importe"><?= esc(formato_importe((string) $p['concepto_no_capitalizable'])) ?></dd></div>
        <div><dt>Condiciones</dt><dd><?= (int) $p['cantidad_cuotas'] ?> cuotas · TNA <?= esc(formato_porcentaje((string) $p['tasa'])) ?></dd></div>
        <div><dt>Obligaciones</dt><dd><?= count($p['cuotas']) ?> cuota(s) de los créditos N.º <?= esc(implode(', ', array_values(array_unique(array_map(static fn ($q) => (int) $q['credito'], $p['cuotas']))))) ?></dd></div>
    </dl>
</section>
