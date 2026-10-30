<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;

/**
 * CU-27 Liquidar Crédito Aprobado, pasos 4 a 6 (A7): verificación de la identidad del destinatario y datos del
 * desembolso (RN-25). Solo se admiten el efectivo por caja y la transferencia a una cuenta del propio cliente.
 */
final class Desembolso
{
    public const MEDIOS = ['Efectivo' => 'Efectivo por caja', 'Transferencia' => 'Transferencia a una cuenta del cliente'];

    /**
     * @param array<string, mixed> $d medio, documento_verificado, conformidad (si el plan la requiere), cbu,
     *                                cuit_titular, evidencia_titularidad, comprobante, fecha_transferencia,
     *                                importe_transferencia
     * @param array<string, mixed> $cliente tipo_cliente, nro_documento, representante_documento, cuit_cuil
     * @return array{errores: array<string, string>, datos: array<string, mixed>}
     */
    public static function validar(array $d, array $cliente, string $neto, string $hoy, bool $requiereConformidad): array
    {
        $e = [];
        $medio = (string) ($d['medio'] ?? '');
        if (!isset(self::MEDIOS[$medio])) {
            $e['medio'] = 'Seleccione el medio de desembolso.';
        }
        // Identidad del destinatario: se reingresa el número del documento que presenta y se compara con el padrón
        $esperado = $cliente['tipo_cliente'] === 'PersonaJuridica' ? (string) $cliente['representante_documento'] : (string) $cliente['nro_documento'];
        $documento = Campos::documento($d['documento_verificado'] ?? '');
        if ($documento === '') {
            $e['documento_verificado'] = 'Ingrese el número del documento que presenta el destinatario.';
        } elseif ($documento !== $esperado) {
            $e['documento_verificado'] = 'El documento no coincide con el ' . ($cliente['tipo_cliente'] === 'PersonaJuridica' ? 'del representante registrado' : 'del cliente') . ': no se puede desembolsar a un tercero (RN-25).';
        }
        if ($requiereConformidad && empty($d['conformidad'])) {
            $e['conformidad'] = 'El plan lo definió el Gerente: registre la conformidad del cliente antes de liquidar (RN-22).';
        }
        $datos = ['medio' => $medio, 'cbu' => null, 'comprobante' => null, 'evidencia' => null];
        if ($medio === 'Transferencia') {
            $cbu = preg_replace('/\D/', '', (string) ($d['cbu'] ?? '')) ?? '';
            if (!self::cbuValido($cbu)) {
                $e['cbu'] = 'Ingrese un CBU o CVU válido de 22 dígitos.';
            }
            $titular = preg_replace('/\D/', '', (string) ($d['cuit_titular'] ?? '')) ?? '';
            if ($titular === '' || $titular !== (string) $cliente['cuit_cuil']) {
                $e['cuit_titular'] = $cliente['cuit_cuil'] === null
                    ? 'El cliente no tiene CUIT/CUIL registrado: regístrelo antes de transferir (CU-12).'
                    : 'El titular de la cuenta debe ser el cliente (' . self::guiones((string) $cliente['cuit_cuil']) . '): no se admiten cuentas de terceros (RN-25).';
            }
            $evidencia = Campos::texto($d['evidencia_titularidad'] ?? '');
            if (mb_strlen($evidencia) < 10) {
                $e['evidencia_titularidad'] = 'Describa cómo verificó la titularidad de la cuenta (al menos 10 caracteres).';
            }
            $numero = Campos::texto($d['comprobante'] ?? '');
            if ($numero === '' || mb_strlen($numero) > 60) {
                $e['comprobante'] = 'Ingrese el número de operación de la transferencia.';
            }
            $fecha = Campos::fecha($d['fecha_transferencia'] ?? '');
            if ($fecha === null || $fecha > $hoy) {
                $e['fecha_transferencia'] = 'Ingrese la fecha de la transferencia (no futura).';
            }
            $importe = Campos::importe($d['importe_transferencia'] ?? '');
            if ($importe === null || !Decimal::of($importe)->equals($neto)) {
                $e['importe_transferencia'] = 'El importe transferido debe ser el neto a desembolsar.';
            }
            $datos = ['medio' => $medio, 'cbu' => $cbu, 'comprobante' => mb_substr("Op. {$numero} del " . ($fecha ?? ''), 0, 100), 'evidencia' => $evidencia];
        }

        return ['errores' => $e, 'datos' => $datos];
    }

    /** CBU o CVU: 22 dígitos con los dígitos verificadores de sus dos bloques. */
    public static function cbuValido(string $cbu): bool
    {
        if (preg_match('/^\d{22}$/', $cbu) !== 1) {
            return false;
        }
        $verificador = static function (string $digitos, array $pesos): int {
            $suma = 0;
            foreach (str_split($digitos) as $i => $c) {
                $suma += (int) $c * $pesos[$i % count($pesos)];
            }

            return (10 - $suma % 10) % 10;
        };

        return $verificador(substr($cbu, 0, 7), [7, 1, 3, 9]) === (int) $cbu[7]
            && $verificador(substr($cbu, 8, 13), [3, 9, 7, 1]) === (int) $cbu[21];
    }

    private static function guiones(string $cuit): string
    {
        return strlen($cuit) === 11 ? substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10) : $cuit;
    }
}
