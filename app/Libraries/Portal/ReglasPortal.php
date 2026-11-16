<?php

declare(strict_types=1);

namespace App\Libraries\Portal;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Punitorios;
use App\Libraries\Validacion\Campos;

/**
 * Reglas del portal de autogestión (SS-07). El portal no tiene reglas de negocio propias: presenta la información de
 * los demás subsistemas restringida al cliente autenticado (3.7.1). Esta clase reúne lo que la presentación necesita
 * calcular sin base de datos: la acción pendiente de cada solicitud, el resumen de la deuda por concepto y las
 * validaciones de CU-75 y CU-98.
 */
final class ReglasPortal
{
    /** Conceptos del estado de cuenta (CU-47 paso 2), en el orden de la prelación de RN-29. */
    public const CONCEPTOS = [
        'Punitorio' => 'Intereses punitorios',
        'InteresOrdinario' => 'Intereses ordinarios',
        'GastosCargos' => 'Gastos y cargos',
        'NoCapitalizable' => 'Concepto no capitalizable',
        'IVA' => 'IVA',
        'Capital' => 'Capital',
    ];

    /**
     * CU-46 paso 4: lo que el cliente tiene que hacer para que su solicitud avance, o null si no depende de él.
     */
    public static function accionPendiente(string $estado): ?string
    {
        return match ($estado) {
            'PendienteDocumentacion' => 'Presente en la sucursal la documentación observada o faltante.',
            'DevueltaRevision' => 'La entidad revisa su solicitud: si le piden documentación o aclaraciones, preséntelas en la sucursal.',
            'PendienteSeleccionPlan' => 'Acérquese a la sucursal para elegir y confirmar la alternativa de financiación.',
            'AprobadaPendienteLiquidacion' => 'Firme el contrato en la sucursal e informe la cuenta (CBU o CVU) para el desembolso.',
            default => null,
        };
    }

    /**
     * CU-46 A2: condición final de una solicitud terminada, con lo que pasó con los fondos reservados.
     */
    public static function condicionFinal(string $estado): ?string
    {
        return match ($estado) {
            'Liquidada' => 'Solicitud liquidada: el crédito ya está en su estado de cuenta.',
            'Rechazada' => 'Solicitud rechazada. Se liberaron los fondos que tenía reservados.',
            'Cancelada' => 'Solicitud cancelada. Se liberaron los fondos que tenía reservados.',
            'Vencida' => 'Solicitud vencida por no completarse a tiempo. Se liberaron los fondos que tenía reservados.',
            default => null,
        };
    }

    /**
     * CU-47 paso 2: deuda total por concepto, cuotas pendientes y vencidas.
     *
     * @param list<array{conceptos: array<string, Decimal>, dias_atraso: int}> $cuotas cuotas impagas, con los punitorios a hoy
     * @return array{conceptos: array<string, string>, total: string, pendientes: int, vencidas: int}
     */
    public static function resumenDeuda(array $cuotas): array
    {
        $totales = array_fill_keys(array_keys(self::CONCEPTOS), Decimal::zero());
        $vencidas = 0;
        foreach ($cuotas as $q) {
            foreach ($totales as $k => $v) {
                $totales[$k] = $v->add($q['conceptos'][$k] ?? Decimal::zero());
            }
            if ($q['dias_atraso'] > 0) {
                $vencidas++;
            }
        }

        return [
            'conceptos' => array_map(static fn (Decimal $d): string => $d->toFixed(2), $totales),
            'total' => Decimal::sum(array_values($totales))->toFixed(2),
            'pendientes' => count($cuotas) - $vencidas,
            'vencidas' => $vencidas,
        ];
    }

    /**
     * CU-47 paso 2: próximos vencimientos (cuotas impagas que todavía no vencieron), del más cercano al más lejano.
     *
     * @template T of array{vencimiento: string, dias_atraso: int}
     * @param list<T> $cuotas
     * @return list<T>
     */
    public static function proximosVencimientos(array $cuotas, int $cantidad = 3): array
    {
        $futuras = array_values(array_filter($cuotas, static fn (array $q): bool => $q['dias_atraso'] === 0));
        usort($futuras, static fn (array $a, array $b): int => strcmp(substr((string) $a['vencimiento'], 0, 10), substr((string) $b['vencimiento'], 0, 10)));

        return array_slice($futuras, 0, $cantidad);
    }

    /**
     * CU-47 paso 6 y A3: situación de una cuota frente a la tolerancia asignada al crédito (RN-53).
     */
    public static function situacionAtraso(int $diasAtraso, int $tolerancia): string
    {
        if ($diasAtraso === 0) {
            return 'Sin atraso.';
        }
        if (!Punitorios::esExigible($diasAtraso, $tolerancia)) {
            return "Vencida hace {$diasAtraso} día(s), dentro de la tolerancia de {$tolerancia} día(s): todavía no se exigen intereses punitorios.";
        }

        return "Vencida hace {$diasAtraso} día(s): superada la tolerancia de {$tolerancia} día(s), se exigen intereses punitorios desde el primer día de atraso.";
    }

    /**
     * CU-98 paso 3 y A1: el nuevo estado del consentimiento para comunicaciones comerciales debe ser distinto del
     * vigente. Sin registro previo, el consentimiento se considera no otorgado.
     */
    public static function validarCambioConsentimiento(?bool $vigente, bool $nuevo): void
    {
        if (($vigente ?? false) === $nuevo) {
            throw new ReglaNegocioException($nuevo ? 'Ya tiene otorgado el consentimiento: no hay cambios por registrar.' : 'El consentimiento ya está revocado: no hay cambios por registrar.', 'RN-64');
        }
    }

    /**
     * CU-75 paso 4: formato del nuevo correo y que sea distinto del actual. Su disponibilidad la controla el servicio.
     */
    public static function validarCorreoNuevo(string $nuevo, string $actual): ?string
    {
        if ($nuevo === '') {
            return 'Ingrese el nuevo correo electrónico.';
        }
        $formato = Campos::formatoCorreo($nuevo);
        if ($formato !== null) {
            return $formato;
        }

        return $nuevo === mb_strtolower($actual) ? 'El nuevo correo es igual al actual.' : null;
    }
}
