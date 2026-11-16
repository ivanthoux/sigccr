<?php
/**
 * Modo mantenimiento durante la restauración de una copia (CU-63 paso 8, D-45). No usa la base de datos ni la sesión.
 *
 * @var array<string, mixed>|null $estado restauración en curso
 * @var array<string, mixed>|null $ultima resultado de la última, cuando ya terminó
 */
$activa = $estado !== null && in_array($estado['estado'] ?? '', ['Pendiente', 'EnCurso'], true);
$ultima ??= null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($activa): ?><script>setTimeout(() => window.location.reload(), 15000);</script><?php endif ?>
    <title>Sistema en mantenimiento | SIG-CCR</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 40rem">
    <?php if ($activa): ?>
        <h1 class="h3">Sistema en mantenimiento</h1>
        <p>Se está restaurando una copia de seguridad de la base de datos. El panel y el portal no están disponibles hasta que termine; las sesiones abiertas se cerraron.</p>
        <div class="alert alert-info">
            <div class="fw-semibold"><?= esc((string) ($estado['etapa'] ?? 'En curso')) ?>…</div>
            <div class="small">Esta página se actualiza sola cada 15 segundos.</div>
        </div>
    <?php elseif ($ultima !== null): ?>
        <h1 class="h3">Restauración <?= !empty($ultima['exitosa']) ? 'finalizada' : 'no realizada' ?></h1>
        <div class="alert <?= !empty($ultima['exitosa']) ? 'alert-success' : 'alert-warning' ?>"><?= esc((string) ($ultima['mensaje'] ?? '')) ?></div>
        <p>El sistema volvió a operar. Inicie sesión nuevamente; el Administrador encuentra el detalle en <em>Copias de seguridad</em>.</p>
        <a class="btn btn-primary" href="<?= site_url('admin/login') ?>">Iniciar sesión</a>
    <?php else: ?>
        <h1 class="h3">Sin restauraciones en curso</h1>
        <p>El sistema está operando normalmente.</p>
        <a class="btn btn-primary" href="<?= site_url('admin/login') ?>">Iniciar sesión</a>
    <?php endif ?>
</main>
</body>
</html>
