<?php
/**
 * CU-64 Consultar Alternativas Automáticas de Financiación, pasos 4 a 7 (A1 a A4).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s
 * @var array<string, mixed>|null $estructuracion
 * @var array<string, mixed>|null $evaluacion evaluación vigente
 */
use App\Libraries\Solicitudes\EstadosSolicitud;

$id = (int) $s['id'];
$pendiente = $s['estado'] === 'PendienteSeleccionPlan';
$alternativas = $estructuracion['alternativas'] ?? [];
$vigentes = array_values(array_filter($alternativas, static fn ($a) => (int) $a['vigente'] === 1));
$descartadas = array_values(array_filter($alternativas, static fn ($a) => $a['estado'] === 'Descartada'));
$confirmada = array_values(array_filter($alternativas, static fn ($a) => $a['estado'] === 'Confirmada'))[0] ?? null;
$vencidas = array_values(array_filter($alternativas, static fn ($a) => $a['estado'] === 'Vencida' || ($a['estado'] === 'Vigente' && (int) $a['vigente'] === 0)));
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url("admin/solicitudes/{$id}") ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Solicitud N.º <?= (int) $s['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Alternativas de financiación</h1>
        <p>Solicitud N.º <?= (int) $s['numero'] ?> · <?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?> · <?= etiqueta_estado_solicitud((string) $s['estado'], $s['tipo_rechazo']) ?></p>
    </div>
    <?php if ($pendiente && $evaluacion !== null): ?>
        <form method="post" action="<?= site_url("admin/solicitudes/{$id}/alternativas/regenerar") ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="version_fila" value="<?= (int) $s['version_fila'] ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Generar nuevas alternativas</button>
        </form>
    <?php endif ?>
</div>

<?php if (EstadosSolicitud::esFinal((string) $s['estado'])): ?>
    <div class="alert alert-secondary">La solicitud finalizó (<?= esc(mb_strtolower(EstadosSolicitud::etiqueta((string) $s['estado']))) ?>): sus alternativas se conservan como antecedente.</div>
<?php elseif ($pendiente && $evaluacion === null): ?>
    <div class="alert alert-warning">La evaluación crediticia no está vigente: no se pueden presentar ni confirmar alternativas (RN-20). Al confirmar, la solicitud vuelve a evaluación.</div>
<?php endif ?>

<?php if ($estructuracion === null): ?>
    <div class="hoja p-4"><p class="mb-0">La solicitud todavía no tiene alternativas: se generan cuando la evaluación crediticia resulta favorable (CU-87).</p></div>
<?php else: ?>
    <section class="hoja p-4 mb-3" aria-labelledby="titulo-capacidad">
        <h2 class="h5" id="titulo-capacidad">Capacidad de pago</h2>
        <dl class="resumen-plan mb-2">
            <div><dt>Categoría de riesgo</dt><dd><?= $evaluacion ? esc((string) $evaluacion['categoria_riesgo'] ?: '—') : '—' ?></dd></div>
            <div><dt>Ingresos considerados</dt><dd class="importe"><?= esc(formato_importe($estructuracion['ingresos_considerados'])) ?></dd></div>
            <div><dt>Afectación máxima</dt><dd><?= esc(formato_porcentaje((string) ($estructuracion['parametros']['porcentaje_afectacion'] ?? '0'), 0)) ?></dd></div>
            <div><dt>Cuotas vigentes y comprometidas</dt><dd class="importe"><?= esc(formato_importe($estructuracion['obligaciones_vigentes'])) ?></dd></div>
            <div><dt>Capacidad de pago</dt><dd class="importe"><?= esc(formato_importe($estructuracion['capacidad_pago'])) ?></dd></div>
            <div><dt>Monto máximo financiable</dt><dd class="importe fw-semibold"><?= esc(formato_importe($estructuracion['monto_maximo_financiable'])) ?></dd></div>
        </dl>
        <p class="small text-body-secondary mb-0">Generadas el <?= esc(formato_fecha_hora($estructuracion['fecha'])) ?> con la versión <?= (int) $estructuracion['version_parametros'] ?> de los parámetros de estructuración. La capacidad es el porcentaje máximo de afectación de los ingresos menos las cuotas vigentes y comprometidas; ninguna alternativa supera su primera cuota con IVA (RN-68).</p>
    </section>

    <?php if ($confirmada !== null): ?>
        <div class="alert alert-success">Alternativa confirmada por el cliente: <?= esc(formato_importe($confirmada['importe'])) ?> en <?= (int) $confirmada['cantidad_cuotas'] ?> cuotas (<?= esc((string) $confirmada['medio_conformidad']) ?>). <a href="<?= site_url("admin/solicitudes/{$id}/alternativas/" . (int) $confirmada['id']) ?>">Ver el plan</a></div>
    <?php endif ?>

    <section class="hoja p-3 mb-3" aria-labelledby="titulo-vigentes">
        <h2 class="h5 px-1" id="titulo-vigentes">Alternativas viables</h2>
        <?php if ($vigentes === []): ?>
            <p class="px-1 mb-0"><?= $vencidas !== [] ? 'Las alternativas superaron su vigencia de 7 días corridos (RN-48): genere nuevas alternativas.' : 'No hay alternativas viables vigentes.' ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="text-end">Importe</th><th class="text-end">Cuotas</th><th class="text-end">Primera cuota</th><th class="text-end">TNA</th><th class="text-end">TEA</th><th class="text-end">CFTEA</th><th class="text-end">Total a pagar</th><th>Vence</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($vigentes as $a): $pedida = (string) $a['importe'] === (string) $s['importe'] && (int) $a['cantidad_cuotas'] === (int) $s['cantidad_cuotas']; ?>
                        <tr>
                            <td class="importe fw-medium"><?= esc(formato_importe($a['importe'])) ?><?= $pedida ? ' <span class="badge rounded-pill text-bg-light border">Pedida</span>' : '' ?></td>
                            <td class="text-end"><?= (int) $a['cantidad_cuotas'] ?></td>
                            <td class="importe"><?= esc(formato_importe($a['valor_cuota'])) ?></td>
                            <td class="text-end"><?= esc(formato_porcentaje((string) $s['tna'])) ?></td>
                            <td class="text-end"><?= esc(formato_porcentaje((string) $a['tea'])) ?></td>
                            <td class="text-end fw-medium"><?= esc(formato_porcentaje((string) $a['cftea'])) ?></td>
                            <td class="importe"><?= esc(formato_importe($a['total_a_pagar'])) ?></td>
                            <td class="small"><?= esc(formato_fecha($a['vence'])) ?></td>
                            <td class="text-end"><a class="btn btn-sm <?= $pendiente && $evaluacion ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url("admin/solicitudes/{$id}/alternativas/" . (int) $a['id']) ?>"><?= $pendiente && $evaluacion && tiene_permiso('alternativas.confirmar') ? 'Ver y confirmar' : 'Ver plan' ?></a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <?php if ($descartadas !== []): ?>
        <details class="hoja p-3">
            <summary class="fw-medium">Combinaciones descartadas (<?= count($descartadas) ?>)</summary>
            <table class="table table-sm align-middle mt-2 mb-0">
                <thead><tr><th class="text-end">Importe</th><th class="text-end">Cuotas</th><th class="text-end">Primera cuota</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php foreach ($descartadas as $a): ?>
                    <tr><td class="importe"><?= esc(formato_importe($a['importe'])) ?></td><td class="text-end"><?= (int) $a['cantidad_cuotas'] ?></td><td class="importe"><?= esc(formato_importe($a['valor_cuota'])) ?></td><td class="small"><?= esc((string) $a['motivo_descarte']) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </details>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
