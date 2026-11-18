<?php

declare(strict_types=1);

namespace Tests\Support\Feature;

/**
 * Clases de datos por tipo del Anexo B.1 del plan de pruebas (D-51). Cada método recibe la definición de un campo del
 * catálogo y devuelve sus casos: [clase, descripción, valor enviado, resultado esperado].
 *
 * Resultado esperado:
 *  - Esperado::acepta($guardado): la operación se acepta y la columna del campo queda con $guardado (null: no se
 *    verifica el valor guardado, por ejemplo en una contraseña);
 *  - Esperado::rechaza($mensaje): el formulario vuelve con ese mensaje en el campo y la base no cambia.
 *
 * Definición de un campo (todas las claves son opcionales salvo «tipo»):
 *  - obligatorio (bool), max (int), mensajes: obligatorio, formato, largo y las propias del tipo;
 *  - normal (string): un valor válido del campo, para las clases que lo modifican.
 *
 * @phpstan-type Caso array{0: string, 1: string, 2: mixed, 3: Esperado}
 */
final class ClasesDeDatos
{
    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    public static function para(array $c, bool $http = true): array
    {
        $tipo = (string) $c['tipo'];
        $propias = match ($tipo) {
            'TXT' => self::texto($c),
            'NOM' => self::nombre($c),
            'COR' => self::correo($c),
            'DOC' => self::documento($c),
            'CUI' => self::cuit($c),
            'IMP' => self::importe($c),
            'PCT' => self::porcentaje($c),
            'ENT' => self::entero($c),
            'FEC' => self::fecha($c),
            'SEL' => self::lista($c),
            'CHK' => self::casilla($c),
            'TEL' => self::telefono($c),
            'CPO' => self::codigoPostal($c),
            'MOT' => self::motivo($c),
            'COD' => self::codigoLinea($c),
            default => throw new \InvalidArgumentException("Tipo sin clases: {$tipo}"),
        };

        return [...self::comunes($c, $http), ...$propias];
    }

