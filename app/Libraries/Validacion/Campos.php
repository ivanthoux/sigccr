<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

/**
 * Normalización y validación de campos comunes del SIG-CCR (sin acceso a la base). Cada validación devuelve el
 * mensaje de error para el usuario o null si el valor es válido; los valores vacíos se tratan con obligatorio().
 */
final class Campos
{
    public const TIPOS_DOCUMENTO = ['DNI', 'LC', 'LE', 'Pasaporte'];

    /** Recorta y colapsa los espacios internos. */
    public static function texto(mixed $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($valor ?? '')) ?? '');
    }

    /** Correo en minúsculas y sin espacios. */
    public static function correo(mixed $valor): string
    {
        return mb_strtolower(str_replace(' ', '', self::texto($valor)));
    }

    /** Número de documento sin puntos, espacios ni guiones, en mayúsculas. */
    public static function documento(mixed $valor): string
    {
        return strtoupper(str_replace(['.', ' ', '-'], '', self::texto($valor)));
    }

    public static function obligatorio(string $valor, string $nombreCampo): ?string
    {
        return $valor === '' ? "Complete {$nombreCampo}." : null;
    }

    public static function largoMaximo(string $valor, int $maximo, string $nombreCampo): ?string
    {
        return mb_strlen($valor) > $maximo ? ucfirst($nombreCampo) . " admite hasta {$maximo} caracteres." : null;
    }

    /** Nombres y apellidos: letras (con tildes y ñ), espacios, apóstrofo, guion y punto. */
    public static function nombrePersona(string $valor, string $nombreCampo): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (preg_match("/^[\\p{L}][\\p{L} '.\\-]*$/u", $valor) !== 1) {
            return ucfirst($nombreCampo) . ' solo admite letras, espacios, apóstrofos y guiones.';
        }

        return self::largoMaximo($valor, 80, $nombreCampo);
    }

    public static function formatoCorreo(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (mb_strlen($valor) > 254 || filter_var($valor, FILTER_VALIDATE_EMAIL) === false || !str_contains(substr($valor, strrpos($valor, '@') ?: 0), '.')) {
            return 'Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.';
        }

        return null;
    }

    public static function tipoDocumento(string $valor): ?string
    {
        return $valor !== '' && !in_array($valor, self::TIPOS_DOCUMENTO, true) ? 'Seleccione un tipo de documento válido.' : null;
    }

    /** DNI, LC y LE: 6 a 9 dígitos; pasaporte: 5 a 15 letras o dígitos. */
    public static function numeroDocumento(string $tipo, string $numero): ?string
    {
        if ($numero === '') {
            return null;
        }
        if ($tipo === 'Pasaporte') {
            return preg_match('/^[A-Z0-9]{5,15}$/', $numero) === 1 ? null : 'El pasaporte debe tener entre 5 y 15 letras o números.';
        }
        if (preg_match('/^\d{6,9}$/', $numero) !== 1) {
            return 'El documento debe tener entre 6 y 9 dígitos, sin puntos.';
        }

        return (int) $numero === 0 ? 'El número de documento no es válido.' : null;
    }

    /** CUIT o CUIL: 11 dígitos, prefijo válido y dígito verificador (módulo 11). */
    public static function cuit(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }
        $c = str_replace(['-', ' ', '.'], '', $valor);
        if (preg_match('/^\d{11}$/', $c) !== 1) {
            return 'El CUIT o CUIL debe tener 11 dígitos.';
        }
        if (!in_array(substr($c, 0, 2), ['20', '23', '24', '27', '30', '33', '34'], true)) {
            return 'El CUIT o CUIL tiene un prefijo inválido.';
        }
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        foreach ($pesos as $i => $p) {
            $suma += (int) $c[$i] * $p;
        }
        $dv = 11 - ($suma % 11);
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);

        return (int) $c[10] === $dv ? null : 'El dígito verificador del CUIT o CUIL no es correcto.';
    }

    /** Texto de motivo de una operación (auditoría): entre 5 y 1000 caracteres. */
    public static function motivo(string $valor): ?string
    {
        $largo = mb_strlen($valor);
        if ($largo < 5) {
            return 'Indique el motivo de la operación (al menos 5 caracteres).';
        }

        return $largo > 1000 ? 'El motivo admite hasta 1000 caracteres.' : null;
    }
}
