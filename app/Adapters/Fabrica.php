<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Comunicaciones\ComunicacionesInterface;
use App\Adapters\Comunicaciones\SimuladorComunicaciones;
use App\Adapters\Comunicaciones\SmtpComunicaciones;
use App\Adapters\InformacionCrediticia\InformacionCrediticiaInterface;
use App\Adapters\InformacionCrediticia\SimuladorInformacionCrediticia;
use App\Adapters\Pasarela\PasarelaInterface;
use App\Adapters\Pasarela\SimuladorPasarela;
use RuntimeException;

/**
 * Selecciona la implementación de cada integración según la configuración (sigccr.adaptador* en .env).
 * Las implementaciones reales se agregan en la iteración que construye cada integración (2.2.1).
 */
final class Fabrica
{
    public static function informacionCrediticia(): InformacionCrediticiaInterface
    {
        return match (config('Sigccr')->adaptadorInformacionCrediticia) {
            'simulador' => new SimuladorInformacionCrediticia(),
            default => throw new RuntimeException('Adaptador real de información crediticia pendiente (iteración 2).'),
        };
    }

    public static function pasarela(): PasarelaInterface
    {
        return match (config('Sigccr')->adaptadorPasarela) {
            'simulador' => new SimuladorPasarela(),
            default => throw new RuntimeException('Adaptador real de la pasarela pendiente (iteración 3).'),
        };
    }

    public static function comunicaciones(): ComunicacionesInterface
    {
        return match (config('Sigccr')->adaptadorComunicaciones) {
            'simulador' => new SimuladorComunicaciones(),
            'real' => new SmtpComunicaciones(),
            default => throw new RuntimeException('Adaptador de comunicaciones desconocido: use simulador o real.'),
        };
    }
}
