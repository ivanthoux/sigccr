<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;

/**
 * Composición de un cobro en ventanilla con uno o más medios de pago (RN-29, D-11).
 *
 * El Cajero informa el importe a cobrar (que puede ser una fracción del total exigible, D-12) y lo que entrega el
 * cliente con cada medio. Los medios distintos del efectivo se aplican por su importe exacto, con número de operación,
 * y no generan cambio. El efectivo cubre el resto: lo que exceda ese resto es cambio. Se admite como máximo una línea
 * de efectivo; las demás pueden repetirse (por ejemplo, dos transferencias) con números de operación distintos.
 */
final class MediosCobro
{
    public const MEDIOS = [
        'Efectivo' => 'Efectivo',
        'TarjetaDebito' => 'Tarjeta de débito',
        'TarjetaCredito' => 'Tarjeta de crédito',
        'Transferencia' => 'Transferencia',
    ];

    /**
     * @param list<array{medio: string, importe: string, numero_operacion?: ?string}> $medios lo entregado con cada medio
     * @param Decimal $importeACobrar importe que se imputará a las cuotas (mayor que cero y no mayor que el total exigible)
     *
     * @return array{
     *     errores: list<string>,
     *     medios: list<array{medio: string, importe: Decimal, recibido: Decimal, numero_operacion: ?string}>,
     *     cambio: Decimal
     * } en medios, importe es lo aplicado al cobro y recibido lo entregado por el cliente
     */
    public static function componer(array $medios, Decimal $importeACobrar): array
    {
        $errores = [];
        $resultado = [];
        $efectivo = null;
        $noEfectivo = Decimal::zero();
        $operaciones = [];

        if ($medios === []) {
            $errores[] = 'Indique al menos un medio de pago.';
        }
        foreach ($medios as $n => $m) {
            $fila = $n + 1;
            $medio = $m['medio'];
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
            if ($medio === 'Efectivo') {
                if ($efectivo !== null) {
                    $errores[] = 'El efectivo se informa en una sola línea, por el total entregado.';
                    continue;
                }
                $efectivo = $importe;
                continue;
            }
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
            $noEfectivo = $noEfectivo->add($importe);
            $resultado[] = ['medio' => $medio, 'importe' => $importe, 'recibido' => $importe, 'numero_operacion' => $operacion];
        }

        if (!$importeACobrar->isPositive()) {
            $errores[] = 'El importe a cobrar debe ser mayor que cero.';
        }
        if ($errores !== []) {
            return ['errores' => $errores, 'medios' => [], 'cambio' => Decimal::zero()];
        }

        if ($noEfectivo->compare($importeACobrar) > 0) {
            $errores[] = 'Los medios distintos del efectivo suman $ ' . $noEfectivo->toFixed(2) . ' y superan el importe a cobrar: no generan cambio.';
        }
        $restoEnEfectivo = $importeACobrar->sub($noEfectivo);
        $cambio = Decimal::zero();
        if ($errores === [] && $restoEnEfectivo->isPositive()) {
            if ($efectivo === null || $efectivo->compare($restoEnEfectivo) < 0) {
                $faltante = $restoEnEfectivo->sub($efectivo ?? Decimal::zero());
                $errores[] = 'Lo entregado no alcanza el importe a cobrar: faltan $ ' . $faltante->toFixed(2) . '.';
            } else {
                $cambio = $efectivo->sub($restoEnEfectivo);
                array_unshift($resultado, ['medio' => 'Efectivo', 'importe' => $restoEnEfectivo, 'recibido' => $efectivo, 'numero_operacion' => null]);
            }
        } elseif ($errores === [] && $efectivo !== null) {
            $errores[] = 'Los demás medios ya cubren el importe a cobrar: quite el efectivo.';
        }

        if ($errores !== []) {
            return ['errores' => $errores, 'medios' => [], 'cambio' => Decimal::zero()];
        }

        return ['errores' => [], 'medios' => $resultado, 'cambio' => $cambio];
    }
}
