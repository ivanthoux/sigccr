<?php
/** CU-75 paso 7: verificación del correo nuevo (RN-58). @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Confirmó desde su correo anterior el cambio a esta dirección. Verifíquela con el siguiente enlace para completar el cambio: desde entonces la usará para ingresar al portal.',
    'boton' => 'Verificar mi correo nuevo',
    'pie' => 'Si usted no pidió este cambio, ignore este mensaje y comuníquese con la entidad.',
]);
