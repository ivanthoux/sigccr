<?php

declare(strict_types=1);

namespace App\Libraries\Seguridad;

use DateInterval;
use DateTimeImmutable;

/**
 * Bloqueo temporal por intentos fallidos (RN-62, CU-01 A1): cinco intentos dentro de 15 minutos bloquean la cuenta
 * durante 15 minutos. Opera sobre los campos de cuentas_acceso; no accede a la base, para poder probarse aislado.
 */
final class ControlIntentos
{
    public function __construct(
        private readonly int $maximo = 5,
        private readonly int $ventanaSegundos = 900,
        private readonly int $bloqueoSegundos = 900,
    ) {
    }

    /** El bloqueo temporal venció: la cuenta vuelve a Habilitada con el contador en cero. */
    public function bloqueoVencido(string $estado, ?DateTimeImmutable $bloqueadaHasta, DateTimeImmutable $ahora): bool
    {
        return $estado === 'BloqueadaTemporalmente' && ($bloqueadaHasta === null || $ahora >= $bloqueadaHasta);
    }

    /**
     * Registra un intento fallido sobre una cuenta habilitada.
     *
     * @return array{intentos_fallidos: int, inicio_ventana_intentos: DateTimeImmutable, bloquear: bool, bloqueada_hasta: ?DateTimeImmutable}
     */
    public function registrarFallo(int $intentos, ?DateTimeImmutable $inicioVentana, DateTimeImmutable $ahora): array
    {
        $ventanaVencida = $inicioVentana === null
            || $ahora > $inicioVentana->add(new DateInterval('PT' . $this->ventanaSegundos . 'S'));
        if ($ventanaVencida) {
            $intentos = 0;
            $inicioVentana = $ahora;
        }
        $intentos++;
        $bloquear = $intentos >= $this->maximo;

        return [
            'intentos_fallidos' => min($intentos, $this->maximo),
            'inicio_ventana_intentos' => $inicioVentana,
            'bloquear' => $bloquear,
            'bloqueada_hasta' => $bloquear ? $ahora->add(new DateInterval('PT' . $this->bloqueoSegundos . 'S')) : null,
        ];
    }
}
