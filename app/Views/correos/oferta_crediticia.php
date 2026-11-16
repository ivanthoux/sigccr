<?php
/**
 * CU-93 paso 6: comunicación de una oferta de campaña (comercial: requiere el consentimiento del cliente, RN-64).
 *
 * @var string $nombre
 * @var string $linea
 * @var string $importe
 * @var string $cuotas
 * @var string $primera_cuota
 * @var string $tna
 * @var string $cftea
 * @var string $vence
 * @var string $enlace
 * @var string $mensaje
 */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SIG-CCR</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1a2330;line-height:1.5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dde3ea;border-radius:8px;">
    <tr><td style="background:#15304b;color:#ffffff;padding:18px 24px;font-size:20px;font-weight:bold;border-radius:8px 8px 0 0;">SIG-CCR</td></tr>
    <tr><td style="padding:24px;">
        <p style="margin-top:0;">Hola<?= $nombre !== '' ? ' ' . esc($nombre) : '' ?>:</p>
        <p>Tiene una oferta de crédito de la línea <strong><?= esc($linea) ?></strong> por hasta <strong><?= esc($importe) ?></strong> en <?= esc($cuotas) ?> cuotas (primera cuota estimada de <?= esc($primera_cuota) ?>, TNA <?= esc($tna) ?>, CFTEA <?= esc($cftea) ?>).</p>
        <?php if ($mensaje !== ''): ?><p><?= esc($mensaje) ?></p><?php endif ?>
        <p style="margin:24px 0;"><a href="<?= esc($enlace, 'attr') ?>" style="background:#1f7a57;color:#ffffff;padding:12px 18px;border-radius:6px;text-decoration:none;font-weight:bold;">Ver la oferta en el portal</a></p>
        <p>La oferta está vigente hasta el <?= esc($vence) ?>. Aceptarla genera una solicitud de crédito que se evalúa como cualquier otra: no implica el desembolso ni genera deuda.</p>
        <p style="color:#536271;">Recibe este correo porque aceptó recibir comunicaciones comerciales. Puede revocar ese consentimiento en el portal, en Mi perfil → Preferencias de comunicación.</p>
    </td></tr>
</table>
<p style="max-width:560px;margin:12px auto 0;color:#536271;font-size:12px;">Mensaje automático del SIG-CCR. No responda este correo.</p>
</body>
</html>
