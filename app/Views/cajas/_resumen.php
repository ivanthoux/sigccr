<?php
/**
 * Resumen de la jornada de caja (RN-32): fondo inicial, cobros por medio, desembolsos y efectivo esperado.
 *
 * @var array<string, mixed> $j jornada con su resumen
 */
$r = $j['resumen'];
$medios = ['Efectivo' => 'Efectivo', 'TarjetaDebito' => 'Tarjeta de débito', 'TarjetaCredito' => 'Tarjeta de crédito', 'Transferencia' => 'Transferencia', 'PagoElectronico' => 'Pago electrónico'];
?>
<dl class="resumen-plan">
    <div><dt>Fondo inicial</dt><dd class="importe"><?= esc(formato_importe($j['fondo_inicial'])) ?></dd></div>
    <div><dt>Cobros en efectivo</dt><dd class="importe"><?= esc(formato_importe($r['cobros_efectivo'])) ?></dd></div>
    <div><dt>Desembolsos en efectivo</dt><dd class="importe"><?= esc(formato_importe($r['desembolsos'])) ?></dd></div>
    <div><dt>Efectivo esperado</dt><dd class="importe fw-semibold"><?= esc(formato_importe($r['esperado'])) ?></dd></div>
</dl>
<?php if ($r['cobros_por_medio'] !== []): ?>
    <p class="small mb-2">Cobros por medio: <?= implode(' · ', array_map(static fn ($m, $i) => esc($medios[$m] ?? $m) . ' ' . esc(formato_importe($i)), array_keys($r['cobros_por_medio']), $r['cobros_por_medio'])) ?>. Solo el efectivo integra el arqueo.</p>
<?php endif ?>
<?php if ($r['movimientos'] === []): ?>
    <p class="small mb-0">La jornada no registra movimientos.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Hora</th><th>Movimiento</th><th>Medio</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
            <tbody>
            <?php foreach ($r['movimientos'] as $m): ?>
                <tr>
                    <td><?= esc(formato_fecha_hora($m['fecha_hora'])) ?></td>
                    <td><?= esc(['FondoInicial' => 'Fondo inicial', 'Cobro' => 'Cobro', 'Desembolso' => 'Desembolso'][$m['tipo']] ?? $m['tipo']) ?></td>
                    <td><?= esc($medios[$m['medio']] ?? $m['medio']) ?></td>
                    <td class="small"><?= esc((string) $m['concepto']) ?></td>
                    <td class="importe<?= $m['tipo'] === 'Desembolso' ? ' text-danger' : '' ?>"><?= $m['tipo'] === 'Desembolso' ? '−' : '' ?><?= esc(formato_importe($m['importe'], false)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
