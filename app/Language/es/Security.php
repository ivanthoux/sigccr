<?php

/**
 * Mensajes de la protección CSRF en español (RNF-02, D-49). Sin este archivo, con la configuración regional «es» se
 * mostraba el texto en inglés del framework al volver de un formulario vencido o enviado sin su token.
 */

return [
    'disallowedAction' => 'El formulario venció o ya se había enviado. Vuelva a cargar la página e intente de nuevo.',
    'insecureCookie'   => 'La cookie de protección del formulario requiere una conexión segura.',
    'invalidSameSite'  => 'El valor de SameSite debe ser None, Lax, Strict o una cadena vacía. Se recibió: {0}',
];
