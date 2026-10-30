<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;

/**
 * Cajas, jornadas y movimientos de fondos (SS-05): validaciones de CU-80, CU-72, CU-31, CU-36 y CU-85 y el arqueo de
 * RN-32. Una asignación está vigente desde su fecha de inicio hasta su fecha de fin inclusive (o sin fin).
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
        if ($denominacion === '' || mb_strlen($denominacion) > 60) {
            $e['denominacion'] = 'Ingrese la denominación de la caja (hasta 60 caracteres).';
        }
        $ubicacion = Campos::texto($d['ubicacion'] ?? '');
        if (mb_strlen($ubicacion) > 100) {
            $e['ubicacion'] = 'La ubicación admite hasta 100 caracteres.';
        }
        $observaciones = Campos::texto($d['observaciones'] ?? '');
        if (mb_strlen($observaciones) > 300) {
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

    /** CU-31 paso 5 y CU-36 paso 6 (A3): importe no negativo. */
    public static function importeNoNegativo(string $texto): ?string
    {
        return Campos::importe($texto);
    }

    /**
     * RN-32: resumen de la jornada. Los cobros aportan a cada medio el importe aplicado con él; solo el efectivo
     * integra el arqueo. Efectivo esperado = fondo inicial + cobros en efectivo − desembolsos en efectivo.
     *
     * @param list<array{tipo: string, medio: string, importe: string}> $movimientos
     * @return array{cobros_por_medio: array<string, string>, cobros_efectivo: string, desembolsos: string, cantidad: int, esperado: string}
     */
    public static function arqueo(string $fondoInicial, array $movimientos): array
    {
        $porMedio = [];
        $efectivo = Decimal::zero();
        $desembolsos = Decimal::zero();
        $cantidad = 0;
        foreach ($movimientos as $m) {
            $importe = Decimal::of($m['importe']);
            if ($m['tipo'] === 'Cobro') {
                $cantidad++;
                $porMedio[$m['medio']] = Decimal::of($porMedio[$m['medio']] ?? '0')->add($importe)->toFixed(2);
                if ($m['medio'] === 'Efectivo') {
                    $efectivo = $efectivo->add($importe);
                }
            } elseif ($m['tipo'] === 'Desembolso') {
                $cantidad++;
                $desembolsos = $desembolsos->add($importe);
            }
        }

        return [
            'cobros_por_medio' => $porMedio,
            'cobros_efectivo' => $efectivo->toFixed(2),
            'desembolsos' => $desembolsos->toFixed(2),
            'cantidad' => $cantidad,
            'esperado' => Decimal::of($fondoInicial)->add($efectivo)->sub($desembolsos)->toFixed(2),
        ];
    }

    /**
     * CU-36 pasos 5 a 8 (A2, A3).
     *
     * @return array{errores: array<string, string>, declarado: ?string, diferencia: ?string, explicacion: string}
     */
    public static function validarCierre(string $declaradoTexto, string $explicacion, string $esperado): array
    {
        $e = [];
        $declarado = Campos::importe($declaradoTexto);
        if ($declarado === null) {
            $e['efectivo_declarado'] = 'Ingrese el efectivo contado al cierre (un importe no negativo).';
        }
        $diferencia = $declarado === null ? null : Decimal::of($declarado)->sub($esperado)->toFixed(2);
        $texto = Campos::texto($explicacion);
        if ($diferencia !== null && !Decimal::of($diferencia)->isZero() && mb_strlen($texto) < 10) {
            $e['explicacion'] = 'Hay una diferencia de ' . str_replace('.', ',', $diferencia) . ': explique su causa (al menos 10 caracteres).';
        } elseif (mb_strlen($texto) > 500) {
            $e['explicacion'] = 'La explicación admite hasta 500 caracteres.';
        }

        return ['errores' => $e, 'declarado' => $declarado, 'diferencia' => $diferencia, 'explicacion' => $texto];
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
        $tipo = (string) ($d['tipo'] ?? '');
        if (!isset(self::TIPOS_FONDOS[$tipo])) {
            $e['tipo'] = 'Seleccione el tipo de movimiento.';
        }
        $caracter = (string) ($d['caracter'] ?? '');
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
        if ($concepto === '' || mb_strlen($concepto) > 200) {
            $e['concepto'] = 'Ingrese el concepto (hasta 200 caracteres).';
        }
        $referencia = Campos::texto($d['referencia'] ?? '');
        if (mb_strlen($referencia) > 100) {
            $e['referencia'] = 'La referencia admite hasta 100 caracteres.';
        }

        return ['errores' => $e, 'datos' => [
            'tipo' => $tipo, 'caracter' => $caracter, 'estado' => $estado, 'importe' => $importe, 'fecha' => $fecha,
            'concepto' => $concepto, 'referencia' => $referencia === '' ? null : $referencia,
        ]];
    }
}
