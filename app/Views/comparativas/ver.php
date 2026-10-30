<?php
/**
 * CU-99 pasos 9 y 11: cuadro comparativo de las líneas para el cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
$vigente = (int) $c['vigente'] === 1;
$mayor = $c['mayor_monto'][0] ?? null;
$menor = $c['menor_costo'][0] ?? null;
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('simulaciones.comparar') ?>?cliente=<?= (int) $c['cliente_id'] ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Nueva comparación</a></nav>
<div class="encabezado">
    <div>
        <h1>Comparación de líneas</h1>
        <p><?= esc($c['denominacion']) ?> · <?= esc(formato_fecha_hora($c['fecha_generacion'])) ?> · <?= $vigente ? 'vigente hasta el ' . esc(formato_fecha_hora($c['vence'])) : 'vencida' ?></p>
    </div>
</div>
<section class="hoja p-4 mb-3" aria-labelledby="titulo-capacidad">
    <h2 class="h5" id="titulo-capacidad">Capacidad de pago</h2>
    <dl class="resumen-plan mb-0">
        <div><dt>Ingresos considerados</dt><dd class="importe"><?= esc(formato_importe($c['ingresos_considerados'])) ?></dd></div>
        <div><dt>Cuotas vigentes y comprometidas</dt><dd class="importe"><?= esc(formato_importe($c['obligaciones_vigentes'])) ?></dd></div>
        <div><dt>Capacidad de pago</dt><dd class="importe fw-semibold"><?= esc(formato_importe($c['capacidad_pago'])) ?></dd></div>
        <div><dt>Pedido</dt><dd><?= $c['importe_pedido'] ? esc(formato_importe($c['importe_pedido'])) : 'Sin importe' ?><?= $c['cuotas_pedidas'] ? ' en ' . (int) $c['cuotas_pedidas'] . ' cuotas' : '' ?></dd></div>
    </dl>
</section>

<section class="hoja p-3 mb-3" aria-labelledby="titulo-lineas">
    <h2 class="h5 px-1" id="titulo-lineas">Líneas viables<?= $c['criterio'] === 'Costo' ? ', de menor a mayor costo' : ', de mayor a menor monto' ?></h2>
    <?php if ($c['lineas'] === []): ?>
        <p class="px-1 mb-0">Ninguna línea resulta viable para el cliente. Los motivos se detallan abajo.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead><tr><th>Línea</th><th class="text-end">Monto máximo</th><th class="text-end">Recomendado</th><th class="text-end">Primera cuota</th><th class="text-end">Total a pagar</th><th class="text-end">A desembolsar</th><th class="text-end">TNA</th><th class="text-end">TEA</th><th class="text-end">CFTEA</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($c['lineas'] as $l): $lid = (int) $l['id']; ?>
                    <tr>
                        <td class="fw-medium"><?= esc($l['linea']) ?>
                            <?php if ($lid === $mayor): ?><span class="badge rounded-pill text-bg-success d-block mt-1">Mayor monto</span><?php endif ?>
                            <?php if ($lid === $menor): ?><span class="badge rounded-pill text-bg-primary d-block mt-1">Menor costo</span><?php endif ?>
                        </td>
                        <td class="importe"><?= esc(formato_importe($c['montos'][$lid])) ?></td>
                        <td class="importe"><?= esc(formato_importe($l['importe'])) ?> <span class="small text-body-secondary">en <?= (int) $l['cantidad_cuotas'] ?></span></td>
                        <td class="importe"><?= esc(formato_importe($l['calculo']['primera_cuota'])) ?></td>
                        <td class="importe"><?= esc(formato_importe($l['calculo']['total_a_pagar'])) ?></td>
                        <td class="importe"><?= esc(formato_importe($l['calculo']['neto'])) ?></td>
                        <td class="text-end"><?= esc(formato_porcentaje((string) $l['tna'])) ?></td>
                        <td class="text-end"><?= esc(formato_porcentaje($l['calculo']['tea'])) ?></td>
                        <td class="text-end fw-medium"><?= esc(formato_porcentaje($l['calculo']['cftea'])) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/simulaciones/' . $lid) ?>">Ver plan</a>
                            <?php if ($vigente && tiene_permiso('solicitudes.registrar')): ?><a class="btn btn-sm btn-primary" href="<?= site_url('admin/solicitudes/nueva/' . $lid) ?>">Formalizar</a><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <p class="small text-body-secondary px-1 mt-2 mb-0">El monto máximo es el mayor capital, en alguno de los plazos de la línea, cuya primera cuota con IVA entra en la capacidad de pago, limitado al máximo de la línea (RN-76). La recomendación usa el importe pedido (o el máximo) en la menor cantidad de cuotas que entra en la capacidad. Es preliminar: no consulta la Central de Deudores (RN-17).</p>
    <?php endif ?>
</section>

<?php if ($c['no_viables'] !== []): ?>
    <section class="hoja p-4" aria-labelledby="titulo-no-viables">
        <h2 class="h5" id="titulo-no-viables">Líneas no viables</h2>
        <ul class="mb-0">
            <?php foreach ($c['no_viables'] as $n): ?><li><span class="fw-medium"><?= esc($n['nombre']) ?>:</span> <?= esc($n['motivo']) ?></li><?php endforeach ?>
        </ul>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
