<?php
/**
 * CU-15 pasos 4 a 7: historial crediticio consolidado.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 * @var array<string, list<array<string, mixed>>> $h
 */
$id = (int) $c['id'];
$vacio = array_sum(array_map('count', $h)) === 0;
$etiquetas = ['Automatica' => 'Automática', 'EnMora' => 'En mora', 'EnEvaluacion' => 'En evaluación', 'Liquidacion' => 'Liquidación'];
$estado = static fn (string $e): string => esc($etiquetas[$e] ?? ucfirst(mb_strtolower(trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', $e)))));
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/clientes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($c['denominacion']) ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Historial crediticio</h1>
        <p><?= esc($c['denominacion']) ?></p>
    </div>
</div>

<?php if ($vacio): ?>
    <div class="hoja p-4"><p class="mb-0">El cliente no tiene antecedentes crediticios en la entidad: no registra solicitudes, créditos, refinanciaciones ni evaluaciones.</p></div>
<?php else: ?>
    <?php
    $secciones = [
        'solicitudes' => ['Solicitudes de crédito', ['Número', 'Fecha', 'Importe', 'Cuotas', 'Estado'], static fn ($r) => [esc($r['numero']), esc(formato_fecha_hora($r['fecha_registro'])), '<span class="importe d-block">' . esc(formato_importe($r['importe'])) . '</span>', (int) $r['cantidad_cuotas'], $estado($r['estado'])]],
        'creditos' => ['Créditos otorgados', ['Número', 'Liquidación', 'Capital', 'Cuotas pagadas', 'Cuotas vencidas', 'Estado'], static fn ($r) => [esc($r['numero']) . ($r['origen'] === 'Refinanciacion' ? ' <span class="badge text-bg-light border">Refinanciación</span>' : ''), esc(formato_fecha($r['fecha_liquidacion'])), '<span class="importe d-block">' . esc(formato_importe($r['capital'])) . '</span>', (int) $r['cuotas_pagadas'] . ' de ' . (int) $r['cantidad_cuotas'], (int) $r['cuotas_vencidas'], $estado($r['estado'])]],
        'refinanciaciones' => ['Refinanciaciones aplicadas', ['Propuesta', 'Fecha', 'Capital refinanciado', 'Cuotas', 'TNA'], static fn ($r) => [esc($r['numero']), esc(formato_fecha_hora($r['fecha_registro'])), '<span class="importe d-block">' . esc(formato_importe($r['capital_refinanciado'])) . '</span>', (int) $r['cantidad_cuotas'], esc(str_replace('.', ',', rtrim(rtrim((string) $r['tasa'], '0'), '.'))) . ' %']],
        'evaluaciones' => ['Evaluaciones crediticias', ['Solicitud', 'Modalidad', 'Resultado', 'Puntaje', 'Categoría', 'Finalizada', 'Vigencia'], static fn ($r) => [esc($r['solicitud']), $estado($r['modalidad']), $estado((string) $r['resultado']), esc((string) $r['puntaje']), esc((string) $r['categoria_riesgo']), esc(formato_fecha($r['fecha_finalizacion'])), $estado((string) $r['vigencia'])]],
        'mora' => ['Cuotas vencidas impagas', ['Crédito', 'Cuota', 'Vencimiento', 'Estado'], static fn ($r) => [esc($r['credito']), (int) $r['cuota'], esc(formato_fecha($r['vencimiento'])), $estado($r['estado'])]],
    ];
    foreach ($secciones as $clave => [$titulo, $columnas, $celdas]): ?>
        <section class="hoja p-4 mb-3" aria-labelledby="titulo-<?= $clave ?>">
            <h2 class="h5" id="titulo-<?= $clave ?>"><?= $titulo ?> <span class="badge rounded-pill text-bg-light border"><?= count($h[$clave]) ?></span></h2>
            <?php if ($h[$clave] === []): ?>
                <p class="text-body-secondary mb-0">Sin registros.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><?php foreach ($columnas as $col): ?><th><?= $col ?></th><?php endforeach ?></tr></thead>
                        <tbody><?php foreach ($h[$clave] as $r): ?><tr><?php foreach ($celdas($r) as $celda): ?><td><?= $celda ?></td><?php endforeach ?></tr><?php endforeach ?></tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    <?php endforeach ?>
<?php endif ?>
<?php $this->endSection() ?>
