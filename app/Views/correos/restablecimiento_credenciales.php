<?php
/** CU-07 Restablecer Credenciales de Usuario. @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'El Administrador del sistema inició el restablecimiento de su contraseña. Para definir la nueva, use el siguiente enlace. Su contraseña actual deja de valer cuando defina la nueva.',
    'boton' => 'Definir contraseña nueva',
    'pie' => 'Si no esperaba este mensaje, comuníquese con el Administrador.',
]);
