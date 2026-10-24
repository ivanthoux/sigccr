<?php
/**
 * Correo de CU-02 (RN-03). El enlace se envía solo por este medio; no se registra en la auditoría (RNF-14).
 *
 * @var string $nombre
 * @var string $enlace
 * @var string $vence
 * @var int $minutos
 */
?>
<!doctype html>
<html lang="es">
<body style="font-family: Arial, sans-serif; color: #212529; line-height: 1.5;">
<p>Hola<?= $nombre !== '' ? ' ' . esc($nombre) : '' ?>:</p>
<p>Recibimos un pedido para recuperar el acceso a su cuenta del SIG-CCR. Para definir una nueva contraseña, ingrese al siguiente enlace:</p>
<p><a href="<?= esc($enlace, 'attr') ?>" style="background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Definir nueva contraseña</a></p>
<p>El enlace puede usarse una sola vez y vence en <?= (int) $minutos ?> minutos (<?= esc($vence) ?>).</p>
<p>Si no solicitó la recuperación, ignore este mensaje: su contraseña actual sigue vigente.</p>
<p style="color:#6c757d;font-size:12px;">Mensaje automático del SIG-CCR. No responda este correo.</p>
</body>
</html>
