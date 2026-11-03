<?php
/**
 * Documentación vigente del legajo con el resultado de la última verificación (CU-26, D-23): cada documento se abre
 * en otra pestaña sin salir de la pantalla de resolución.
 *
 * @var array<string, mixed> $s solicitud de SolicitudService::obtener()
 */
$nombres = array_column($s['documentos'], 'tipo', 'codigo');
$ultima = $s['revisiones'][0] ?? null;
$resultados = $ultima === null ? [] : array_column($ultima['verificaciones'], null, 'documento_legajo_id');
$etiquetas = ['Conforme' => ['Conforme', 'text-bg-success'], 'Observado' => ['Observado', 'text-bg-warning'], 'Rechazado' => ['Rechazado', 'text-bg-danger']];
$clienteId = (int) $s['cliente_id'];
?>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-legajo">
    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-2">
        <h2 class="h6 mb-0" id="titulo-legajo">Documentación del legajo</h2>
        <span class="small text-body-secondary"><?= $ultima === null ? 'Sin verificación registrada' : 'Última verificación: ' . esc(formato_fecha_hora($ultima['fecha'])) . ($ultima['responsable'] ? ' · ' . esc($ultima['responsable']) : '') ?></span>
    </div>
    <?php if ($s['faltantes'] !== []): ?>
        <div class="alert alert-warning py-2 small">Falta en el legajo: <?= esc(implode(', ', array_map(static fn ($c) => $nombres[$c] ?? $c, $s['faltantes']))) ?>.</div>
    <?php endif ?>
    <?php if ($s['documentos'] === []): ?>
        <p class="mb-0">El cliente no tiene documentos en su legajo.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Documento</th><th>Archivo</th><th>Incorporado</th><th>Verificación</th><th class="text-end">Abrir</th></tr></thead>
                <tbody>
                <?php foreach ($s['documentos'] as $d): $v = $resultados[$d['id']] ?? null; $exigido = in_array($d['codigo'], $s['exigidos'], true); ?>
                    <tr>
                        <td><?= esc($d['tipo']) ?><?= $exigido ? ' <span class="badge text-bg-light border">exigido</span>' : '' ?></td>
                        <td class="text-break small"><?= esc($d['nombre_original']) ?></td>
                        <td class="small"><?= esc(formato_fecha($d['fecha_incorporacion'])) ?></td>
                        <td>
                            <?php if ($v === null): ?><span class="small text-body-secondary">No verificado</span>
                            <?php else: [$t, $c] = $etiquetas[$v['resultado']] ?? [$v['resultado'], 'text-bg-light']; ?>
                                <span class="badge <?= $c ?>"><?= esc($t) ?></span><?= $v['observacion'] ? ' <span class="small">' . esc($v['observacion']) . '</span>' : '' ?>
                            <?php endif ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url("admin/clientes/{$clienteId}/documentos/" . (int) $d['id'] . '?ver=1') ?>" target="_blank" rel="noopener" aria-label="Abrir <?= esc($d['tipo'], 'attr') ?> en otra pestaña"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
