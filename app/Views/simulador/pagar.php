<?php
/**
 * Página de pago del simulador de la pasarela (desarrollo y pruebas).
 *
 * @var array<string, mixed> $orden
 * @var list<array<string, string>> $pagos
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulador de pasarela | SIG-CCR</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<main class="container py-4" style="max-width: 720px">
    <div class="alert alert-secondary small"><i class="bi bi-cone-striped me-1" aria-hidden="true"></i>Simulador de la pasarela de pagos: no existe en producción.</div>
    <?php if (session('exito')): ?><div class="alert alert-success"><?= esc((string) session('exito')) ?></div><?php endif ?>
    <div class="card mb-3">
        <div class="card-body">
            <h1 class="h4">Pagar orden N.º <?= (int) $orden['numero'] ?></h1>
            <p class="mb-1">Referencia <code><?= esc($orden['referencia_externa']) ?></code> · estado en el SIG-CCR: <strong><?= esc($orden['estado']) ?></strong></p>
            <p class="mb-0">Importe: <strong>$ <?= esc(number_format((float) $orden['importe'], 2, ',', '.')) ?></strong> · vence <?= esc(formato_fecha_hora($orden['vence'])) ?></p>
        </div>
    </div>
    <form method="post" action="<?= site_url('simulador/pagar/' . $orden['referencia_externa']) ?>" class="card mb-3">
        <?= csrf_field() ?>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="estado">Resultado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="approved">Aprobado</option>
                    <option value="rejected">Rechazado</option>
                    <option value="in_process">En proceso (no definitivo)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="importe">Importe pagado</label>
                <input class="form-control" id="importe" name="importe" inputmode="decimal" placeholder="<?= esc((string) $orden['importe'], 'attr') ?>">
                <div class="form-text">Vacío: el de la orden.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="fecha_efectiva">Fecha efectiva</label>
                <input class="form-control" type="datetime-local" id="fecha_efectiva" name="fecha_efectiva">
                <div class="form-text">Vacío: ahora.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="id_externo">Actualizar un pago anterior</label>
                <select class="form-select" id="id_externo" name="id_externo">
                    <option value="">No: pago nuevo</option>
                    <?php foreach ($pagos as $p): ?><option value="<?= esc($p['id_externo'], 'attr') ?>"><?= esc($p['id_externo'] . ' (' . $p['estado'] . ')') ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="col-md-6 d-flex flex-column justify-content-end">
                <div class="form-check"><input class="form-check-input" type="checkbox" id="notificar" name="notificar" value="1" checked><label class="form-check-label" for="notificar">Enviar la notificación</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="firma_invalida" name="firma_invalida" value="1"><label class="form-check-label" for="firma_invalida">Con firma no válida</label></div>
            </div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Simular el pago</button></div>
    </form>
    <?php if ($pagos !== []): ?>
        <div class="card"><div class="card-body">
            <h2 class="h6">Pagos simulados de esta orden</h2>
            <ul class="mb-0"><?php foreach ($pagos as $p): ?><li><code><?= esc($p['id_externo']) ?></code> · <?= esc($p['estado']) ?> · $ <?= esc($p['importe']) ?> · <?= esc($p['fecha_efectiva']) ?></li><?php endforeach ?></ul>
        </div></div>
    <?php endif ?>
</main>
</body>
</html>
