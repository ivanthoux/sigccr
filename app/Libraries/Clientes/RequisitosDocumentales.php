<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

/**
 * Documentación del legajo (RN-11, D-14).
 *
 * - Identificación: lo único obligatorio en el alta del cliente (CU-11). Persona humana: documento de identidad.
 *   Persona jurídica: constancia de CUIT, documento del representante e instrumento que acredita la representación.
 * - Documentación sugerida por categoría de ingresos: la que el sistema propone al configurar una línea (CU-16). Cada
 *   versión de condiciones define la que exige, que puede ser solo el documento de identidad (por ejemplo, una línea
 *   «solo con DNI»), y se exige al registrar la solicitud (CU-22) y se verifica en CU-23.
 *
 * Los códigos son los de tipos_documentacion (TiposDocumentacionSeeder).
 */
final class RequisitosDocumentales
{
    public const CATEGORIAS = [
        'PersonaHumana' => [
            'RelacionDependencia' => 'Relación de dependencia',
            'JubiladoPensionado' => 'Jubilado o pensionado',
            'Monotributista' => 'Monotributista',
            'ResponsableInscripto' => 'Responsable inscripto',
        ],
        'PersonaJuridica' => ['Empresa' => 'Empresa'],
    ];

    /**
     * Declaración jurada sobre la condición de persona expuesta políticamente, firmada por el cliente (RN-80, Resolución
     * UIF 35/2023). Se puede incorporar en el alta o después, y se exige en el legajo para liquidar un crédito (D-55).
     */
    public const DECLARACION_PEP = 'DDJJ_PEP';

    /** Documentación obligatoria para identificar al cliente en el alta (D-14). */
    public const IDENTIFICACION = [
        'PersonaHumana' => ['DOC_IDENTIDAD'],
        'PersonaJuridica' => ['CONST_CUIT', 'DOC_IDENTIDAD', 'PODER_REPRESENTACION'],
    ];

    /**
     * Documentación base inicial por categoría. Desde D-25 la configurable está en documentacion_sugerida
     * (DocumentacionService); esta lista la carga la instalación.
     */
    public const SUGERIDOS_INICIALES = [
        'RelacionDependencia' => ['DOC_IDENTIDAD', 'RECIBO_SUELDO'],
        'JubiladoPensionado' => ['DOC_IDENTIDAD', 'RECIBO_HABERES'],
        'Monotributista' => ['DOC_IDENTIDAD', 'CONST_MONOTRIBUTO', 'PAGOS_MONOTRIBUTO'],
        'ResponsableInscripto' => ['DOC_IDENTIDAD', 'CONST_INSCRIPCION', 'DDJJ_GANANCIAS'],
        // Para la empresa, DOC_IDENTIDAD es el documento de su representante
        'Empresa' => ['ESTATUTO', 'CONST_CUIT', 'ESTADOS_CONTABLES', 'PODER_REPRESENTACION', 'DOC_IDENTIDAD'],
    ];

    /** @return list<string> documentación obligatoria del alta para el tipo de cliente */
    public static function identificacion(string $tipoCliente): array
    {
        return self::IDENTIFICACION[$tipoCliente] ?? self::IDENTIFICACION['PersonaHumana'];
    }

    /** @return list<string> documentación base inicial de la categoría; vacío si la categoría no existe */
    public static function sugeridosIniciales(string $categoria): array
    {
        return self::SUGERIDOS_INICIALES[$categoria] ?? [];
    }

    /** Tipo de cliente de una categoría de ingresos. */
    public static function tipoDeCategoria(string $categoria): ?string
    {
        foreach (self::CATEGORIAS as $tipo => $cats) {
            if (isset($cats[$categoria])) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * Validación de la documentación sugerida por categoría (D-25): cada categoría incluye la identificación de su
     * tipo de cliente (D-14) y solo tipos de documento activos que le correspondan.
     *
     * @param array<string, list<string>> $porCategoria códigos marcados por categoría
     * @param array<string, string>       $aplicaA      código => PersonaHumana | PersonaJuridica | Ambos (tipos activos)
     * @return array<string, string> errores por categoría
     */
    public static function validarSugeridos(array $porCategoria, array $aplicaA): array
    {
        $e = [];
        foreach (self::CATEGORIAS as $tipo => $cats) {
            foreach ($cats as $cat => $nombre) {
                $codigos = array_values(array_unique($porCategoria[$cat] ?? []));
                $ajenos = array_filter($codigos, static fn (string $c): bool => !isset($aplicaA[$c]) || !in_array($aplicaA[$c], ['Ambos', $tipo], true));
                $faltan = self::faltantes(self::identificacion($tipo), $codigos);
                if ($ajenos !== []) {
                    $e[$cat] = "{$nombre}: hay documentos inactivos o que no corresponden a este tipo de cliente.";
                } elseif ($faltan !== []) {
                    $e[$cat] = "{$nombre}: debe incluir la documentación de identificación del cliente (D-14).";
                }
            }
        }

        return $e;
    }

    /** Código de un tipo de documento nuevo a partir de su nombre: mayúsculas sin acentos y guiones bajos, hasta 30. */
    public static function codigoPara(string $nombre): string
    {
        $t = strtr(mb_strtoupper($nombre), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $t = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $t), '_');

        return substr($t === '' ? 'DOC' : $t, 0, 30);
    }

    /**
     * @param list<string> $exigidos códigos exigidos (identificación del alta o requisitos de la versión de condiciones)
     * @param list<string> $adjuntos códigos de los documentos presentes en el legajo
     * @return list<string> códigos exigidos que faltan
     */
    public static function faltantes(array $exigidos, array $adjuntos): array
    {
        return array_values(array_diff($exigidos, $adjuntos));
    }

    /**
     * Requisitos de una versión de condiciones para la categoría del cliente (CU-22, CU-23). Las filas sin categoría
     * aplican a todas las categorías.
     *
     * @param list<array{codigo: string, categoria_ingresos: ?string, obligatorio?: bool|int}> $requisitosVersion
     * @return list<string> códigos obligatorios
     */
    public static function exigidosPorLinea(array $requisitosVersion, string $categoria): array
    {
        $r = [];
        foreach ($requisitosVersion as $q) {
            if (($q['categoria_ingresos'] === null || $q['categoria_ingresos'] === $categoria) && (bool) ($q['obligatorio'] ?? true)) {
                $r[] = $q['codigo'];
            }
        }

        return array_values(array_unique($r));
    }

    public static function categoriaValida(string $tipoCliente, string $categoria): bool
    {
        return isset(self::CATEGORIAS[$tipoCliente][$categoria]);
    }
}
