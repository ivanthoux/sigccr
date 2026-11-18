<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;

/**
 * Cajas, jornadas y movimientos de fondos (SS-05): validaciones de CU-80, CU-72, CU-36 y CU-85 y la conciliación de
 * RN-32. La caja no maneja efectivo (D-30, RN-77). Una asignación está vigente desde su fecha de inicio hasta su
 * fecha de fin inclusive (o sin fin).
 */
final class ReglasCaja
{
    public const TIPOS_FONDOS = [
        'AporteCapital' => 'Aporte de capital',
        'Financiamiento' => 'Financiamiento o fondeo',
        'GastoOperativo' => 'Gasto operativo',
        'Otro' => 'Otro',
    ];

    /**
     * CU-80 pasos 2 a 4.
     *
     * @param array<string, mixed> $d codigo, denominacion, ubicacion, observaciones
     * @return array{errores: array<string, string>, datos: array<string, ?string>}
     */
    public static function validarCaja(array $d): array
    {
        $e = [];
        $codigo = strtoupper(Campos::texto($d['codigo'] ?? ''));
        if (preg_match('/^[A-Z0-9-]{1,10}$/', $codigo) !== 1) {
            $e['codigo'] = 'Ingrese un código de hasta 10 letras, números o guiones (por ejemplo, CAJA-01).';
        }
        $denominacion = Campos::texto($d['denominacion'] ?? '');
        // H-16: largo en unidades de NVARCHAR (un emoji ocupa dos)
        if ($denominacion === '' || Campos::largo($denominacion) > 60) {
            $e['denominacion'] = 'Ingrese la denominación de la caja (hasta 60 caracteres).';
        }
        $ubicacion = Campos::texto($d['ubicacion'] ?? '');
        if (Campos::largo($ubicacion) > 100) {
            $e['ubicacion'] = 'La ubicación admite hasta 100 caracteres.';
        }
        $observaciones = Campos::texto($d['observaciones'] ?? '');
        if (Campos::largo($observaciones) > 300) {
            $e['observaciones'] = 'Las observaciones admiten hasta 300 caracteres.';
        }

        return ['errores' => $e, 'datos' => [
            'codigo' => $codigo, 'denominacion' => $denominacion,
            'ubicacion' => $ubicacion === '' ? null : $ubicacion, 'observaciones' => $observaciones === '' ? null : $observaciones,
        ]];
    }

