<?php

/*
 * Medición de cobertura de la batería de punta a punta (rama temporal, no se integra).
 * Se carga con auto_prepend_file en el contenedor app: registra las líneas de app/ que ejecuta cada petición o
 * comando, en un archivo comprimido por proceso y petición, dentro de build/cobertura-e2e.
 */
if (function_exists('pcov\start') && is_dir('/var/www/html/build/cobertura-e2e')) {
    \pcov\start();
    register_shutdown_function(static function (): void {
        try {
            \pcov\stop();
            $datos = \pcov\collect(\pcov\inclusive);
            \pcov\clear();
            $salida = '';
            foreach ($datos as $archivo => $lineas) {
                $ejecutadas = array_keys(array_filter($lineas, static fn ($v) => $v > 0));
                if ($ejecutadas !== []) {
                    $salida .= $archivo . "\t" . implode(',', $ejecutadas) . "\n";
                }
            }
            if ($salida !== '') {
                file_put_contents('/var/www/html/build/cobertura-e2e/' . getmypid() . '-' . hrtime(true) . '.gz', gzencode($salida, 1));
            }
        } catch (\Throwable) {
            // la medición nunca debe alterar la respuesta
        }
    });
}
