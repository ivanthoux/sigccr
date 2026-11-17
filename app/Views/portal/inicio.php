<?php
/**
 * Pantalla principal del portal (CU-01 paso 6 para el Cliente): saludo, último acceso y las funciones de autogestión.
 * Cada función es un enlace cuando su caso de uso está construido (Config\Menu::$portal); si no, se muestra como próxima.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array{icono: string, titulo: string, texto: string, url: ?string}> $funciones
 * @var string|null $ultimoAcceso fecha y hora del acceso anterior, ya en la zona de la entidad
 * @var array<string, mixed>|null $resumen estado de cuenta (CU-47) para el resumen del encabezado; null si no se pudo obtener
 */
$cuenta = session('cuenta');
$this->extend('layouts/portal');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Hola, <?= esc($cuenta['nombre']) ?></h1>
        <p><?= $ultimoAcceso === null ? 'Es su primer ingreso al portal.' : 'Su ingreso anterior fue el ' . esc($ultimoAcceso) . '. Si no lo reconoce, cambie su contraseña desde «¿Olvidó su contraseña?».' ?></p>
    </div>
</div>
<?php if ($resumen !== null && $resumen['creditos'] !== []):
    $deudaTotal = (string) $resumen['deuda']['total'];
    $conDeuda = (float) $deudaTotal > 0;
    $vencidas = $resumen['vencidas'];
    $proximo = $resumen['proximos'][0] ?? null;
    $totalVencido = $vencidas === [] ? null : \App\Libraries\Finanzas\Decimal::sum(array_map(static fn ($q) => $q['total'], $vencidas));
    ?>
    <section class="hoja p-4 mb-4" aria-labelledby="t-resumen">
        <h2 class="h5" id="t-resumen">Su cuenta hoy</h2>
        <?php if ($vencidas !== []): ?>
            <div class="alert alert-warning mb-3" role="status">Tiene <?= count($vencidas) === 1 ? '1 cuota vencida' : count($vencidas) . ' cuotas vencidas' ?> por <strong><?= esc(formato_importe($totalVencido)) ?></strong>, con los intereses punitorios a hoy.</div>
        <?php endif ?>
        <dl class="resumen-plan mb-3">
            <?php if ($proximo !== null): ?>
                <div><dt>Próximo vencimiento</dt><dd><?= esc(formato_fecha((string) $proximo['vencimiento'])) ?></dd></div>
                <div><dt>Importe de esa cuota</dt><dd class="importe"><?= esc(formato_importe($proximo['total'])) ?></dd></div>
            <?php endif ?>
            <div><dt>Saldo deudor total</dt><dd class="importe"><?= esc(formato_importe($deudaTotal)) ?></dd></div>
            <?php if ((float) $resumen['saldo_disponible'] > 0): ?><div><dt>Saldo a favor</dt><dd class="importe"><?= esc(formato_importe($resumen['saldo_disponible'])) ?></dd></div><?php endif ?>
        </dl>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($conDeuda): ?><a href="<?= url_to('portal.pagar') ?>" class="btn btn-primary"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Pagar cuotas</a><?php endif ?>
            <a href="<?= url_to('portal.estado-cuenta') ?>" class="btn btn-outline-secondary">Ver el estado de cuenta</a>
        </div>
    </section>
<?php endif ?>
<h2 class="h5 mb-3">Autogestión</h2>
<ul class="funciones-portal">
    <?php foreach ($funciones as $f): ?>
        <li>
            <i class="bi <?= esc($f['icono'], 'attr') ?>" aria-hidden="true"></i>
            <h3><?php if ($f['url'] !== null): ?><a href="<?= esc($f['url'], 'attr') ?>"><?= esc($f['titulo']) ?></a><?php else: ?><?= esc($f['titulo']) ?><?php endif ?></h3>
            <p><?= esc($f['texto']) ?></p>
            <?php if ($f['url'] === null): ?><span class="badge text-bg-light border mt-2">Próximamente</span><?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
<p class="small text-body-secondary mt-4 mb-0">Por seguridad, la sesión se cierra sola después de <?= (int) (config('Sigccr')->inactividadPortal / 60) ?> minutos sin actividad.</p>
<?php $this->endSection() ?>
