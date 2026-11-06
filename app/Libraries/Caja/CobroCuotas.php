<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Imputador;
use App\Libraries\Validacion\Campos;
use DateTimeImmutable;

/**
 * Reglas del cobro de cuotas en ventanilla (CU-33, RN-29, D-12): deuda exigible de cada cuota por concepto, selección
 * consecutiva por crédito, orden de imputación entre créditos y saldos de la cuota después del cobro.
 *
 * Los punitorios los devenga CU-88 en la cuota (cuatro decimales) y se exigen redondeados a dos (RN-34, RN-50); el IVA
 * de los punitorios integra el concepto IVA. Las cuotas no vencidas se cobran a su importe nominal.
 */
final class CobroCuotas
{
    /**
     * Saldo exigible de la cuota por concepto, en el orden de prelación de RN-29.
     *
     * @param array<string, mixed> $c fila de cuotas
     * @return array<string, Decimal>
     */
    public static function conceptos(array $c): array
    {
        return [
            'Punitorio' => Decimal::of((string) $c['punitorio_pendiente'])->round(2),
            'InteresOrdinario' => Decimal::of((string) $c['interes_pendiente']),
            'GastosCargos' => Decimal::of((string) $c['gastos_pendiente']),
            'IVA' => Decimal::of((string) $c['iva_pendiente'])->add(Decimal::of((string) $c['iva_punitorio_pendiente'])->round(2)),
            'NoCapitalizable' => Decimal::of((string) $c['no_capitalizable_pendiente']),
            'Capital' => Decimal::of((string) $c['capital_pendiente']),
        ];
    }

    /** @param array<string, mixed> $c fila de cuotas */
    public static function total(array $c): Decimal
    {
        return Imputador::totalCuota(self::conceptos($c));
    }

    /** Días de atraso a la fecha (0 si no venció). */
    public static function diasAtraso(string $vencimiento, string $hoy): int
    {
        $v = new DateTimeImmutable(substr($vencimiento, 0, 10));
        $h = new DateTimeImmutable($hoy);

        return $h > $v ? (int) $v->diff($h)->days : 0;
    }

    /**
     * CU-33 paso 5 (A8): por cada crédito la selección comienza por la cuota impaga más antigua y es consecutiva.
     *
     * @param array<int, list<int>> $pendientes crédito => números de cuota impagos en orden ascendente
     * @param array<int, list<int>> $seleccion  crédito => números elegidos
     * @param array<int, string>    $nombres    crédito => número del crédito para los mensajes
     * @return list<string>
     */
    public static function validarSeleccion(array $pendientes, array $seleccion, array $nombres = []): array
    {
        $e = [];
        $seleccion = array_filter($seleccion, static fn ($s) => $s !== []);
        if ($seleccion === []) {
            return ['Seleccione al menos una cuota.'];
        }
        foreach ($seleccion as $credito => $numeros) {
            if (!isset($pendientes[$credito])) {
                $e[] = 'El crédito ' . ($nombres[$credito] ?? $credito) . ' no tiene cuotas pendientes del cliente.';
                continue;
            }
            if (!Imputador::seleccionConsecutiva($pendientes[$credito], array_map('intval', $numeros))) {
                $e[] = 'Crédito ' . ($nombres[$credito] ?? $credito) . ': la selección debe comenzar por la cuota ' . $pendientes[$credito][0]
                    . ' y seguir sin saltear cuotas (RN-29).';
            }
        }

        return $e;
    }

    /**
     * Orden de imputación entre las cuotas seleccionadas: por vencimiento y, a igual vencimiento, por crédito y número
     * (RN-29: primero la obligación más antigua).
     *
     * @param list<array<string, mixed>> $cuotas
     * @return list<array<string, mixed>>
     */
    public static function ordenar(array $cuotas): array
    {
        usort($cuotas, static fn ($a, $b) => [substr((string) $a['vencimiento'], 0, 10), (int) $a['credito_id'], (int) $a['numero']]
            <=> [substr((string) $b['vencimiento'], 0, 10), (int) $b['credito_id'], (int) $b['numero']]);

        return $cuotas;
    }

    /**
     * CU-33 pasos 7 y 8: importe a cobrar; vacío equivale al total.
     *
     * @return array{importe: ?Decimal, error: ?string}
     */
    public static function importeACobrar(string $texto, Decimal $total): array
    {
        if (trim($texto) === '') {
            return ['importe' => $total, 'error' => null];
        }
        $i = Campos::importe($texto);
        if ($i === null || !Decimal::of($i)->isPositive()) {
            return ['importe' => null, 'error' => 'Ingrese un importe a cobrar mayor que cero.'];
        }
        if (Decimal::of($i)->compare($total) > 0) {
            return ['importe' => null, 'error' => 'El importe a cobrar no puede superar el total de las cuotas seleccionadas ($ ' . $total->toFixed(2) . ', RN-29).'];
        }

        return ['importe' => Decimal::of($i), 'error' => null];
    }

