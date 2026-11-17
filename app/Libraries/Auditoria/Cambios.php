<?php

declare(strict_types=1);

namespace App\Libraries\Auditoria;

/**
 * Cambios campo a campo de una modificación, para el registro de auditoría (RN-45, D-48).
 *
 * Compara el estado anterior y el nuevo de un objeto y devuelve solo los campos que cambiaron, con su valor anterior
 * y su valor nuevo: ['campo' => ['anterior' => …, 'nuevo' => …]]. Se guarda en el detalle del registro bajo la clave
 * «cambios» y la consulta de auditoría (CU-57) lo presenta como tabla.
 *
 * - Los datos anidados (por ejemplo, los pesos del modelo de evaluación) se comparan hoja por hoja, con la ruta
 *   separada por puntos («pesos.situacion_bcra»). Las listas se comparan completas.
 * - Un valor vacío y null son iguales; los números se comparan por su valor («0.600000» = «0.6»), y los lógicos, como
 *   1 y 0.
 */
final class Cambios
{
    /** Nombres legibles de los campos más usados; el resto se deriva del nombre del campo. */
    private const ETIQUETAS = [
        'nombre' => 'Nombre', 'apellido' => 'Apellido', 'tipo_documento' => 'Tipo de documento', 'nro_documento' => 'Número de documento',
        'correo' => 'Correo electrónico', 'rol' => 'Rol', 'estado' => 'Estado', 'cuit_cuil' => 'CUIT/CUIL', 'denominacion' => 'Denominación',
        'telefono' => 'Teléfono', 'domicilio' => 'Calle y número', 'localidad' => 'Localidad', 'codigo_postal' => 'Código postal',
        'ingresos_declarados' => 'Ingresos declarados', 'categoria_ingresos' => 'Categoría de ingresos', 'categoria_monotributo' => 'Categoría del monotributo',
        'persona_expuesta' => 'Persona expuesta políticamente', 'comunicaciones_comerciales' => 'Acepta comunicaciones comerciales',
        'fecha_nacimiento' => 'Fecha de nacimiento', 'nacionalidad' => 'Nacionalidad', 'actividad_principal' => 'Actividad principal',
        'empleador' => 'Empleador', 'razon_social' => 'Razón social', 'fecha_constitucion' => 'Fecha de constitución',
        'representante_nombre' => 'Nombre del representante', 'representante_apellido' => 'Apellido del representante',
        'representante_documento' => 'Documento del representante',
        'descripcion' => 'Descripción', 'tna' => 'TNA', 'gasto_otorgamiento_pct' => 'Gasto de otorgamiento (%)',
        'tipo_gasto_administrativo' => 'Tipo de gasto administrativo', 'gasto_administrativo_cuota' => 'Gasto administrativo por cuota',
        'gasto_administrativo_pct' => 'Gasto administrativo (%)', 'monto_minimo' => 'Importe mínimo', 'monto_maximo' => 'Importe máximo',
        'cuotas_minimas' => 'Cuotas mínimas', 'cuotas_maximas' => 'Cuotas máximas', 'edad_minima' => 'Edad mínima', 'edad_maxima' => 'Edad máxima',
        'antiguedad_minima_meses' => 'Antigüedad mínima (meses)', 'admite_persona_humana' => 'Admite persona humana',
        'admite_persona_juridica' => 'Admite persona jurídica', 'vigencia_inicio' => 'Inicio de la vigencia', 'vigencia_fin' => 'Fin de la vigencia',
        'categorias' => 'Categorías de ingresos admitidas', 'version_condiciones' => 'Versión de condiciones', 'version' => 'Versión',
        'aplica_a' => 'Aplica a', 'es_clave' => 'Documento clave', 'activo' => 'Activo', 'cajero' => 'Cajero', 'asignacion' => 'Asignación',
        'hasta' => 'Hasta', 'desde' => 'Desde', 'fecha' => 'Fecha', 'motivo' => 'Motivo', 'tope' => 'Tope',
    ];

    /**
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     * @param list<string>|null $campos campos a comparar (rutas con puntos para los anidados); null compara todos
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    public static function entre(array $antes, array $despues, ?array $campos = null): array
    {
        $a = self::aplanar($antes);
        $d = self::aplanar($despues);
        $campos ??= array_values(array_unique([...array_keys($a), ...array_keys($d)]));
        $cambios = [];
        foreach ($campos as $campo) {
            $anterior = $a[$campo] ?? null;
            $nuevo = $d[$campo] ?? null;
            if (!self::iguales($anterior, $nuevo)) {
                $cambios[$campo] = ['anterior' => $anterior, 'nuevo' => $nuevo];
            }
        }

        return $cambios;
    }

    /** Un solo campo (por ejemplo, el estado): ['estado' => ['anterior' => …, 'nuevo' => …]], o vacío si no cambió. */
    public static function campo(string $campo, mixed $anterior, mixed $nuevo): array
    {
        return self::iguales($anterior, $nuevo) ? [] : [$campo => ['anterior' => $anterior, 'nuevo' => $nuevo]];
    }

