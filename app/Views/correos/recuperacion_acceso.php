<?php
/** CU-02 Recuperar Acceso. @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Recibimos un pedido para recuperar el acceso a su cuenta. Para definir una contraseña nueva, use el siguiente enlace.',
    'boton' => 'Definir contraseña nueva',
    'pie' => 'Si no pidió recuperar el acceso, ignore este mensaje: su contraseña actual sigue vigente.',
]);