    /**
     * COM-01 a COM-06.
     *
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    public static function comunes(array $c, bool $http = true): array
    {
        $html = '<b>Ana</b><script>alert(1)</script>';
        $sql = "Ana'); DROP TABLE clientes;--";
        $vacio = self::vacio($c);
        $normal = (string) ($c['normal'] ?? 'Ana');
        $libre = in_array($c['tipo'], ['TXT', 'MOT'], true);
        $formato = Esperado::rechaza($c['mensajes']['formato'] ?? $c['mensajes']['obligatorio'] ?? null);
        if ($c['tipo'] === 'CHK') {
            // Una casilla cuenta como marcada con cualquier valor no vacío (CHK-03), también enviada como lista
            return [
                ['COM-01', 'campo ausente', Esperado::AUSENTE, isset($c['mensajes']['obligatorio']) ? Esperado::rechaza($c['mensajes']['obligatorio']) : Esperado::acepta('0')],
                ['COM-02', 'enviada como lista', ['1'], Esperado::acepta('1')],
                ['COM-03', 'código HTML', $html, Esperado::acepta('1')],
            ];
        }
        if (isset($c['fuera_se_toma_como'])) {
            // Una lista cuyo valor fuera de catálogo se normaliza a uno admitido (observaciones O-02 y O-03)
            $formato = Esperado::acepta($c['fuera_se_toma_como']);
            $libre = false;
        }
        $casos = [
            ['COM-01', 'campo ausente', Esperado::AUSENTE, $vacio],
            ['COM-02', 'enviado como lista', [$normal], isset($c['fuera_se_toma_como']) ? Esperado::acepta($c['fuera_se_toma_como']) : $vacio],
            ['COM-03', 'código HTML', $html, $libre ? Esperado::acepta($html) : $formato],
            ['COM-04', 'texto SQL', $sql, $libre ? Esperado::acepta($sql) : $formato],
        ];
        if ($libre || $c['tipo'] === 'NOM') {
            $casos[] = ['COM-05', 'espacios y saltos de línea', "  Ana \t  María\r\n ", Esperado::acepta('Ana María')];
            // Por HTTP, el filtro invalidchars rechaza la petición; dentro del servicio, Campos::texto() los elimina
            $casos[] = ['COM-06', 'caracteres de control', "Ana\0\x07 María", $http ? Esperado::rechazaPeticion() : Esperado::acepta('Ana María')];
        }
        return $casos;
    }

    /** @param array<string, mixed> $c */
    private static function vacio(array $c): Esperado
    {
        return ($c['obligatorio'] ?? false)
            ? Esperado::rechaza($c['mensajes']['obligatorio'] ?? null)
            : Esperado::acepta($c['vacio_guardado'] ?? null);
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function texto(array $c): array
    {
        $n = (int) $c['max'];
        $largo = Esperado::rechaza($c['mensajes']['largo'] ?? null);

        return [
            ['TXT-01', 'vacío', '', self::vacio($c)],
            ['TXT-01', 'solo espacios', '     ', self::vacio($c)],
            ['TXT-02', "{$n} caracteres", str_repeat('a', $n), Esperado::acepta(str_repeat('a', $n))],
            ['TXT-03', ($n + 1) . ' caracteres', str_repeat('a', $n + 1), $largo],
            ['TXT-04', "{$n} caracteres con tildes y eñe", self::multibyte($n), Esperado::acepta(self::multibyte($n))],
            ['TXT-05', ($n - 1) . ' caracteres y un emoji', str_repeat('a', $n - 1) . '😀', $largo],
            ['TXT-05', ($n - 2) . ' caracteres y un emoji', str_repeat('a', $n - 2) . '😀', Esperado::acepta(str_repeat('a', $n - 2) . '😀')],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function nombre(array $c): array
    {
        $formato = Esperado::rechaza($c['mensajes']['formato'] ?? null);

        return [
            ['NOM-01', 'vacío', '', Esperado::rechaza($c['mensajes']['obligatorio'] ?? null)],
            ['NOM-01', 'solo espacios', '   ', Esperado::rechaza($c['mensajes']['obligatorio'] ?? null)],
            ['NOM-02', 'apóstrofo', "O'Brien", Esperado::acepta("O'Brien")],
            ['NOM-02', 'guion', 'Pérez-Gómez', Esperado::acepta('Pérez-Gómez')],
            ['NOM-02', 'punto', 'Ma. José', Esperado::acepta('Ma. José')],
            ['NOM-02', 'tildes y diéresis', 'Núñez Güemes', Esperado::acepta('Núñez Güemes')],
            ['NOM-03', 'dígito', 'Ana2', $formato],
            ['NOM-03', 'guion bajo', 'Ana_María', $formato],
            ['NOM-03', 'arroba', 'Ana@', $formato],
            ['NOM-04', 'empieza con guion', '-Ana', $formato],
            ['NOM-04', 'empieza con apóstrofo', "'Ana", $formato],
            ['NOM-04', 'empieza con punto', '.Ana', $formato],
            ['NOM-05', 'emoji', 'Ana 😀', $formato],
            ['NOM-06', '80 letras', str_repeat('a', 80), Esperado::acepta(str_repeat('a', 80))],
            ['NOM-06', '81 letras', str_repeat('a', 81), Esperado::rechaza($c['mensajes']['largo'] ?? null)],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function correo(array $c): array
    {
        $formato = Esperado::rechaza('Ingrese un correo electrónico válido, por ejemplo nombre@dominio.com.');
        $u = Fabrica::sufijo();
        $local64 = str_pad("c{$u}", 64, 'x');
        // 254 caracteres: 64 de la parte local, la arroba y un dominio de 189 con etiquetas de hasta 63
        $dominio189 = str_repeat('d', 63) . '.' . str_repeat('e', 63) . '.' . str_repeat('f', 48) . '.prueba.local';
        $c254 = $local64 . '@' . $dominio189;
        $c255 = $local64 . '@g' . $dominio189;
        assert(strlen($c254) === 254 && strlen($c255) === 255);

        return [
            ['COR-01', 'vacío', '', Esperado::rechaza($c['mensajes']['obligatorio'] ?? 'Complete el correo electrónico.')],
            ['COR-02', 'mayúsculas', "Ana.{$u}@Prueba.Local", Esperado::acepta(mb_strtolower("ana.{$u}@prueba.local"))],
            ['COR-03', 'espacios', " ana{$u} @prueba.local ", Esperado::acepta("ana{$u}@prueba.local")],
            ['COR-04', 'sin arroba', 'ana', $formato],
            ['COR-04', 'sin dominio', 'ana@', $formato],
            ['COR-04', 'sin parte local', '@prueba.local', $formato],
            ['COR-04', 'dos arrobas', 'ana@@prueba.local', $formato],
            ['COR-04', 'puntos seguidos en el dominio', 'ana@prueba..local', $formato],
            ['COR-05', 'dominio sin punto', 'ana@prueba', $formato],
            ['COR-06', 'caracteres no ASCII', 'ñandú@prueba.local', $formato],
            ['COR-07', '254 caracteres', $c254, Esperado::acepta($c254)],
            ['COR-07', '255 caracteres', $c255, $formato],
            ['COR-08', 'parte local de 65', str_pad("c{$u}", 65, 'x') . '@prueba.local', $formato],
        ];
    }

    /**
     * Número de documento (el tipo se prueba como lista, SEL).
     *
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function documento(array $c): array
    {
        $digitos = Esperado::rechaza('El documento debe tener entre 6 y 9 dígitos, sin puntos.');
        $dni = Fabrica::dni();
        $puntos = number_format((float) $dni, 0, '', '.');

        $casos = [
            ['DOC-03', 'vacío', '', Esperado::rechaza($c['mensajes']['obligatorio'] ?? 'Complete el número de documento.')],
            ['DOC-04', 'con puntos de miles', $puntos, Esperado::acepta((string) $dni)],
            ['DOC-05', '6 dígitos', (string) (100000 + Fabrica::entre(0, 99999)), Esperado::acepta(null)],
            ['DOC-05', '9 dígitos', (string) (100000000 + Fabrica::entre(0, 99999)), Esperado::acepta(null)],
            ['DOC-06', '5 dígitos', '10000', $digitos],
            ['DOC-06', '10 dígitos', '1000000000', $digitos],
            ['DOC-07', 'punto final', $dni . '.', $digitos],
            ['DOC-07', 'guiones', substr((string) $dni, 0, 2) . '-' . substr((string) $dni, 2, 3) . '-' . substr((string) $dni, 5), $digitos],
            ['DOC-07', 'espacios internos', substr((string) $dni, 0, 2) . ' ' . substr((string) $dni, 2), $digitos],
            ['DOC-07', 'letra', '28A99111', $digitos],
            ['DOC-08', 'en cero', '000000', Esperado::rechaza('El número de documento no es válido.')],
        ];
        if ($c['admite_pasaporte'] ?? false) {
            $pasaporte = 'aa' . Fabrica::entre(10000, 99999);
            $casos[] = ['DOC-09', 'pasaporte en minúsculas', $pasaporte, Esperado::acepta(strtoupper($pasaporte)), ['tipo_documento' => 'Pasaporte']];
            $casos[] = ['DOC-10', 'pasaporte de 4', 'AA12', Esperado::rechaza('El pasaporte debe tener entre 5 y 15 letras o números.'), ['tipo_documento' => 'Pasaporte']];
            $casos[] = ['DOC-10', 'pasaporte de 16', 'AA12345678901234', Esperado::rechaza('El pasaporte debe tener entre 5 y 15 letras o números.'), ['tipo_documento' => 'Pasaporte']];
            $casos[] = ['DOC-10', 'pasaporte con guion', 'AA-12345', Esperado::rechaza('El pasaporte debe tener entre 5 y 15 letras o números.'), ['tipo_documento' => 'Pasaporte']];
        }

        return $casos;
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function cuit(array $c): array
    {
        $juridica = ($c['persona'] ?? 'PersonaHumana') === 'PersonaJuridica';
        $prefijo = $juridica ? '30' : '20';
        $n = Fabrica::cuitValido($prefijo);
        $cuerpo = substr($n, 0, 10);
        $dv = (int) $n[10];
        $otroDv = ($dv + 1) % 10;
        $sinDv = Fabrica::numeroSinVerificador($prefijo);
        $prefijoAjeno = $juridica ? Fabrica::cuitValido('20') : Fabrica::cuitValido('30');
        // Un número distinto en cada clase aceptada: el CUIT es único entre los clientes (CUI-07)
        [$g, $e, $p] = [Fabrica::cuitValido($prefijo), Fabrica::cuitValido($prefijo), Fabrica::cuitValido($prefijo)];

        return [
            ['CUI-01', 'con guiones', substr($g, 0, 2) . '-' . substr($g, 2, 8) . '-' . $g[10], Esperado::acepta($g)],
            ['CUI-01', 'con espacios', substr($e, 0, 2) . ' ' . substr($e, 2, 8) . ' ' . $e[10], Esperado::acepta($e)],
            ['CUI-01', 'con puntos', substr($p, 0, 2) . '.' . substr($p, 2, 8) . '.' . $p[10], Esperado::acepta($p)],
            ['CUI-02', '10 dígitos', substr($n, 0, 10), Esperado::rechaza('El CUIT o CUIL debe tener 11 dígitos.')],
            ['CUI-02', '12 dígitos', $n . '0', Esperado::rechaza('El CUIT o CUIL debe tener 11 dígitos.')],
            ['CUI-02', 'con letra', substr($n, 0, 2) . 'A' . substr($n, 3), Esperado::rechaza('El CUIT o CUIL debe tener 11 dígitos.')],
            ['CUI-03', 'prefijo de otro tipo de persona', $prefijoAjeno, Esperado::rechaza($juridica
                ? 'El CUIT de una persona jurídica empieza con 30, 33 o 34.'
                : 'El CUIL o CUIT de una persona humana empieza con 20, 23, 24 o 27.')],
            ['CUI-04', 'verificador incorrecto', $cuerpo . $otroDv, Esperado::rechaza(
                'El dígito verificador no corresponde: para ' . $prefijo . '-' . substr($n, 2, 8) . " el último dígito es {$dv}.",
            )],
            ['CUI-05', 'combinación sin verificador', $sinDv . '0', Esperado::rechaza(
                "Con el prefijo {$prefijo} ese número no tiene un CUIT o CUIL válido: verifique el prefijo asignado (por ejemplo, 23 o 33).",
            )],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function importe(array $c): array
    {
        $formato = Esperado::rechaza($c['mensajes']['formato'] ?? null);
        $cero = isset($c['mensajes']['cero']) ? Esperado::rechaza($c['mensajes']['cero']) : Esperado::acepta('0.00');

        return [
            ['IMP-01', 'vacío', '', self::vacio($c)],
            ['IMP-02', 'puntos y coma', '1.234.567,89', Esperado::acepta('1234567.89')],
            ['IMP-02', 'solo coma', '1234567,89', Esperado::acepta('1234567.89')],
            ['IMP-02', 'solo punto', '1234567.89', Esperado::acepta('1234567.89')],
            ['IMP-02', 'con signo de pesos', '$ 1.234.567,89', Esperado::acepta('1234567.89')],
            ['IMP-03', 'un decimal con coma', '12,5', Esperado::acepta('12.50')],
            ['IMP-03', 'un decimal con punto', '1.5', Esperado::acepta('1.50')],
            ['IMP-04', 'punto de miles sin decimales', '1.234', Esperado::acepta('1234.00')],
            ['IMP-05', 'cero', '0', $cero],
            ['IMP-05', 'cero con decimales', '0,00', $cero],
            ['IMP-06', 'negativo', '-100', $formato],
            ['IMP-07', 'tres decimales con coma', '1,234', $formato],
            ['IMP-07', 'cuatro decimales', '1.2345', $formato],
            ['IMP-08', '17 dígitos', '99999999999999999', Esperado::acepta('99999999999999999.00')],
            ['IMP-08', '18 dígitos', '999999999999999999', $formato],
            ['IMP-09', 'notación científica', '1e5', $formato],
            ['IMP-09', 'letras', 'abc', $formato],
            ['IMP-09', 'dos comas', '1.000,00,00', $formato],
        ];
    }

    /**
     * @param array<string, mixed> $c límites: «maximo» (porcentaje), «maximo_incluido» (bool), «cero» (mensaje si se
     *                                rechaza el cero)
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function porcentaje(array $c): array
    {
        $formato = Esperado::rechaza($c['mensajes']['formato'] ?? null);
        $cero = isset($c['mensajes']['cero']) ? Esperado::rechaza($c['mensajes']['cero']) : Esperado::acepta('0.000000');
        $casos = [
            ['PCT-01', 'vacío', '', self::vacio($c)],
            ['PCT-02', 'entero', '6', Esperado::acepta('0.060000')],
            ['PCT-02', 'coma decimal', '6,5', Esperado::acepta('0.065000')],
            ['PCT-02', 'punto decimal', '6.5', Esperado::acepta('0.065000')],
            ['PCT-02', 'con signo %', '6,5 %', Esperado::acepta('0.065000')],
            ['PCT-03', 'cero', '0', $cero],
            ['PCT-04', 'cuatro decimales', '2,1234', Esperado::acepta('0.021234')],
            ['PCT-04', 'cinco decimales', '2,12345', $formato],
            ['PCT-05', 'cuatro enteros', '1000', $formato],
            ['PCT-05', 'negativo', '-5', $formato],
            ['PCT-05', 'letras', 'abc', $formato],
            ['PCT-05', 'dos comas', '1,5,5', $formato],
        ];
        $max = (string) $c['maximo'];
        $incluido = (bool) ($c['maximo_incluido'] ?? true);
        $fuera = Esperado::rechaza($c['mensajes']['maximo'] ?? null);
        $casos[] = ['PCT-06', "límite {$max}", str_replace('.', ',', $max), $incluido ? Esperado::acepta(null) : $fuera];
        $casos[] = ['PCT-06', 'límite + 0,0001', str_replace('.', ',', bcadd($max, '0.0001', 4)), $fuera];

        return $casos;
    }

    /**
     * @param array<string, mixed> $c límites «minimo» y «maximo» (null: sin límite) y mensajes «formato», «minimo»,
     *                                «maximo»
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function entero(array $c): array
    {
        $formato = Esperado::rechaza($c['mensajes']['formato'] ?? null);
        $min = $c['minimo'] ?? null;
        $max = $c['maximo'] ?? null;
        $casos = [
            ['ENT-01', 'vacío', '', self::vacio($c)],
            ['ENT-03', 'decimal con punto', '12.5', $formato],
            ['ENT-03', 'decimal con coma', '12,5', $formato],
            ['ENT-03', 'negativo', '-1', $formato],
            ['ENT-03', 'letras', 'abc', $formato],
            ['ENT-03', 'notación científica', '1e2', $formato],
            ['ENT-03', 'cinco dígitos', '12345', $formato],
            ['ENT-04', 'con espacios', ' ' . ($c['normal'] ?? '12') . ' ', Esperado::acepta((string) (int) ($c['normal'] ?? '12'))],
        ];
        if ($min !== null) {
            $casos[] = ['ENT-02', "mínimo {$min}", (string) $min, Esperado::acepta((string) $min)];
            if ($min > 0) {
                $casos[] = ['ENT-02', 'mínimo − 1', (string) ($min - 1), Esperado::rechaza($c['mensajes']['minimo'] ?? $c['mensajes']['formato'] ?? null)];
            }
        }
        if ($max !== null) {
            $casos[] = ['ENT-02', "máximo {$max}", (string) $max, Esperado::acepta((string) $max)];
            $casos[] = ['ENT-02', 'máximo + 1', (string) ($max + 1), Esperado::rechaza($c['mensajes']['maximo'] ?? $c['mensajes']['formato'] ?? null)];
        }

        return $casos;
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function fecha(array $c): array
    {
        $formato = Esperado::rechaza($c['mensajes']['formato'] ?? 'Ingrese una fecha válida (dd/mm/aaaa).');
        $anio = (int) ($c['anio_valido'] ?? 2020);
        $casos = [
            ['FEC-01', 'vacía', '', self::vacio($c)],
            ['FEC-02', 'dd/mm/aaaa', "31/12/{$anio}", Esperado::acepta("{$anio}-12-31")],
            ['FEC-02', 'd/m/aaaa', "1/2/{$anio}", Esperado::acepta(sprintf('%04d-02-01', $anio))],
            ['FEC-02', 'aaaa-mm-dd', "{$anio}-12-31", Esperado::acepta("{$anio}-12-31")],
            ['FEC-04', '29 de febrero de un año no bisiesto', '29/02/2025', $formato],
            ['FEC-04', '30 de febrero', '30/02/2024', $formato],
            ['FEC-04', '31 de abril', '31/04/2024', $formato],
            ['FEC-04', 'día cero', '00/01/2024', $formato],
            ['FEC-04', 'mes 13', '01/13/2024', $formato],
            ['FEC-06', 'barras invertidas', '2024/12/31', $formato],
            ['FEC-06', 'guiones en dd-mm-aaaa', '31-12-2024', $formato],
            ['FEC-06', 'año de dos dígitos', '31/12/24', $formato],
            ['FEC-06', 'texto', 'hoy', $formato],
        ];
        if ($c['admite_pasado'] ?? true) {
            $casos[] = ['FEC-03', '29 de febrero de un año bisiesto', '29/02/2000', Esperado::acepta('2000-02-29')];
            $casos[] = ['FEC-05', '1 de enero de 1900', '01/01/1900', Esperado::acepta('1900-01-01')];
        }
        $casos[] = ['FEC-05', '31 de diciembre de 1899', '31/12/1899', $formato];

        return $casos;
    }

    /**
     * @param array<string, mixed> $c «valores»: los admitidos; «guardado»: valor guardado de cada uno (por defecto, el mismo)
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function lista(array $c): array
    {
        $casos = [];
        foreach ($c['valores'] as $v) {
            $casos[] = ['SEL-01', "valor {$v}", $v, Esperado::acepta($c['guardado'][$v] ?? $v)];
        }
        $fuera = isset($c['fuera_se_toma_como'])
            ? Esperado::acepta($c['fuera_se_toma_como'])
            : Esperado::rechaza($c['mensajes']['formato'] ?? null);
        $casos[] = ['SEL-02', 'vacío', '', self::vacio($c)];
        $casos[] = ['SEL-03', 'valor inexistente', 'Inexistente', $fuera];
        $primero = (string) $c['valores'][0];
        $otraCaja = mb_strtolower($primero) !== $primero ? mb_strtolower($primero) : mb_strtoupper($primero);
        $casos[] = ['SEL-03', 'otras mayúsculas', $otraCaja, $fuera];
        $casos[] = ['SEL-04', 'espacios en los extremos', " {$primero} ", Esperado::acepta($c['guardado'][$primero] ?? $primero)];

        return $casos;
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function casilla(array $c): array
    {
        $falso = isset($c['mensajes']['obligatorio']) ? Esperado::rechaza($c['mensajes']['obligatorio']) : Esperado::acepta('0');

        return [
            ['CHK-01', 'marcada', '1', Esperado::acepta('1')],
            ['CHK-02', 'en cero', '0', $falso],
            ['CHK-02', 'vacía', '', $falso],
            ['CHK-03', 'otro valor', 'si', Esperado::acepta('1')],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function telefono(array $c): array
    {
        $mal = Esperado::rechaza('Ingrese un teléfono válido (solo números, espacios, guiones o +).');

        return [
            ['TEL-01', 'vacío', '', Esperado::acepta(null)],
            ['TEL-02', 'con código de área', '+54 (376) 444-1234', Esperado::acepta('+54 (376) 444-1234')],
            ['TEL-02', 'solo dígitos', '3764441234', Esperado::acepta('3764441234')],
            ['TEL-03', '5 caracteres', '12345', $mal],
            ['TEL-03', '31 caracteres', str_repeat('1', 31), $mal],
            ['TEL-04', 'letras', 'abc123456', $mal],
            ['TEL-04', 'sin dígitos', '------', $mal],
            ['TEL-04', 'letra entre dígitos', '+54 376 444 12x4', $mal],
            ['TEL-04', 'menos de 6 dígitos', '(1) 2-3', $mal],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function codigoPostal(array $c): array
    {
        $mal = Esperado::rechaza('Ingrese un código postal de 4 dígitos o el CPA de 8 caracteres (por ejemplo, N3350ABC).');

        return [
            ['CPO-01', 'vacío', '', Esperado::acepta(null)],
            ['CPO-02', 'cuatro dígitos', '3300', Esperado::acepta('3300')],
            ['CPO-02', 'CPA', 'N3350ABC', Esperado::acepta('N3350ABC')],
            ['CPO-02', 'CPA en minúsculas', 'n3350abc', Esperado::acepta('N3350ABC')],
            ['CPO-02', 'CPA con espacios', 'N 3350 ABC', Esperado::acepta('N3350ABC')],
            ['CPO-02', 'CPA con guion', 'N3350-ABC', Esperado::acepta('N3350ABC')],
            ['CPO-03', 'tres dígitos', '330', $mal],
            ['CPO-03', 'cinco dígitos', '33000', $mal],
            ['CPO-03', 'letra I', 'I3350ABC', $mal],
            ['CPO-03', 'letra O', 'O3350ABC', $mal],
            ['CPO-03', 'CPA de 7', 'N3350AB', $mal],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function motivo(array $c): array
    {
        $corto = Esperado::rechaza('Indique el motivo de la operación (al menos 5 caracteres).');

        return [
            ['MOT-01', 'vacío', '', $corto],
            ['MOT-01', 'solo espacios', '     ', $corto],
            ['MOT-01', '4 caracteres', 'Baja', $corto],
            ['MOT-02', 'espacios internos', 'a    b', $corto],
            ['MOT-03', '5 caracteres', 'Bajas', Esperado::acepta('Bajas')],
            ['MOT-03', '1000 caracteres', str_repeat('m', 1000), Esperado::acepta(str_repeat('m', 1000))],
            ['MOT-03', '1001 caracteres', str_repeat('m', 1001), Esperado::rechaza('El motivo admite hasta 1000 caracteres.')],
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return list<array{0: string, 1: string, 2: mixed, 3: Esperado}>
     */
    private static function codigoLinea(array $c): array
    {
        $mal = Esperado::rechaza('La identificación admite de 2 a 20 letras, números, guiones o guiones bajos.');
        $u = strtoupper(substr(Fabrica::sufijo(), -6));

        return [
            ['COD-01', 'vacío', '', Esperado::rechaza('Complete la identificación de la línea.')],
            ['COD-02', 'minúsculas', "p{$u}-01", Esperado::acepta("P{$u}-01")],
            ['COD-02', 'espacio', "Q{$u} 01", Esperado::acepta("Q{$u}-01")],
            ['COD-03', '2 caracteres', substr($u, 0, 2), Esperado::acepta(substr($u, 0, 2))],
            ['COD-03', '20 caracteres', str_pad("R{$u}", 20, '9'), Esperado::acepta(str_pad("R{$u}", 20, '9'))],
            ['COD-03', '1 carácter', 'P', $mal],
            ['COD-03', '21 caracteres', str_pad("S{$u}", 21, '9'), $mal],
            ['COD-04', 'empieza con guion bajo', '_PER', $mal],
            ['COD-04', 'empieza con guion', '-PER', $mal],
            ['COD-04', 'tilde', 'PÉR', $mal],
            ['COD-04', 'punto', 'PER.01', $mal],
        ];
    }

    /** Texto de $n caracteres con tildes, eñe y diéresis (una unidad cada uno en NVARCHAR). */
    private static function multibyte(int $n): string
    {
        $base = 'ñáéíóúüÑ';

        return mb_substr(str_repeat($base, intdiv($n, mb_strlen($base)) + 1), 0, $n);
    }
}
