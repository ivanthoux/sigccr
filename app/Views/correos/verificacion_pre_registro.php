<?php
/** CU-104 paso 5: verificación del correo de un pre-registro. @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Recibimos su pre-registro en el portal de autogestión. Verifique su correo con el siguiente enlace; después acérquese a la sucursal con su documento para completar el alta.',
    'boton' => 'Verificar mi correo',
    'pie' => 'Si usted no se pre-registró, ignore este mensaje: sus datos no se usarán.',
]);
