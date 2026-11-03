<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;

/**
 * Categorías del monotributo (D-26). El tope de cada categoría es de ingresos brutos anuales; los ingresos que se
 * declaran en el SIG-CCR son netos y mensuales, así que doce veces los ingresos declarados no pueden superar el tope de
 * la categoría informada: si lo superan, la categoría o los ingresos son inconsistentes.
 */
final class CategoriasMonotributo
{
    public const CATEGORIAS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];

    /**
     * @param array<string, mixed> $topes categoría => importe en formato local
     * @return array{errores: array<string, string>, topes: array<string, string>}
     */
    public static function validarEscala(array $topes): array
    {
        $e = [];
        $r = [];
        $anterior = null;
        foreach (self::CATEGORIAS as $c) {
            $t = Campos::importe($topes[$c] ?? '');
            if ($t === null || !Decimal::of($t)->isPositive()) {
                $e["tope_{$c}"] = "Ingrese el tope de la categoría {$c}.";
            } elseif ($anterior !== null && Decimal::of($t)->compare($anterior) <= 0) {
                $e["tope_{$c}"] = "El tope de la categoría {$c} debe ser mayor que el de la anterior.";
            }
            if ($t !== null) {
                $r[$c] = $t;
                $anterior = $t;
            }
        }

        return ['errores' => $e, 'topes' => $r];
    }

    /**
     * Error de consistencia entre la categoría y los ingresos declarados, o null si son consistentes.
     *
     * @param array<string, array{tope: string, vigente_desde: string}> $vigentes MonotributoService::vigentes()
     */
    public static function inconsistencia(string $categoria, string $ingresosMensuales, array $vigentes): ?string
    {
        if (!isset($vigentes[$categoria])) {
            return 'Seleccione una categoría del monotributo vigente.';
        }
        $anual = Decimal::of($ingresosMensuales)->mul(12);
        $tope = Decimal::of($vigentes[$categoria]['tope']);
        if ($anual->compare($tope) <= 0) {
            return null;
        }

        return 'Doce meses de los ingresos declarados ($ ' . self::miles($anual->toFixed(2)) . ') superan el tope de la categoría '
            . $categoria . ' ($ ' . self::miles($tope->toFixed(2)) . '): revise la categoría o los ingresos (D-26).';
    }

    private static function miles(string $importe): string
    {
        [$e, $c] = explode('.', $importe);

        return strrev(implode('.', str_split(strrev($e), 3))) . ',' . $c;
    }
}
