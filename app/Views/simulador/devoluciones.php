<?php
/**
 * Simulador de devoluciones de la pasarela (desarrollo y pruebas).
 *
 * @var array<string, mixed> $datos
 */
$modos = ['approved' => 'Aprobar', 'rejected' => 'Rechazar', 'in_process' => 'Dejar en proceso (no definitivo)', 'timeout' => 'No responder'];
$modo = (string) ($datos['modo'] ?? 'approved');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulador de devoluciones | SIG-CCR</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<main class="container py-4" style="max-width: 760px">
    <div class="alert alert-secondary small">Simulador de la pasarela de pagos: no existe en producción.</div>
    <?php if (session('exito')): ?><div class="alert alert-success"><?= esc((string) session('exito')) ?></div><?php endif ?>
    <form method="post" action="<?= site_url('simulador/devoluciones') ?>" class="card mb-3">
        <?= csrf_field() ?>
        <div class="card-body">
            <h1 class="h5">Respuesta a las próximas devoluciones</h1>
            <?php foreach ($modos as $k => $n): ?>
                <div class="form-check"><input class="form-check-input" type="radio" name="modo" id="modo-<?= $k ?>" value="<?= $k ?>"<?= $modo === $k ? ' checked' : '' ?>><label class="form-check-label" for="modo-<?= $k ?>"><?= esc($n) ?></label></div>
            <?php endforeach ?>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
    <div class="card">
        <div class="card-body">
            <h2 class="h6">Devoluciones recibidas</h2>
            <?php if (($datos['devoluciones'] ?? []) === []): ?><p class="mb-0">Ninguna.</p><?php endif ?>
            <?php foreach ((array) ($datos['devoluciones'] ?? []) as $op => $d): ?>
                <form method="post" action="<?= site_url('simulador/devoluciones') ?>" class="d-flex align-items-center gap-2 border-bottom py-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="operacion" value="<?= esc((string) $op, 'attr') ?>">
                    <span class="flex-grow-1"><code><?= esc((string) $op) ?></code> · $ <?= esc((string) ($d['importe'] ?? '')) ?> · <?= esc((string) $d['estado']) ?></span>
                    <?php if (!in_array($d['estado'], ['approved', 'rejected'], true)): ?>
                        <button class="btn btn-sm btn-outline-success" name="estado" value="approved">Aprobar</button>
                        <button class="btn btn-sm btn-outline-danger" name="estado" value="rejected">Rechazar</button>
                    <?php endif ?>
                </form>
            <?php endforeach ?>
        </div>
    </div>
</main>
</body>
</html>
