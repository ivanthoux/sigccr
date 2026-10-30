<?php
/** CU-06 Modificar Datos de Usuario (verificación del correo nuevo). @var string $nombre @var string $enlace @var string $vence @var string $vigencia */
echo view('correos/_marco', [
    'nombre' => $nombre, 'enlace' => $enlace, 'vence' => $vence, 'vigencia' => $vigencia,
    'parrafo' => 'Se registró esta dirección como su nuevo correo en el SIG-CCR. Confírmela con el siguiente enlace; hasta entonces sigue vigente su correo anterior.',
    'boton' => 'Confirmar este correo',
    'pie' => 'Si no reconoce este cambio, comuníquese con el Administrador.',
]);
