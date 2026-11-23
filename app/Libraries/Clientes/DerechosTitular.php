<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

use App\Libraries\Finanzas\CalendarioHabil;
use App\Libraries\Validacion\Campos;
use DateTimeImmutable;

/**
 * Reglas de las solicitudes de derechos del titular de los datos personales (CU-108, D-64; Ley 25.326, arts. 14, 16
 * y 20; RN-85, RN-86). Sin base de datos: los plazos y las validaciones se prueban sin entorno.
 *
 * - Acceso y revisión de una valoración automatizada: 10 días corridos desde la recepción (art. 14, inc. 2).
 * - Rectificación, actualización y supresión: 5 días hábiles desde la recepción (art. 16, inc. 2), según el calendario
 *   operativo (los días no hábiles no se cuentan).
 */
final class DerechosTitular
{
    public const TIPOS = [
        'Acceso' => 'Acceso a mis datos',
        'Rectificacion' => 'Rectificación de datos inexactos',
        'Actualizacion' => 'Actualización de datos',
        'Supresion' => 'Supresión de datos',
        'RevisionAutomatizada' => 'Revisión de una decisión automatizada',
    ];
    public const ESTADOS = ['Pendiente' => 'Pendiente', 'Atendida' => 'Atendida', 'Denegada' => 'Denegada'];
    public const CANALES = ['Portal' => 'Portal', 'Sucursal' => 'Sucursal'];

    /** Tipos que se cuentan en días hábiles (art. 16); el resto, en días corridos (art. 14). */
    private const EN_DIAS_HABILES = ['Rectificacion', 'Actualizacion', 'Supresion'];

    /** Días antes del vencimiento desde los que el plazo se marca «por vencer». */
    public const DIAS_AVISO = 2;

    public const LARGO_DETALLE = 1000;
    public const LARGO_RESPUESTA = 1000;

    /**
     * Fecha límite de respuesta (RN-85).
     *
     * @param DateTimeImmutable $recepcion   instante de recepción, en la zona horaria de negocio
     * @param list<string>      $diasNoHabiles fechas locales Y-m-d del calendario operativo
     */
    public static function vence(DateTimeImmutable $recepcion, string $tipo, array $diasNoHabiles): DateTimeImmutable
    {
        $dia = $recepcion->setTime(0, 0);
        if (!in_array($tipo, self::EN_DIAS_HABILES, true)) {
            return $dia->modify('+' . PoliticaDatos::DIAS_ACCESO . ' days');
        }
        $contados = 0;
        // Límite de seguridad: un año sin días hábiles indicaría un calendario mal cargado
        for ($n = 0; $n < 400 && $contados < PoliticaDatos::DIAS_RECTIFICACION; $n++) {
            $dia = $dia->modify('+1 day');
            if (CalendarioHabil::esHabil($dia, $diasNoHabiles)) {
                $contados++;
            }
        }

        return $dia;
    }

    /** Situación del plazo de una solicitud pendiente: EnPlazo, PorVencer (quedan DIAS_AVISO días o menos) o Vencido. */
    public static function situacionPlazo(string $vence, string $hoy): string
    {
        $dias = (int) (new DateTimeImmutable($hoy))->diff(new DateTimeImmutable($vence))->format('%r%a');

        return $dias < 0 ? 'Vencido' : ($dias <= self::DIAS_AVISO ? 'PorVencer' : 'EnPlazo');
    }

    /**
     * Valida el pedido (RN-86).
     *
     * @param array{tipo?: string, detalle?: string, solicitud_id?: int} $d
     * @return array<string, string> campo => mensaje; vacío si es válido
     */
    public static function validar(array $d): array
    {
        $errores = [];
        $tipo = (string) ($d['tipo'] ?? '');
        if (!isset(self::TIPOS[$tipo])) {
            $errores['tipo'] = 'Elija qué derecho quiere ejercer.';
        }
        $detalle = Campos::texto($d['detalle'] ?? '');
        if (Campos::largo($detalle) < 10) {
            $errores['detalle'] = 'Cuéntenos qué necesita (al menos 10 caracteres).';
        } elseif (Campos::largo($detalle) > self::LARGO_DETALLE) {
            $errores['detalle'] = 'El detalle admite hasta ' . self::LARGO_DETALLE . ' caracteres.';
        }
        if ($tipo === 'RevisionAutomatizada' && (int) ($d['solicitud_id'] ?? 0) <= 0) {
            $errores['solicitud_id'] = 'Elija la solicitud cuya decisión quiere que se revise.';
        }

        return $errores;
    }

    /**
     * Valida la respuesta al atender o denegar (RN-86): obligatoria y entre 10 y 1000 caracteres.
     *
     * @return array<string, string>
     */
    public static function validarRespuesta(string $estado, string $respuesta): array
    {
        $errores = [];
        if (!in_array($estado, ['Atendida', 'Denegada'], true)) {
            $errores['estado'] = 'Indique si la solicitud se atiende o se deniega.';
        }
        $texto = Campos::texto($respuesta);
        if (Campos::largo($texto) < 10) {
            $errores['respuesta'] = 'Escriba la respuesta a la persona (al menos 10 caracteres).';
        } elseif (Campos::largo($texto) > self::LARGO_RESPUESTA) {
            $errores['respuesta'] = 'La respuesta admite hasta ' . self::LARGO_RESPUESTA . ' caracteres.';
        }

        return $errores;
    }
}