    /**
     * Columnas de la cuota después de imputarle un cobro (CU-33 paso 9). El IVA se aplica primero al de los conceptos
     * ordinarios y luego al de los punitorios; un concepto cancelado queda en cero aunque tuviera fracciones de centavo.
     *
     * @param array<string, mixed> $c fila de cuotas
     * @param list<array{cuota: int|string, concepto: string, importe: Decimal, parcial: bool}> $imputaciones de esta cuota
     * @return array{columnas: array<string, string>, pagada: bool}
     */
    public static function saldosDespues(array $c, array $imputaciones): array
    {
        $por = [];
        foreach ($imputaciones as $i) {
            $por[$i['concepto']] = ($por[$i['concepto']] ?? Decimal::zero())->add($i['importe']);
        }
        $menos = static fn (string $col, string $concepto) => Decimal::of((string) $c[$col])->sub($por[$concepto] ?? Decimal::zero());
        $columnas = [
            'interes_pendiente' => $menos('interes_pendiente', 'InteresOrdinario')->toFixed(2),
            'gastos_pendiente' => $menos('gastos_pendiente', 'GastosCargos')->toFixed(2),
            'no_capitalizable_pendiente' => $menos('no_capitalizable_pendiente', 'NoCapitalizable')->toFixed(2),
            'capital_pendiente' => $menos('capital_pendiente', 'Capital')->toFixed(2),
        ];
        $punitorio = Decimal::of((string) $c['punitorio_pendiente']);
        $aplicadoPunitorio = $por['Punitorio'] ?? Decimal::zero();
        $columnas['punitorio_pendiente'] = $aplicadoPunitorio->compare($punitorio->round(2)) >= 0 ? '0.0000' : $punitorio->sub($aplicadoPunitorio)->toFixed(4);
        $iva = Decimal::of((string) $c['iva_pendiente']);
        $aplicadoIva = $por['IVA'] ?? Decimal::zero();
        $alOrdinario = Decimal::min($aplicadoIva, $iva);
        $columnas['iva_pendiente'] = $iva->sub($alOrdinario)->toFixed(2);
        $ivaPunitorio = Decimal::of((string) $c['iva_punitorio_pendiente']);
        $alPunitorio = $aplicadoIva->sub($alOrdinario);
        $columnas['iva_punitorio_pendiente'] = $alPunitorio->compare($ivaPunitorio->round(2)) >= 0 ? '0.0000' : $ivaPunitorio->sub($alPunitorio)->toFixed(4);

        $pagada = true;
        foreach ($columnas as $v) {
            if (!Decimal::of($v)->isZero()) {
                $pagada = false;
            }
        }

        return ['columnas' => $columnas, 'pagada' => $pagada];
    }

    /**
     * CU-37 paso 8: columnas de la cuota después de anular un cobro que le imputó importes (RN-33). Cada concepto
     * recupera lo imputado; el IVA vuelve primero al de los conceptos ordinarios, hasta su importe nominal, y el resto
     * al de los punitorios. La cuota vuelve a Pendiente o Vencida según su vencimiento.
     *
     * @param array<string, mixed> $c fila de cuotas
     * @param list<array{concepto: string, importe: string}> $imputaciones del cobro anulado a esta cuota
     * @return array{columnas: array<string, string>, estado: string}
     */
    public static function restaurar(array $c, array $imputaciones, string $hoy): array
    {
        $por = [];
        foreach ($imputaciones as $i) {
            $por[$i['concepto']] = ($por[$i['concepto']] ?? Decimal::zero())->add($i['importe']);
        }
        $mas = static fn (string $col, string $concepto) => Decimal::of((string) $c[$col])->add($por[$concepto] ?? Decimal::zero());
        $iva = Decimal::of((string) $c['iva_pendiente']);
        $hueco = Decimal::of((string) $c['iva'])->sub($iva);
        $ivaDevuelto = $por['IVA'] ?? Decimal::zero();
        $alOrdinario = Decimal::min($ivaDevuelto, $hueco->isNegative() ? Decimal::zero() : $hueco);
        $columnas = [
            'interes_pendiente' => $mas('interes_pendiente', 'InteresOrdinario')->toFixed(2),
            'gastos_pendiente' => $mas('gastos_pendiente', 'GastosCargos')->toFixed(2),
            'no_capitalizable_pendiente' => $mas('no_capitalizable_pendiente', 'NoCapitalizable')->toFixed(2),
            'capital_pendiente' => $mas('capital_pendiente', 'Capital')->toFixed(2),
            'punitorio_pendiente' => $mas('punitorio_pendiente', 'Punitorio')->toFixed(4),
            'iva_pendiente' => $iva->add($alOrdinario)->toFixed(2),
            'iva_punitorio_pendiente' => Decimal::of((string) $c['iva_punitorio_pendiente'])->add($ivaDevuelto->sub($alOrdinario))->toFixed(4),
        ];

        return ['columnas' => $columnas, 'estado' => substr((string) $c['vencimiento'], 0, 10) < $hoy ? 'Vencida' : 'Pendiente'];
    }
}

