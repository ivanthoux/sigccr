<?php

declare(strict_types=1);

namespace App\Libraries\Comunicaciones;

use App\Adapters\Comunicaciones\ComunicacionesInterface;
use DateTimeImmutable;

/**
 * Política de envío de la cola de comunicaciones (CU-95, RNF-09).
 *
 * - Validez antes de enviar (paso 3): una autorización debe seguir vigente, sin usar ni sustituir (RN-03); una oferta
 *   debe seguir vigente y el cliente conservar el consentimiento para comunicaciones comerciales (RN-64). Si deja de
 *   ser válida, la comunicación se cancela sin enviarse (A1).
 * - Resultado del intento: aceptado por el proveedor; fallo transitorio con reintentos al minuto, a los tres y a los
 *   cinco minutos del primer intento (A2); fallo definitivo sin reintentos (A3); resultado incierto, que queda En
 *   revisión y no se reenvía (A4).
 */
final class PoliticaEnvio
{
    /** Minutos desde el primer intento en que se programa cada reintento (RNF-09). */
    public const REINTENTOS_MINUTOS = [1, 3, 5];

    /**
     * Motivo por el que la comunicación ya no debe enviarse, o null si sigue siendo válida.
     *
     * @param array{estado: string, vence: string}|null $autorizacion autorización de origen (tipos Autorizacion y VerificacionCorreo)
     * @param array{estado: string, vence: string}|null $oferta       oferta de origen (tipo Oferta)
     * @param bool|null $consentimiento consentimiento vigente para comunicaciones comerciales (tipo Oferta)
     */
    public static function motivoCancelacion(string $tipo, ?array $autorizacion, ?array $oferta, ?bool $consentimiento, DateTimeImmutable $ahora): ?string
    {
        if (in_array($tipo, ['Autorizacion', 'VerificacionCorreo'], true)) {
            if ($autorizacion === null) {
                return 'La autorización de origen no existe.';
            }
            if ($autorizacion['estado'] !== 'Vigente') {
                return 'La autorización ya fue ' . ($autorizacion['estado'] === 'Utilizada' ? 'utilizada.' : 'sustituida o vencida.');
            }
            if (new DateTimeImmutable($autorizacion['vence']) <= $ahora) {
                return 'La autorización venció antes del envío.';
            }
        }
        if ($tipo === 'Oferta') {
            if ($oferta === null || $oferta['estado'] !== 'Vigente' || new DateTimeImmutable($oferta['vence']) <= $ahora) {
                return 'La oferta ya no está vigente.';
            }
            if ($consentimiento !== true) {
                return 'El cliente no tiene consentimiento vigente para comunicaciones comerciales (RN-64).';
            }
        }

        return null;
    }

    /**
     * Estado que corresponde después de un intento.
     *
     * @param int $intentos cantidad de intentos realizados, incluido el actual
     * @return array{estado: string, programada_para: ?DateTimeImmutable, alerta: bool}
     */
    public static function despuesDelIntento(string $resultado, int $intentos, DateTimeImmutable $primerIntento): array
    {
        return match ($resultado) {
            ComunicacionesInterface::ACEPTADO => ['estado' => 'AceptadaProveedor', 'programada_para' => null, 'alerta' => false],
            ComunicacionesInterface::FALLO_DEFINITIVO => ['estado' => 'Fallida', 'programada_para' => null, 'alerta' => true],
            ComunicacionesInterface::INCIERTO => ['estado' => 'EnRevision', 'programada_para' => null, 'alerta' => true],
            default => $intentos <= count(self::REINTENTOS_MINUTOS)
                ? ['estado' => 'ReintentoProgramado', 'programada_para' => $primerIntento->modify('+' . self::REINTENTOS_MINUTOS[$intentos - 1] . ' minutes'), 'alerta' => false]
                : ['estado' => 'Fallida', 'programada_para' => null, 'alerta' => true],
        };
    }
}
