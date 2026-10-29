<?php
/** CU-04 Activar o Rehabilitar Acceso del Cliente. @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Recibimos su pedido para activar el acceso al portal de autogestión del SIG-CCR. Defina su contraseña con el siguiente enlace; después podrá ingresar con este correo.',
    'boton' => 'Activar mi acceso',
    'pie' => 'Si usted no lo pidió, ignore este mensaje: su cuenta no cambia.',
]);
