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

    /** Documentación obligatoria para identificar al cliente en el alta (D-14). */
    public const IDENTIFICACION = [
        'PersonaHumana' => ['DOC_IDENTIDAD'],
        'PersonaJuridica' => ['CONST_CUIT', 'DOC_IDENTIDAD', 'PODER_REPRESENTACION'],
    ];

    private const SUGERIDOS_POR_CATEGORIA = [
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

    /** @return list<string> documentación base sugerida para la categoría; vacío si la categoría no existe */
    public static function sugeridos(string $categoria): array
    {
        return self::SUGERIDOS_POR_CATEGORIA[$categoria] ?? [];
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
