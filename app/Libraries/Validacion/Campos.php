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

    /**
     * Recorta y colapsa los espacios internos. Un valor que no es texto ni número (por ejemplo, un campo enviado como
     * lista: nombre[]=x) se trata como vacío, y los caracteres de control se eliminan: la tabulación y los saltos de
     * línea cuentan como espacios (D-51, clases COM-02, COM-05 y COM-06).
     */
    public static function texto(mixed $valor): string
    {
        if (!is_scalar($valor)) {
            return '';
        }
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $valor) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
    }

    /**
     * Lista de valores de un formulario (casillas múltiples, campo[]): solo los elementos que son texto o número,
     * como texto. Un valor suelto se toma como lista de un elemento; uno anidado (campo[][]=x) se descarta (D-51).
     *
     * @return list<string>
     */
    public static function lista(mixed $valor): array
    {
        $r = [];
        foreach (is_array($valor) ? $valor : ($valor === null || $valor === '' ? [] : [$valor]) as $v) {
            if (is_scalar($v)) {
                $r[] = (string) $v;
            }
        }

        return $r;
    }

    /**
     * Texto para buscar con like() del Query Builder como texto literal: CodeIgniter agrega ESCAPE '!' pero no escapa
     * los comodines del valor, así que «%» devolvía todos los registros (D-51, H-12, clase FIL-03).
     */
    public static function literalLike(string $texto): string
    {
        return str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $texto);
    }

    /** Valor de un formulario como texto sin transformar (contraseñas): un valor que no es texto se trata como vacío. */
    public static function crudo(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    /**
     * Identificador o versión enviado en un formulario: entero positivo de hasta 9 dígitos, con espacios en los
     * extremos admitidos; cualquier otra cosa (lista, vacío, signo, decimales, letras) vale 0 (H-21).
     */
    public static function identificador(mixed $valor): int
    {
        $t = trim(self::crudo($valor));

        return preg_match('/^\d{1,9}$/', $t) === 1 ? (int) $t : 0;
    }

    /** Correo en minúsculas y sin espacios. */
    public static function correo(mixed $valor): string
    {
        return mb_strtolower(str_replace(' ', '', self::texto($valor)));
    }

    /**
     * Número de documento en mayúsculas y sin espacios en los extremos. Solo se quitan los puntos de un número escrito
     * con separador de miles (28.999.111); cualquier otro carácter se conserva para que numeroDocumento() lo rechace
     * (por ejemplo, «28999111.»).
     */
    public static function documento(mixed $valor): string
    {
        $v = strtoupper(self::texto($valor));

        return preg_match('/^\d{1,3}(\.\d{3})+$/', $v) === 1 ? str_replace('.', '', $v) : $v;
    }

    /** Número de documento para buscar o identificarse: sin puntos, espacios ni guiones. */
    public static function documentoFlexible(mixed $valor): string
    {
        return strtoupper(str_replace(['.', ' ', '-'], '', self::texto($valor)));
    }

    public static function obligatorio(string $valor, string $nombreCampo): ?string
    {
        return $valor === '' ? "Complete {$nombreCampo}." : null;
    }

    /**
     * Largo de un texto como lo mide SQL Server en una columna NVARCHAR (unidades UTF-16): las letras con tilde y la
     * eñe cuentan uno, y un emoji u otro carácter fuera del plano básico, dos. Con mb_strlen() un texto con emoji
     * pasaba la validación y fallaba al guardarse (D-51, clase TXT-05).
     */
    public static function largo(string $valor): int
    {
        return intdiv(strlen((string) mb_convert_encoding($valor, 'UTF-16LE', 'UTF-8')), 2);
    }

    public static function largoMaximo(string $valor, int $maximo, string $nombreCampo): ?string
    {
        return self::largo($valor) > $maximo ? ucfirst($nombreCampo) . " admite hasta {$maximo} caracteres." : null;
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

    /**
     * Correo con formato válido (O-05, D-52): admite direcciones internacionales, con letras no ASCII en la parte local
     * (RFC 6531) o en el dominio (IDN, que se valida en su forma punycode). El dominio debe tener al menos un punto y
     * el largo total no puede superar 254 unidades, como la columna NVARCHAR(254). La parte local admite hasta 64
     * octetos (RFC 5321, 4.5.3.1.1): 64 letras ASCII o 32 eñes, que ocupan dos octetos cada una en UTF-8.
     */
    public static function formatoCorreo(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }
        $error = 'Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.';
        $arroba = strrpos($valor, '@');
        if ($arroba === false || $arroba > 64 || self::largo($valor) > 254) {
            return $error;
        }
        $dominio = self::dominioAscii(substr($valor, $arroba + 1));
        if ($dominio === null || !str_contains($dominio, '.')) {
            return $error;
        }

        return filter_var(substr($valor, 0, $arroba) . '@' . $dominio, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false ? $error : null;
    }

    /** Dominio de un correo en ASCII (punycode para un dominio internacional); null si no es un dominio válido. */
    public static function dominioAscii(string $dominio): ?string
    {
        if ($dominio === '' || preg_match('/^[\x00-\x7F]+$/', $dominio) === 1) {
            return $dominio === '' ? null : $dominio;
        }
        $ascii = idn_to_ascii($dominio, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return $ascii === false ? null : $ascii;
    }

    /** Correo con el dominio en ASCII, para enviarlo por SMTP (la parte local se conserva). */
    public static function correoParaEnvio(string $correo): string
    {
        $arroba = strrpos($correo, '@');
        if ($arroba === false) {
            return $correo;
        }

        return substr($correo, 0, $arroba) . '@' . (self::dominioAscii(substr($correo, $arroba + 1)) ?? substr($correo, $arroba + 1));
    }

    public static function tipoDocumento(string $valor): ?string
    {
        return $valor !== '' && !in_array($valor, self::TIPOS_DOCUMENTO, true) ? 'Seleccione un tipo de documento válido.' : null;
    }

    /**
     * DNI, LC y LE: 6 a 8 dígitos, el largo que admite el CUIL (D-53); pasaporte: 5 a 15 letras o dígitos.
     */
    public static function numeroDocumento(string $tipo, string $numero): ?string
    {
        if ($numero === '') {
            return null;
        }
        if ($tipo === 'Pasaporte') {
            return preg_match('/^[A-Z0-9]{5,15}$/', $numero) === 1 ? null : 'El pasaporte debe tener entre 5 y 15 letras o números.';
        }
        if (preg_match('/^\d{6,8}$/', $numero) !== 1) {
            return 'El documento debe tener entre 6 y 8 dígitos, sin puntos.';
        }

        return (int) $numero === 0 ? 'El número de documento no es válido.' : null;
    }

    /** Prefijos de CUIT/CUIL por tipo de persona (AFIP): 20, 23, 24 y 27 para personas humanas; 30, 33 y 34 para jurídicas. */
    public const PREFIJOS_CUIT = ['PersonaHumana' => ['20', '23', '24', '27'], 'PersonaJuridica' => ['30', '33', '34']];

    /**
     * CUIT o CUIL: 11 dígitos, prefijo válido para el tipo de persona y dígito verificador (módulo 11). El dígito
     * depende del prefijo: al cambiar 20 por 27 cambia también el último dígito, y el mensaje indica cuál corresponde.
     *
     * @param 'PersonaHumana'|'PersonaJuridica'|null $tipo null admite cualquier prefijo válido
     */
    public static function cuit(string $valor, ?string $tipo = null): ?string
    {
        if ($valor === '') {
            return null;
        }
        $c = str_replace(['-', ' ', '.'], '', $valor);
        if (preg_match('/^\d{11}$/', $c) !== 1) {
            return 'El CUIT o CUIL debe tener 11 dígitos.';
        }
        $prefijo = substr($c, 0, 2);
        $validos = $tipo === null ? array_merge(...array_values(self::PREFIJOS_CUIT)) : self::PREFIJOS_CUIT[$tipo];
        if (!in_array($prefijo, $validos, true)) {
            return $tipo === 'PersonaHumana' ? 'El CUIL o CUIT de una persona humana empieza con 20, 23, 24 o 27.'
                : ($tipo === 'PersonaJuridica' ? 'El CUIT de una persona jurídica empieza con 30, 33 o 34.' : 'El CUIT o CUIL tiene un prefijo inválido.');
        }
        $dv = self::digitoVerificadorCuit(substr($c, 0, 10));
        if ($dv === null) {
            return "Con el prefijo {$prefijo} ese número no tiene un CUIT o CUIL válido: verifique el prefijo asignado (por ejemplo, 23 o 33).";
        }

        return (int) $c[10] === $dv ? null
            : 'El dígito verificador no corresponde: para ' . $prefijo . '-' . substr($c, 2, 8) . " el último dígito es {$dv}.";
    }

    /** Dígito verificador de los 10 primeros dígitos de un CUIT o CUIL, o null si el resultado es 10 (combinación no asignable). */
    public static function digitoVerificadorCuit(string $diezDigitos): ?int
    {
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        foreach ($pesos as $i => $p) {
            $suma += (int) $diezDigitos[$i] * $p;
        }
        $dv = 11 - ($suma % 11);

        return $dv === 11 ? 0 : ($dv === 10 ? null : $dv);
    }

    /** Texto de motivo de una operación (auditoría): entre 5 y 1000 caracteres. */
    public static function motivo(string $valor, int $maximo = 1000): ?string
    {
        $largo = self::largo($valor);
        if ($largo < 5) {
            return 'Indique el motivo de la operación (al menos 5 caracteres).';
        }

        // H-14: el máximo es el de la columna donde se guarda; nunca se recorta en silencio
        return $largo > $maximo ? "El motivo admite hasta {$maximo} caracteres." : null;
    }

    /**
     * Importe ingresado con formato local ("1.234.567,89", "1234567,89" o "1234567.89") normalizado a "1234567.89".
     * Devuelve null si no es un importe válido con hasta dos decimales.
     */
    public static function importe(mixed $valor): ?string
    {
        $t = str_replace([' ', '$'], '', self::texto($valor));
        if ($t === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $t) === 1 || str_contains($t, ',')) {
            $t = str_replace(['.', ','], ['', '.'], $t);
        }
        if (preg_match('/^\d{1,17}(\.\d{1,2})?$/', $t) !== 1) {
            return null;
        }
        [$entero, $dec] = array_pad(explode('.', $t), 2, '');

        return ltrim($entero, '0') === '' ? '0.' . str_pad($dec, 2, '0') : ltrim($entero, '0') . '.' . str_pad($dec, 2, '0');
    }

    /** Fecha dd/mm/aaaa o aaaa-mm-dd normalizada a aaaa-mm-dd; null si no es una fecha válida. */
    public static function fecha(mixed $valor): ?string
    {
        $t = self::texto($valor);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) === 1) {
            [$a, $mes, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $t, $m) === 1) {
            [$d, $mes, $a] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mes, $d, $a) && $a >= 1900 ? sprintf('%04d-%02d-%02d', $a, $mes, $d) : null;
    }

    /** Años cumplidos entre dos fechas aaaa-mm-dd. */
    public static function edad(string $nacimiento, string $hoy): int
    {
        return (new \DateTimeImmutable($nacimiento))->diff(new \DateTimeImmutable($hoy))->y;
    }

    /** Teléfono informativo (RN-08): dígitos, espacios, +, guiones y paréntesis; entre 6 y 30 caracteres. */
    public static function telefono(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }

        return preg_match('/^\+?[\d\s()\-]{6,30}$/', $valor) === 1 && preg_match_all('/\d/', $valor) >= 6
            ? null : 'Ingrese un teléfono válido (solo números, espacios, guiones o +).';
    }

    /** CUIL coherente con el DNI: los ocho dígitos centrales son el documento. */
    public static function cuilCorrespondeADni(string $cuil, string $dni): bool
    {
        return strlen($cuil) === 11 && substr($cuil, 2, 8) === str_pad($dni, 8, '0', STR_PAD_LEFT);
    }
}
