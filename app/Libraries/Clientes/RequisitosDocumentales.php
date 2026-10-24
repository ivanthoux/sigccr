<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

/**
 * Documentación base exigida en el alta según la categoría de ingresos (RN-11, CU-11 A4). Cada línea de crédito
 * puede exigir documentación adicional al registrar la solicitud (CU-22). Los códigos son los de
 * tipos_documentacion (TiposDocumentacionSeeder).
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

    private const POR_CATEGORIA = [
        'RelacionDependencia' => ['DOC_IDENTIDAD', 'RECIBO_SUELDO'],
        'JubiladoPensionado' => ['DOC_IDENTIDAD', 'RECIBO_HABERES'],
        'Monotributista' => ['DOC_IDENTIDAD', 'CONST_MONOTRIBUTO', 'PAGOS_MONOTRIBUTO'],
        'ResponsableInscripto' => ['DOC_IDENTIDAD', 'CONST_INSCRIPCION', 'DDJJ_GANANCIAS'],
        // Para la empresa, DOC_IDENTIDAD es el documento de su representante
        'Empresa' => ['ESTATUTO', 'CONST_CUIT', 'ESTADOS_CONTABLES', 'PODER_REPRESENTACION', 'DOC_IDENTIDAD'],
    ];

    /** @return list<string> códigos exigidos para la categoría; vacío si la categoría no existe */
    public static function exigidos(string $categoria): array
    {
        return self::POR_CATEGORIA[$categoria] ?? [];
    }

    /**
     * @param list<string> $adjuntos códigos de los documentos adjuntados
     * @return list<string> códigos exigidos que faltan
     */
    public static function faltantes(string $categoria, array $adjuntos): array
    {
        return array_values(array_diff(self::exigidos($categoria), $adjuntos));
    }

    public static function categoriaValida(string $tipoCliente, string $categoria): bool
    {
        return isset(self::CATEGORIAS[$tipoCliente][$categoria]);
    }
}
