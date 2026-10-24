<?php

declare(strict_types=1);

namespace App\Libraries\Seguridad;

/**
 * Política de contraseñas (RN-62): entre 15 y 128 caracteres y no figurar en la lista de contraseñas comprometidas.
 * La lista (contrasenas_comprometidas.txt, una por línea) puede reemplazarse por una más extensa sin cambiar código.
 */
final class PoliticaContrasena
{
    public const MINIMO = 15;
    public const MAXIMO = 128;

    /** @var array<string, true>|null */
    private static ?array $comprometidas = null;

    /** @return list<string> motivos de rechazo; vacía si la contraseña es aceptable */
    public static function validar(string $contrasena, ?string $confirmacion = null): array
    {
        $motivos = [];
        $largo = mb_strlen($contrasena);
        if ($largo < self::MINIMO || $largo > self::MAXIMO) {
            $motivos[] = 'La contraseña debe tener entre ' . self::MINIMO . ' y ' . self::MAXIMO . ' caracteres.';
        }
        if ($largo > 0 && isset(self::comprometidas()[self::normalizar($contrasena)])) {
            $motivos[] = 'La contraseña figura en la lista de contraseñas comprometidas. Elija otra.';
        }
        if ($confirmacion !== null && !hash_equals($contrasena, $confirmacion)) {
            $motivos[] = 'La confirmación no coincide con la contraseña.';
        }

        return $motivos;
    }

    /** @return array<string, true> */
    private static function comprometidas(): array
    {
        if (self::$comprometidas === null) {
            $lineas = file(__DIR__ . '/contrasenas_comprometidas.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$comprometidas = [];
            foreach ($lineas as $l) {
                if (!str_starts_with($l, '#')) {
                    self::$comprometidas[self::normalizar($l)] = true;
                }
            }
        }

        return self::$comprometidas;
    }

    private static function normalizar(string $s): string
    {
        return mb_strtolower(trim($s));
    }
}
