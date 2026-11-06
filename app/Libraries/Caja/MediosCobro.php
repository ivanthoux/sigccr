<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;

/**
 * Composición de un cobro en ventanilla con uno o más medios de pago bancarios (RN-29, RN-77, D-11, D-30).
 *
 * El Cajero informa el importe a cobrar (que puede ser una fracción del total exigible, D-12) y el importe y el número
 * de operación de cada medio. No se recibe efectivo y no hay cambio: la suma de los medios debe ser igual al importe a
 * cobrar. Un medio puede repetirse (por ejemplo, dos transferencias) con números de operación distintos.
 */
final class MediosCobro
{
    public const MEDIOS = ReglasCaja::MEDIOS_VENTANILLA;

    /**
     * @param list<array{medio: string, importe: string, numero_operacion?: ?string}> $medios
     * @param Decimal $importeACobrar importe que se imputará a las cuotas (mayor que cero y no mayor que el total exigible)
     *
     * @return array{errores: list<string>, medios: list<array{medio: string, importe: Decimal, recibido: Decimal, numero_operacion: string}>}
     */
    public static function componer(array $medios, Decimal $importeACobrar): array
    {
        $errores = [];
        $resultado = [];
        $suma = Decimal::zero();
        $operaciones = [];

        if ($medios === []) {
            $errores[] = 'Indique al menos un medio de pago.';
        }
        foreach ($medios as $n => $m) {
            $fila = $n + 1;
            $medio = $m['medio'];
            if ($medio === 'Efectivo') {
                $errores[] = "Medio {$fila}: no se reciben pagos en efectivo; el cliente paga por transferencia, depósito o tarjeta (RN-77).";
                continue;
            }
            if (!isset(self::MEDIOS[$medio])) {
                $errores[] = "Medio {$fila}: seleccione un medio de pago válido.";
                continue;
            }
            $importe = Decimal::of($m['importe']);
            if (!$importe->isPositive()) {
                $errores[] = "Medio {$fila}: el importe debe ser mayor que cero.";
                continue;
            }
            $operacion = trim((string) ($m['numero_operacion'] ?? ''));
            if ($operacion === '') {
                $errores[] = "Medio {$fila}: informe el número de operación o comprobante de " . mb_strtolower(self::MEDIOS[$medio]) . '.';
                continue;
            }
            $clave = $medio . '|' . $operacion;
            if (isset($operaciones[$clave])) {
                $errores[] = "Medio {$fila}: el número de operación {$operacion} está repetido.";
                continue;
            }
            $operaciones[$clave] = true;
            $suma = $suma->add($importe);
            $resultado[] = ['medio' => $medio, 'importe' => $importe, 'recibido' => $importe, 'numero_operacion' => $operacion];
        }

        if (!$importeACobrar->isPositive()) {
            $errores[] = 'El importe a cobrar debe ser mayor que cero.';
        }
        if ($errores === [] && $suma->compare($importeACobrar) !== 0) {
            $diferencia = $importeACobrar->sub($suma);
            $errores[] = $diferencia->isPositive()
                ? 'Los medios suman $ ' . $suma->toFixed(2) . ': faltan $ ' . $diferencia->toFixed(2) . ' para el importe a cobrar.'
                : 'Los medios suman $ ' . $suma->toFixed(2) . ' y superan el importe a cobrar en $ ' . $diferencia->neg()->toFixed(2) . '.';
        }

        return $errores === [] ? ['errores' => [], 'medios' => $resultado] : ['errores' => $errores, 'medios' => []];
    }
}
