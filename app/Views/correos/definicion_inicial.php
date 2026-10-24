<?php
/** CU-05 Registrar Usuario Interno (definición inicial). @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Se creó su usuario en el SIG-CCR. Para activarlo, defina su contraseña con el siguiente enlace; después podrá iniciar sesión con este correo.',
    'boton' => 'Activar mi usuario',
    'pie' => 'Si el enlace vence, pida al Administrador que le envíe uno nuevo.',
]);
