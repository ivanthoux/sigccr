<?php
/**
 * Cláusulas del contrato de préstamo compuestas con ModeloContrato::componer() (CU-103, CU-106, D-56). Los párrafos
 * de texto llegan escapados; los bloques {cuadro_cft} y {plan_cuotas} se presentan aquí.
 *
 * @var list<array{estilo: string, numero: ?int, titulo: string, parrafos: list<array{tipo: string, html?: string}>}> $clausulas
 * @var array<string, mixed> $calculo resultado de Simulador::calcular()
 * @var App\Libraries\Finanzas\PlanCuotas|object $plan
 */
$rotulo = static fn (array $k): string => $k['numero'] !== null ? $k['numero'] . '. ' . $k['titulo'] . '.' : ($k['titulo'] !== '' ? $k['titulo'] . '.' : '');
foreach ($clausulas as $k):
    $r = $rotulo($k);
    $recuadro = $k['estilo'] === 'recuadro';
    if ($recuadro): ?><div class="revocacion border border-2 border-dark rounded p-3 my-4"><?php endif;
    foreach ($k['parrafos'] as $i => $p):
        $ultimo = $i === array_key_last($k['parrafos']);
        // El rótulo encabeza el primer párrafo de texto; si la cláusula empieza con un bloque, va en un párrafo propio
        if ($i === 0 && $r !== '' && $p['tipo'] !== 'texto'): ?><p><strong><?= esc($r) ?></strong></p><?php endif;
        if ($p['tipo'] === 'cuadro_cft'): ?>
            <div class="cft-destacado text-center my-3 p-3 border border-2 rounded">
                <div class="small text-uppercase">Costo financiero total efectivo anual (CFTEA) con IVA</div>
                <div class="cft-valor fw-bold"><?= esc(formato_porcentaje($calculo['cftea'])) ?></div>
            </div>
        <?php elseif ($p['tipo'] === 'plan_cuotas'): ?>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Cuota</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Gasto administrativo</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($plan->cuotas as $q): ?>
                        <tr><td><?= $q->numero ?></td><td class="importe"><?= esc(formato_importe($q->capital, false)) ?></td><td class="importe"><?= esc(formato_importe($q->interes, false)) ?></td><td class="importe"><?= esc(formato_importe($q->gastos, false)) ?></td><td class="importe"><?= esc(formato_importe($q->iva, false)) ?></td><td class="importe fw-medium"><?= esc(formato_importe($q->total(), false)) ?></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p<?= $recuadro && $ultimo ? ' class="mb-0"' : '' ?>><?php if ($i === 0 && $r !== ''): ?><strong><?= esc($r) ?></strong> <?php endif ?><?= $p['html'] ?></p>
        <?php endif;
    endforeach;
    if ($recuadro): ?></div><?php endif;
endforeach;