    /**
     * CU-72 pasos 4 y 5 (A3): vigencia de la asignación. El inicio no puede ser anterior a hoy.
     *
     * @return array{errores: array<string, string>, desde: ?string, hasta: ?string}
     */
    public static function validarVigencia(string $desdeTexto, string $hastaTexto, string $hoy): array
    {
        $e = [];
        $desde = trim($desdeTexto) === '' ? $hoy : Campos::fecha($desdeTexto);
        if ($desde === null) {
            $e['desde'] = 'Ingrese una fecha de inicio válida (dd/mm/aaaa).';
        } elseif ($desde < $hoy) {
            $e['desde'] = 'La asignación no puede comenzar en una fecha pasada.';
        }
        $hasta = null;
        if (trim($hastaTexto) !== '') {
            $hasta = Campos::fecha($hastaTexto);
            if ($hasta === null) {
                $e['hasta'] = 'Ingrese una fecha de fin válida (dd/mm/aaaa) o déjela vacía.';
            } elseif ($desde !== null && $hasta < $desde) {
                $e['hasta'] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
        }

        return ['errores' => $e, 'desde' => $desde, 'hasta' => $hasta];
    }

    public static function asignacionVigente(string $desde, ?string $hasta, string $hoy): bool
    {
        return substr($desde, 0, 10) <= $hoy && ($hasta === null || substr($hasta, 0, 10) >= $hoy);
    }

    /** Medios con que se cobra en ventanilla y que el cierre concilia (RN-29, RN-77). */
    public const MEDIOS_VENTANILLA = [
        'Transferencia' => 'Transferencia o depósito',
        'TarjetaDebito' => 'Tarjeta de débito',
        'TarjetaCredito' => 'Tarjeta de crédito',
    ];

    /**
     * RN-32: total registrado por medio en los cobros de la jornada. Un cobro con varios medios aporta a cada uno el
     * importe aplicado con él. Los movimientos de jornadas anteriores a D-30 (fondo inicial, efectivo, desembolsos)
     * no se concilian.
     *
     * @param list<array{tipo: string, medio: string, importe: string}> $movimientos
     * @return array{por_medio: array<string, string>, cantidad: int, total: string}
     */
    public static function totalesPorMedio(array $movimientos): array
    {
        $porMedio = array_fill_keys(array_keys(self::MEDIOS_VENTANILLA), '0.00');
        $total = Decimal::zero();
        $cantidad = 0;
        foreach ($movimientos as $m) {
            if ($m['tipo'] !== 'Cobro' || !isset($porMedio[$m['medio']])) {
                continue;
            }
            $cantidad++;
            $porMedio[$m['medio']] = Decimal::of($porMedio[$m['medio']])->add($m['importe'])->toFixed(2);
            $total = $total->add($m['importe']);
        }

        return ['por_medio' => $porMedio, 'cantidad' => $cantidad, 'total' => $total->toFixed(2)];
    }

    /**
     * CU-36 pasos 4 a 8 (A2, A3): conciliación de cada medio. El total acreditado es obligatorio para los medios con
     * cobros registrados; para los demás, vacío equivale a cero. Toda diferencia exige una explicación.
     *
     * @param array<string, string> $registrado total registrado por medio (totalesPorMedio)
     * @param array<string, mixed> $acreditados texto ingresado por medio
     * @param array<string, mixed> $explicaciones texto ingresado por medio
     * @return array{errores: array<string, string>, conciliaciones: list<array{medio: string, total_registrado: string, total_acreditado: string, diferencia: string, explicacion: ?string}>}
     */
    public static function validarConciliacion(array $registrado, array $acreditados, array $explicaciones): array
    {
        $e = [];
        $filas = [];
        foreach (self::MEDIOS_VENTANILLA as $medio => $nombre) {
            $reg = $registrado[$medio] ?? '0.00';
            // H-15: un valor enviado como lista se toma como vacío
            $texto = Campos::texto($acreditados[$medio] ?? '');
            if ($texto === '' && Decimal::of($reg)->isZero()) {
                $acreditado = '0.00';
            } else {
                $acreditado = Campos::importe($texto);
                if ($acreditado === null) {
                    $e["acreditado_{$medio}"] = "Ingrese el total acreditado por {$nombre} según el extracto o el resumen del procesador (un importe no negativo).";
                    continue;
                }
            }
            $diferencia = Decimal::of($acreditado)->sub($reg)->toFixed(2);
            $explicacion = Campos::texto($explicaciones[$medio] ?? '');
            if (!Decimal::of($diferencia)->isZero() && Campos::largo($explicacion) < 10) {
                $e["explicacion_{$medio}"] = 'Hay una diferencia de ' . str_replace('.', ',', $diferencia) . ': explique su causa (al menos 10 caracteres).';
            } elseif (Campos::largo($explicacion) > 500) {
                $e["explicacion_{$medio}"] = 'La explicación admite hasta 500 caracteres.';
            }
            if (Decimal::of($reg)->isZero() && Decimal::of($acreditado)->isZero()) {
                continue;
            }
            $filas[] = [
                'medio' => $medio, 'total_registrado' => Decimal::of($reg)->toFixed(2), 'total_acreditado' => $acreditado,
                'diferencia' => $diferencia, 'explicacion' => $explicacion === '' ? null : $explicacion,
            ];
        }

        return ['errores' => $e, 'conciliaciones' => $e === [] ? $filas : []];
    }

    /**
     * CU-85 pasos 3 a 5: movimiento de fondos. Un movimiento realizado tiene fecha de hoy o anterior; uno programado,
     * fecha futura. Que un egreso realizado no supere el disponible libre lo verifica el servicio (A1).
     *
     * @param array<string, mixed> $d tipo, caracter, programado, importe, fecha, concepto, referencia
     * @return array{errores: array<string, string>, datos: array<string, mixed>}
     */
    public static function validarMovimiento(array $d, string $hoy): array
    {
        $e = [];
        // H-15: texto recortado; una lista llega vacía
        $tipo = Campos::texto($d['tipo'] ?? '');
        if (!isset(self::TIPOS_FONDOS[$tipo])) {
            $e['tipo'] = 'Seleccione el tipo de movimiento.';
        }
        $caracter = Campos::texto($d['caracter'] ?? '');
        if (!in_array($caracter, ['Ingreso', 'Egreso'], true)) {
            $e['caracter'] = 'Indique si es un ingreso o un egreso.';
        }
        $estado = !empty($d['programado']) ? 'Programado' : 'Realizado';
        $importe = Campos::importe($d['importe'] ?? '');
        if ($importe === null || !Decimal::of($importe)->isPositive()) {
            $e['importe'] = 'Ingrese un importe mayor que cero.';
        }
        $fecha = Campos::fecha($d['fecha'] ?? '');
        if ($fecha === null) {
            $e['fecha'] = 'Ingrese la fecha del movimiento (dd/mm/aaaa).';
        } elseif ($estado === 'Realizado' && $fecha > $hoy) {
            $e['fecha'] = 'Un movimiento realizado no puede tener fecha futura: márquelo como programado.';
        } elseif ($estado === 'Programado' && $fecha <= $hoy) {
            $e['fecha'] = 'Un movimiento programado debe tener una fecha futura.';
        }
        $concepto = Campos::texto($d['concepto'] ?? '');
        if ($concepto === '' || Campos::largo($concepto) > 200) {
            $e['concepto'] = 'Ingrese el concepto (hasta 200 caracteres).';
        }
        $referencia = Campos::texto($d['referencia'] ?? '');
        if (Campos::largo($referencia) > 100) {
            $e['referencia'] = 'La referencia admite hasta 100 caracteres.';
        }

        return ['errores' => $e, 'datos' => [
            'tipo' => $tipo, 'caracter' => $caracter, 'estado' => $estado, 'importe' => $importe, 'fecha' => $fecha,
            'concepto' => $concepto, 'referencia' => $referencia === '' ? null : $referencia,
        ]];
    }
}
