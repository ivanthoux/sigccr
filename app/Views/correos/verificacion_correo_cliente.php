<?php
/** CU-11 y CU-12: verificación del correo de un cliente, que continúa con la activación del portal (D-16). @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'La entidad registró esta dirección como su correo de contacto. Confírmela con el siguiente enlace: si todavía no usa el portal de autogestión, a continuación podrá definir su contraseña para activarlo.',
    'boton' => 'Confirmar mi correo',
    'pie' => 'Si usted no solicitó este registro, ignore este mensaje o comuníquese con la entidad.',
]);
