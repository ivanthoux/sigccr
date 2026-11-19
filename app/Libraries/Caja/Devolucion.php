<?php

declare(strict_types=1);

namespace App\Libraries\Caja;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Solicitudes\Desembolso;
use App\Libraries\Validacion\Campos;

/**
 * Reglas de la devolución de un saldo a favor (RN-73, RN-77; CU-77, CU-78; D-37).
 *
 * - Importe mayor que cero y no superior al disponible del saldo.
 * - Destino: el medio de pago original solo si el saldo proviene de un pago electrónico (la pasarela admite la
 *   devolución de la transacción); si no, transferencia a una cuenta bancaria del cliente: CBU válido (no CVU), CUIT
 *   del titular igual al del cliente, entidad y evidencia de la verificación de titularidad. Sin efectivo ni terceros.
 * - La transferencia ejecutada debe coincidir en importe y cuenta con lo autorizado.
 */
final class Devolucion
{
    public const DESTINOS = ['MedioOriginal' => 'Medio de pago original (pasarela)', 'Transferencia' => 'Transferencia a una cuenta del cliente'];
    public const HORAS_VIGENCIA = 72;

    /**
     * @param array<string, mixed> $d importe, motivo, fecha_solicitud, medio_solicitud, destino, cbu, cuit_titular, entidad, evidencia
     * @param array<string, mixed> $saldo fila de saldos_a_favor
     * @return array{errores: array<string, string>, datos: array<string, mixed>}
     */
    public static function validarAutorizacion(array $d, array $saldo, string $cuitCliente, string $hoy): array
    {
        $e = [];
        $importe = Campos::importe($d['importe'] ?? '');
        $disponible = Decimal::of((string) $saldo['importe_disponible']);
        if ($importe === null || !Decimal::of($importe)->isPositive()) {
            $e['importe'] = 'Ingrese el importe a devolver, mayor que cero.';
        } elseif (Decimal::of($importe)->compare($disponible) > 0) {
            $e['importe'] = 'El importe supera el saldo disponible: el máximo es ' . self::importe($disponible->toFixed(2)) . ' (A1).';
        }
        $motivo = Campos::texto($d['motivo'] ?? '');
        // H-16: largos en unidades de NVARCHAR; H-15: valores con otra forma se toman como vacíos
        if (($m = Campos::motivo($motivo, 500)) !== null) {
            $e['motivo'] = $m;
        }
        $fecha = Campos::fecha($d['fecha_solicitud'] ?? '');
        if ($fecha === null || $fecha > $hoy) {
            $e['fecha_solicitud'] = 'Ingrese la fecha de la solicitud del cliente (no posterior a hoy).';
        }
        $medio = Campos::texto($d['medio_solicitud'] ?? '');
        if ($medio === '' || Campos::largo($medio) > 100) {
            $e['medio_solicitud'] = 'Indique el medio de la solicitud del cliente (hasta 100 caracteres).';
        }
        $destino = Campos::texto($d['destino'] ?? '');
        $cbu = preg_replace('/\D/', '', Campos::crudo($d['cbu'] ?? '')) ?? '';
        $cuit = preg_replace('/\D/', '', Campos::crudo($d['cuit_titular'] ?? '')) ?? '';
        $entidad = Campos::texto($d['entidad'] ?? '');
        $evidencia = Campos::texto($d['evidencia'] ?? '');
        if (!isset(self::DESTINOS[$destino])) {
            $e['destino'] = 'Seleccione el destino de la devolución. No se admiten devoluciones en efectivo (RN-73, RN-77).';
        } elseif ($destino === 'MedioOriginal' && ($saldo['transaccion_origen_id'] ?? null) === null) {
            $e['destino'] = 'El medio de pago de origen de este saldo no admite la devolución: use una transferencia a una cuenta del cliente (A2, RN-73).';
        } elseif ($destino === 'Transferencia') {
            if (Desembolso::esCvu($cbu) && strlen($cbu) === 22) {
                $e['cbu'] = 'No se admiten CVU de billeteras virtuales: indique un CBU (A2, RN-77).';
            } elseif (!Desembolso::cbuValido($cbu)) {
                $e['cbu'] = 'El CBU debe tener 22 dígitos con sus dígitos verificadores correctos.';
            }
            if ($cuit === '' || $cuit !== preg_replace('/\D/', '', $cuitCliente)) {
                $e['cuit_titular'] = 'El CUIT/CUIL del titular de la cuenta debe ser el del cliente: no se devuelve a cuentas de terceros (A3, RN-73).';
            }
            if ($entidad === '' || Campos::largo($entidad) > 100) {
                $e['entidad'] = 'Indique la entidad bancaria (hasta 100 caracteres).';
            }
            if ($evidencia === '' || Campos::largo($evidencia) > 300) {
                $e['evidencia'] = 'Indique la evidencia de la verificación de titularidad, por ejemplo la constancia de CBU (A3).';
            }
        }
        $transferencia = $destino === 'Transferencia';

        return ['errores' => $e, 'datos' => [
            'importe' => $importe, 'motivo' => $motivo, 'fecha_solicitud' => $fecha, 'medio_solicitud' => $medio, 'destino' => $destino,
            'cbu_cvu' => $transferencia ? $cbu : null, 'cuit_titular' => $transferencia ? $cuit : null,
            'entidad_bancaria' => $transferencia ? $entidad : null, 'evidencia_titularidad' => $transferencia ? $evidencia : null,
        ]];
    }

