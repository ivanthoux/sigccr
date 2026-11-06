<?php
/**
 * Marco común de los correos de autorización (RN-03). El enlace se envía solo por este medio; no se registra en la
 * auditoría (RNF-14).
 *
 * @var string $nombre
 * @var string $parrafo  explicación del motivo del correo
 * @var string $boton    texto del botón
 * @var string $enlace
 * @var string $vence
 * @var string $vigencia
 * @var string $pie      qué hacer si no corresponde
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
        <p><?= esc($parrafo) ?></p>
        <p style="margin:24px 0;"><a href="<?= esc($enlace, 'attr') ?>" style="background:#1f7a57;color:#ffffff;padding:12px 18px;border-radius:6px;text-decoration:none;font-weight:bold;"><?= esc($boton) ?></a></p>
        <p>El enlace puede usarse una sola vez y vence en <?= esc($vigencia) ?> (<?= esc($vence) ?>).</p>
        <p style="color:#536271;"><?= esc($pie) ?></p>
    </td></tr>
</table>
<p style="max-width:560px;margin:12px auto 0;color:#536271;font-size:12px;">Mensaje automático del SIG-CCR. No responda este correo.</p>
</body>
</html>