    /**
     * Aplana los arreglos asociativos con rutas separadas por puntos; las listas quedan como un único valor.
     *
     * @param array<array-key, mixed> $datos
     * @return array<string, mixed>
     */
    public static function aplanar(array $datos, string $prefijo = ''): array
    {
        $plano = [];
        foreach ($datos as $k => $v) {
            $clave = $prefijo === '' ? (string) $k : "{$prefijo}.{$k}";
            if (is_array($v) && $v !== [] && !array_is_list($v)) {
                $plano += self::aplanar($v, $clave);
            } else {
                $plano[$clave] = $v;
            }
        }

        return $plano;
    }

    public static function iguales(mixed $a, mixed $b): bool
    {
        return self::normal($a) === self::normal($b);
    }

    public static function etiqueta(string $campo): string
    {
        if (isset(self::ETIQUETAS[$campo])) {
            return self::ETIQUETAS[$campo];
        }
        $partes = array_map(static fn (string $p): string => self::ETIQUETAS[$p] ?? str_replace('_', ' ', $p), explode('.', $campo));

        return mb_strtoupper(mb_substr($partes[0], 0, 1)) . mb_substr(implode(' › ', $partes), 1);
    }

    /** Valor para mostrar en la consulta: vacío como «—», lógicos como Sí/No y listas separadas por comas. */
    public static function mostrar(mixed $v): string
    {
        return match (true) {
            $v === null, $v === '', $v === [] => '—',
            is_bool($v) => $v ? 'Sí' : 'No',
            is_array($v) => array_is_list($v) && array_filter($v, 'is_array') === []
                ? implode(', ', array_map(static fn ($x): string => self::mostrar($x), $v))
                : (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $v,
        };
    }

    /**
     * Separa los cambios del resto del detalle de un registro, para la consulta (CU-57). Reconoce también los formatos
     * de los registros anteriores a D-48: campos con «anterior» y «nuevo», «estado_anterior» y «estado_nuevo», y los
     * pares «antes» y «despues».
     *
     * @param array<string, mixed>|null $detalle
     * @return array{cambios: array<string, array{anterior: mixed, nuevo: mixed}>, datos: array<string, mixed>}
     */
    public static function desdeDetalle(?array $detalle): array
    {
        $datos = $detalle ?? [];
        $cambios = is_array($datos['cambios'] ?? null) ? $datos['cambios'] : [];
        unset($datos['cambios']);
        if (array_key_exists('anterior', $datos) && array_key_exists('nuevo', $datos) && !is_array($datos['anterior'])) {
            $cambios += ['estado' => ['anterior' => $datos['anterior'], 'nuevo' => $datos['nuevo']]];
            unset($datos['anterior'], $datos['nuevo']);
        }
        if (array_key_exists('estado_anterior', $datos) || array_key_exists('estado_nuevo', $datos)) {
            $cambios += ['estado' => ['anterior' => $datos['estado_anterior'] ?? null, 'nuevo' => $datos['estado_nuevo'] ?? null]];
            unset($datos['estado_anterior'], $datos['estado_nuevo']);
        }
        if (is_array($datos['antes'] ?? null) && is_array($datos['despues'] ?? null)) {
            $cambios += self::entre($datos['antes'], $datos['despues'], array_keys($datos['despues']));
            unset($datos['antes'], $datos['despues']);
        }
        foreach ($datos as $k => $v) {
            if (is_array($v) && array_key_exists('anterior', $v) && array_key_exists('nuevo', $v)) {
                $cambios[(string) $k] = ['anterior' => $v['anterior'], 'nuevo' => $v['nuevo']];
                $resto = array_diff_key($v, ['anterior' => 1, 'nuevo' => 1]);
                if ($resto === []) {
                    unset($datos[$k]);
                } else {
                    $datos[$k] = $resto;
                }
            }
        }

        return ['cambios' => $cambios, 'datos' => $datos];
    }

    /** Forma comparable de un valor. */
    private static function normal(mixed $v): ?string
    {
        if ($v === null || $v === '' || $v === []) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_array($v)) {
            return (string) json_encode(array_map(static fn ($x) => self::normal($x), $v), JSON_UNESCAPED_UNICODE);
        }
        $s = trim((string) $v);
        if (preg_match('/^-?\d+(\.\d+)?$/', $s) === 1) {
            if (str_contains($s, '.')) {
                $s = rtrim(rtrim($s, '0'), '.');
            }
            // Cero con signo o con decimales («-0», «0.00») es cero; los ceros a la izquierda se conservan, porque un
            // documento o un código postal se comparan como texto
            $s = ltrim($s, '-0') === '' ? '0' : $s;
        }

        return $s;
    }
}
