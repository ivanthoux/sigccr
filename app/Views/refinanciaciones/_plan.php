<?php
/**
 * Plan de cuotas de una refinanciación: capital, interés, IVA, concepto no capitalizable y total (RN-50 a RN-52,
 * RN-70). Recibe filas con las claves de CuotaPlan::toArray() o de la tabla cuotas.
 *
 * @var list<array<string, mixed>> $filas
 */
$v = static fn (array $f, string $a, string $b = ''): string => (string) ($f[$a] ?? $f[$b] ?? '0');
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Cuota</th><th>Vencimiento</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">IVA</th><th class="text-end">No capitalizable</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($filas as $f): $total = $f['total'] ?? null; ?>
            <tr>
                <td><?= (int) $f['numero'] ?></td>
                <td><?= esc(formato_fecha((string) $f['vencimiento'])) ?></td>
                <td class="importe"><?= esc(formato_importe($v($f, 'capital'), false)) ?></td>
                <td class="importe"><?= esc(formato_importe($v($f, 'interes'), false)) ?></td>
                <td class="importe"><?= esc(formato_importe($v($f, 'iva'), false)) ?></td>
                <td class="importe"><?= esc(formato_importe($v($f, 'no_capitalizable', 'concepto_no_capitalizable'), false)) ?></td>
                <td class="importe fw-medium"><?= esc(formato_importe($total ?? bcadd(bcadd(bcadd($v($f, 'capital'), $v($f, 'interes'), 2), bcadd($v($f, 'gastos'), $v($f, 'iva'), 2), 2), $v($f, 'concepto_no_capitalizable'), 2), false)) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
