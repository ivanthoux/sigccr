<?php
/**
 * Saldos a favor del cliente con su origen, lo disponible y lo reservado (RN-57, RN-73).
 *
 * @var list<array<string, mixed>> $saldos
 */
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Fecha</th><th>Origen</th><th class="text-end">Original</th><th class="text-end">Disponible</th><th class="text-end">Reservado</th><th>Devolución en curso</th></tr></thead>
        <tbody>
        <?php foreach ($saldos as $s): ?>
            <tr>
                <td><?= esc(formato_fecha_hora($s['fecha'])) ?></td>
                <td><?= esc($s['origen']) ?></td>
                <td class="importe"><?= esc(formato_importe((string) $s['importe_original'], false)) ?></td>
                <td class="importe fw-medium"><?= esc(formato_importe((string) $s['importe_disponible'], false)) ?></td>
                <td class="importe"><?= esc(formato_importe((string) $s['importe_reservado'], false)) ?></td>
                <td><?php if ($s['autorizacion_activa'] !== null): ?>N.º <?= (int) $s['autorizacion_activa'] ?><?php else: ?>—<?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
