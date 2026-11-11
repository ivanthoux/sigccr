<?php
/**
 * Resumen de la jornada de caja (RN-32): cobros registrados por medio de pago y sus movimientos.
 *
 * @var array<string, mixed> $j jornada con su resumen
 */
use App\Libraries\Caja\ReglasCaja;

$r = $j['resumen'];
$medios = ReglasCaja::MEDIOS_VENTANILLA + ['Efectivo' => 'Efectivo', 'PagoElectronico' => 'Pago electrónico'];
$tipos = ['FondoInicial' => 'Fondo inicial', 'Cobro' => 'Cobro', 'Desembolso' => 'Desembolso'];
?>
<dl class="resumen-plan">
    <?php foreach (ReglasCaja::MEDIOS_VENTANILLA as $m => $nombre): ?>
        <div><dt><?= esc($nombre) ?></dt><dd class="importe"><?= esc(formato_importe($r['por_medio'][$m])) ?></dd></div>
    <?php endforeach ?>
    <div><dt>Total cobrado (<?= (int) $r['cantidad'] ?> <?= $r['cantidad'] === 1 ? 'medio' : 'medios' ?>)</dt><dd class="importe fw-semibold"><?= esc(formato_importe($r['total'])) ?></dd></div>
</dl>
<?php if ($r['movimientos'] === []): ?>
    <p class="small mb-0">La jornada no registra cobros.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Hora</th><th>Movimiento</th><th>Medio</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
            <tbody>
            <?php foreach ($r['movimientos'] as $m): ?>
                <tr>
                    <td><?= esc(formato_fecha_hora($m['fecha_hora'])) ?></td>
                    <td><?= esc($tipos[$m['tipo']] ?? $m['tipo']) ?></td>
                    <td><?= esc($medios[$m['medio']] ?? $m['medio']) ?></td>
                    <td class="small"><?= esc((string) $m['concepto']) ?></td>
                    <td class="importe"><?= esc(formato_importe($m['importe'], false)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