    /**
     * CU-78 A1 y A6: comprobante de la transferencia realizada.
     *
     * @param array<string, mixed> $d numero_operacion, fecha, importe, cbu
     * @param array<string, mixed> $autorizacion fila de autorizaciones_devolucion
     * @return array{errores: array<string, string>, discrepancia: bool, datos: array<string, mixed>}
     */
    public static function validarTransferencia(array $d, array $autorizacion, string $hoy): array
    {
        $e = [];
        $numero = Campos::texto($d['numero_operacion'] ?? '');
        // H-26: el comprobante se guarda en VARCHAR(100)
        if (!Campos::operacionValida($numero, 100)) {
            $e['numero_operacion'] = 'Ingrese el número de operación de la transferencia (hasta 100 caracteres).';
        }
        $fecha = Campos::fecha($d['fecha'] ?? '');
        if ($fecha === null || $fecha > $hoy) {
            $e['fecha'] = 'Ingrese la fecha de la transferencia (no posterior a hoy).';
        }
        $importe = Campos::importe($d['importe'] ?? '');
        $cbu = preg_replace('/\D/', '', Campos::crudo($d['cbu'] ?? '')) ?? '';
        $discrepancia = false;
        if ($importe === null) {
            $e['importe'] = 'Ingrese el importe transferido.';
        } elseif (Decimal::of($importe)->compare((string) $autorizacion['importe']) !== 0) {
            $e['importe'] = 'El importe transferido no coincide con el autorizado (' . self::importe((string) $autorizacion['importe']) . ', A6).';
            $discrepancia = true;
        }
        if ($cbu !== trim((string) $autorizacion['cbu_cvu'])) {
            $e['cbu'] = 'La cuenta destino no coincide con la autorizada (A6).';
            $discrepancia = $discrepancia || $cbu !== '';
        }

        return ['errores' => $e, 'discrepancia' => $discrepancia, 'datos' => ['numero_operacion' => $numero, 'fecha' => $fecha, 'importe' => $importe, 'cbu' => $cbu]];
    }

    /** Clasificación del estado informado por la pasarela para una devolución. */
    public static function resultado(string $estado): string
    {
        return match (PagoElectronico::clasificar($estado)) {
            'aprobado' => 'Confirmada',
            'rechazado' => 'Rechazada',
            default => 'Incierta',
        };
    }

    private static function importe(string $valor): string
    {
        return '$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
