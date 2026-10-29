<?php

declare(strict_types=1);

namespace App\Libraries\Seguridad;

use App\Libraries\Validacion\Campos;

/**
 * CU-04 Activar o Rehabilitar Acceso del Cliente (RN-10, RN-62).
 *
 * La activación corresponde a una cuenta Pendiente de activación; la rehabilitación, a una cuenta Desactivada de un
 * cliente reactivado en el padrón (CU-14). En ambos casos el cliente debe estar Activo y el correo ingresado debe
 * coincidir con su correo verificado. Una cuenta Habilitada o bloqueada temporalmente no recibe la autorización, pero
 * la respuesta es la misma que si se hubiera enviado (A2), para no revelar su estado.
 */
final class ReglasActivacion
{
    public const RESULTADO_ENVIAR = 'Enviar';
    public const RESULTADO_SIN_ENVIO = 'SinEnvio';
    public const RESULTADO_NO_VERIFICADO = 'NoVerificado';

    /**
     * Identificación ingresada en el paso 2: tipo y número de documento de una persona humana, o CUIT de una
     * persona jurídica. Devuelve null si el dato no es válido.
     *
     * @return array{tipo_documento: string, nro_documento: string}|array{cuit: string}|null
     */
    public static function identificacion(string $tipo, string $numero): ?array
    {
        if ($tipo === 'CUIT') {
            $cuit = str_replace(['-', ' ', '.'], '', Campos::texto($numero));

            return Campos::cuit($cuit) === null && $cuit !== '' ? ['cuit' => $cuit] : null;
        }
        $nro = Campos::documentoFlexible($numero);
        if (Campos::tipoDocumento($tipo) !== null || $tipo === '' || $nro === '' || Campos::numeroDocumento($tipo, $nro) !== null) {
            return null;
        }

        return ['tipo_documento' => $tipo, 'nro_documento' => $nro];
    }

    /**
     * Paso 4: resultado de la verificación y propósito de la autorización.
     *
     * @param array{estado_cliente: string, correo: string, correo_verificado: bool, estado_cuenta: ?string, bloqueada: bool}|null $cliente
     * @return array{resultado: string, proposito: ?string, motivo: string}
     */
    public static function evaluar(?array $cliente, string $correoIngresado): array
    {
        $no = static fn (string $m): array => ['resultado' => self::RESULTADO_NO_VERIFICADO, 'proposito' => null, 'motivo' => $m];
        if ($cliente === null) {
            return $no('Cliente inexistente');
        }
        if ($cliente['estado_cliente'] !== 'Activo') {
            return $no('Cliente desactivado en el padrón (RN-10)');
        }
        if (!$cliente['correo_verificado'] || Campos::correo($correoIngresado) !== Campos::correo($cliente['correo'])) {
            return $no('El correo no coincide con un correo verificado');
        }
        if ($cliente['bloqueada'] || $cliente['estado_cuenta'] === 'BloqueadaTemporalmente') {
            return ['resultado' => self::RESULTADO_SIN_ENVIO, 'proposito' => null, 'motivo' => 'Cuenta bloqueada temporalmente (RN-62)'];
        }

        return match ($cliente['estado_cuenta']) {
            'PendienteActivacion' => ['resultado' => self::RESULTADO_ENVIAR, 'proposito' => 'Activacion', 'motivo' => 'Activación'],
            'Desactivada' => ['resultado' => self::RESULTADO_ENVIAR, 'proposito' => 'Rehabilitacion', 'motivo' => 'Rehabilitación'],
            'Habilitada' => ['resultado' => self::RESULTADO_SIN_ENVIO, 'proposito' => null, 'motivo' => 'Cuenta ya habilitada'],
            default => $no('El cliente no tiene cuenta de acceso'),
        };
    }
}
