<?php
/**
 * Presentación común de un reporte (D-44): encabezado, aviso, indicadores y secciones.
 *
 * @var array<string, mixed> $r estructura de ReporteService
 */
?>
<section class="hoja p-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1"><?= esc($r['titulo']) ?></h2>
            <div class="small text-body-secondary">Período: <strong><?= esc($r['periodo']['etiqueta']) ?></strong> · Generado el <?= esc(formato_fecha_hora((string) $r['referencia'])) ?> · <?= esc($r['cu']) ?></div>
            <?php if ($r['filtros'] !== []): ?><div class="small text-body-secondary">Criterios: <?= esc(implode(' · ', $r['filtros'])) ?></div><?php endif ?>
        </div>
        <?= $acciones ?? '' ?>
    </div>
    <?php if ($r['aviso'] !== null): ?><div class="alert alert-warning py-2"><?= esc($r['aviso']) ?></div><?php endif ?>
    <div class="row g-2">
        <?php foreach ($r['kpis'] as [$etiqueta, $valor, $tipo]): ?>
            <div class="col-6 col-md-4 col-xl-3">
                <div class="border rounded p-2 h-100">
                    <div class="small text-body-secondary"><?= esc($etiqueta) ?></div>
                    <div class="fs-5 fw-semibold<?= in_array($tipo, ['importe', 'entero', 'decimal', 'porcentaje'], true) ? ' importe text-start' : '' ?>"><?= esc(valor_reporte($valor, $tipo, true)) ?></div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</section>
<?php foreach ($r['secciones'] as $s): ?>
    <section class="hoja p-4 mb-3">
        <h3 class="h6"><?= esc($s['titulo']) ?></h3>
        <?php if ($s['filas'] === []): ?>
            <p class="small text-body-secondary mb-0">Sin datos para el período y los criterios seleccionados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><?php foreach ($s['columnas'] as [$col, $tipo]): ?><th scope="col" class="<?= in_array($tipo, ['importe', 'entero', 'decimal', 'porcentaje'], true) ? 'importe' : '' ?>"><?= esc($col) ?></th><?php endforeach ?></tr></thead>
                    <tbody>
                    <?php foreach ($s['filas'] as $fila): ?>
                        <tr><?php foreach (array_values($fila) as $i => $v): $tipo = $s['columnas'][$i][1] ?? 'texto'; ?><td<?= in_array($tipo, ['importe', 'entero', 'decimal', 'porcentaje'], true) ? ' class="importe"' : '' ?>><?= esc(valor_reporte($v, $tipo)) ?></td><?php endforeach ?></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($s['filas']) >= 1000): ?><p class="small text-body-secondary mt-2 mb-0">Se muestran las primeras 1.000 filas; los totales consideran todas.</p><?php endif ?>
        <?php endif ?>
        <?php if (($s['nota'] ?? null) !== null): ?><p class="small text-body-secondary mt-2 mb-0"><?= esc($s['nota']) ?></p><?php endif ?>
    </section>
<?php endforeach ?>
