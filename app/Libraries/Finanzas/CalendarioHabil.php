<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;

/**
 * Vigencia de las aprobaciones en horas hábiles (RN-49).
 *
 * Se computan 72 horas entre las 00:00 y las 24:00 de lunes a viernes, excluidos los días no hábiles del calendario
 * operativo. El cómputo se suspende durante los días no hábiles; si la aprobación se registra en un día no hábil,
 * comienza a las 00:00 del siguiente día hábil. El vencimiento se calcula una sola vez, al registrar la aprobación.
 */
final class CalendarioHabil
{
    public const HORAS_APROBACION = 72;

    /** @param list<string> $diasNoHabiles fechas locales en formato Y-m-d */
    public static function esHabil(DateTimeImmutable $dia, array $diasNoHabiles): bool
    {
        return (int) $dia->format('N') <= 5 && !in_array($dia->format('Y-m-d'), $diasNoHabiles, true);
    }

    /**
     * @param DateTimeImmutable $registro instante de la aprobación, en la zona horaria de negocio
     * @param list<string>      $diasNoHabiles
     */
    public static function venceAprobacion(DateTimeImmutable $registro, array $diasNoHabiles, int $horas = self::HORAS_APROBACION): DateTimeImmutable
    {
        $restante = $horas * 3600;
        $cursor = $registro;
        // Límite de seguridad: un año de días no hábiles consecutivos indicaría un calendario mal cargado.
        for ($n = 0; $n < 366 + $horas; $n++) {
            $finDelDia = $cursor->setTime(0, 0)->modify('+1 day');
            if (self::esHabil($cursor, $diasNoHabiles)) {
                $disponible = $finDelDia->getTimestamp() - $cursor->getTimestamp();
                if ($restante <= $disponible) {
                    return $cursor->modify("+{$restante} seconds");
                }
                $restante -= $disponible;
            }
            $cursor = $finDelDia;
        }

        throw new \DomainException('No fue posible calcular el vencimiento: revise el calendario operativo.');
    }
}
