<?php
/** CU-75 paso 5: confirmación del cambio, enviada al correo actual (RN-58). @var string $nombre @var string $enlace @var string $vence @var string $vigencia @var string|null $correo_nuevo */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Recibimos desde el portal un pedido para reemplazar este correo por ' . ($correo_nuevo ?? 'otro') . '. Si fue usted, confírmelo con el siguiente enlace; después le enviaremos otro al correo nuevo para verificarlo.',
    'boton' => 'Confirmar el cambio',
    'pie' => 'Si usted no lo pidió, ignore este mensaje: su correo no cambia. Le recomendamos cambiar su contraseña del portal.',
]);
