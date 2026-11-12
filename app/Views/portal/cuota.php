<?php
/**
 * CU-47 Consultar Estado de Cuenta, paso 6 (A3): detalle de una cuota con los días de atraso y la tolerancia.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $credito
 * @var array<string, mixed> $cuota
 * @var string $fecha
 */
use App\Libraries\Portal\ReglasPortal;

$q = $cuota;
$impaga = $q['total'] !== null;
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url('portal/estado-cuenta/creditos/' . (int) $credito['id']) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Crédito N.º <?= (int) $credito['numero'] ?></a></nav>
<div class="encabezado">
    <div>
        <h1>Cuota <?= (int) $q['numero'] ?> de <?= (int) $credito['cantidad_cuotas'] ?></h1>
        <p>Vence el <?= esc(formato_fecha((string) $q['vencimiento'])) ?> · <?= esc($credito['linea']) ?></p>
    </div>
    <?php if ($impaga): ?><a href="<?= url_to('portal.pagar') ?>" class="btn btn-primary">Pagar</a><?php endif ?>
</div>
<section class="hoja p-4" style="max-width: 44rem">
    <?php if (!$impaga): ?>
        <p class="mb-0">Cuota <?= esc(mb_strtolower((string) $q['estado'])) ?>: no tiene saldo pendiente.</p>
    <?php else: ?>
        <p class="<?= $q['dias_atraso'] > 0 ? 'fw-medium' : '' ?>"><?= esc($q['situacion']) ?></p>
        <dl class="resumen-plan">
            <?php foreach (ReglasPortal::CONCEPTOS as $k => $etiqueta): if ($k === 'NoCapitalizable' && $q['conceptos'][$k]->isZero()) { continue; } ?>
                <div><dt><?= esc($k === 'Capital' ? 'Capital pendiente' : $etiqueta) ?></dt><dd class="importe fw-normal"><?= esc(formato_importe($q['conceptos'][$k])) ?></dd></div>
            <?php endforeach ?>
            <div><dt>Total actualizado</dt><dd class="importe"><?= esc(formato_importe($q['total'])) ?></dd></div>
        </dl>
        <p class="small text-body-secondary mb-0">Calculado al <?= esc(formato_fecha($fecha)) ?>. Tolerancia del crédito: <?= (int) $credito['tolerancia_dias'] ?> día(s). Los punitorios se calculan sobre el capital vencido de la cuota.</p>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
